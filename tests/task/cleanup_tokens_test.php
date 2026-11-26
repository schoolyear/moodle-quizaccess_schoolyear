<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace quizaccess_schoolyear\task;

use quizaccess_schoolyear\token;

/**
 * PHPUnit tests for the task that deletes expired entry tokens.
 *
 * @package    quizaccess_schoolyear
 * @copyright  2026 Schoolyear B.V.
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \quizaccess_schoolyear\task\cleanup_tokens
 */
final class cleanup_tokens_test extends \advanced_testcase {
    /**
     * Store an entry token for a new user of a new quiz.
     *
     * @param string $value Value of the token.
     * @param int $timeexpires Time the token expires.
     * @return token The stored token.
     */
    private function create_token(string $value, int $timeexpires): token {
        $course = $this->getDataGenerator()->create_course();
        $quiz = $this->getDataGenerator()->create_module('quiz', ['course' => $course->id]);
        $token = new token(0, (object) [
            'tokenhash' => hash('sha256', $value),
            'userid' => $this->getDataGenerator()->create_user()->id,
            'cmid' => $quiz->cmid,
            'workspaceid' => '3f1c9a52-6a1e-4c2b-9b7e-2d4f8e0a1b6c',
            'timeexpires' => $timeexpires,
        ]);
        return $token->create();
    }

    /**
     * Test the task deletes an expired token and keeps a valid one.
     */
    public function test_execute_deletes_expired_tokens(): void {
        $this->resetAfterTest();
        $expired = $this->create_token('expired', time() - 1);
        $valid = $this->create_token('valid', time() + HOURSECS);

        (new cleanup_tokens())->execute();

        $this->assertFalse(token::record_exists($expired->get('id')));
        $this->assertTrue(token::record_exists($valid->get('id')));
    }
}

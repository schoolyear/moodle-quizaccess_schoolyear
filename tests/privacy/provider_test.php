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

namespace quizaccess_schoolyear\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\transform;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use core_privacy\tests\request\approved_contextlist;
use quizaccess_schoolyear\quiz_settings;
use quizaccess_schoolyear\token;

/**
 * PHPUnit tests for the privacy provider.
 *
 * @package    quizaccess_schoolyear
 * @copyright  2026 Schoolyear B.V.
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \quizaccess_schoolyear\privacy\provider
 */
final class provider_test extends \core_privacy\tests\provider_testcase {
    /** Time the settings of the test quizzes were created. */
    private const TIMECREATED = 1700000000;

    /** Time the settings of the test quizzes were last modified. */
    private const TIMEMODIFIED = 1700086400;

    /** Entry tokens of the tests, as user name, quiz name and workspace ID. */
    private const TOKENS = [
        ['student', 'a', '3f1c9a52-6a1e-4c2b-9b7e-2d4f8e0a1b6c'],
        ['student', 'b', '7d2e4b61-0c5f-4a8e-b3d9-1e6f2a7c8b90'],
        ['classmate', 'a', 'c5a8e3f2-9b14-4d6c-a7e0-3f2b1d8c6a45'],
    ];

    /** @var \stdClass[] $users Test users by name: "teacher" last modified the settings of both quizzes. */
    private $users = [];

    /** @var \stdClass[] $quizzes Test quizzes with a Schoolyear exam, by name. */
    private $quizzes = [];

    /**
     * Called before every test.
     */
    public function setUp(): void {
        global $DB;

        parent::setUp();

        $this->resetAfterTest();

        foreach (['teacher', 'student', 'classmate', 'other'] as $name) {
            $this->users[$name] = $this->getDataGenerator()->create_user();
        }

        $course = $this->getDataGenerator()->create_course();
        foreach (['a', 'b'] as $name) {
            $quiz = $this->getDataGenerator()->create_module('quiz', ['course' => $course->id]);
            $DB->insert_record('quizaccess_schoolyear', [
                'quizid' => $quiz->id,
                'schoolyearenabled' => 1,
                'examid' => 'exam' . $name,
                'usermodified' => $this->users['teacher']->id,
                'timecreated' => self::TIMECREATED,
                'timemodified' => self::TIMEMODIFIED,
            ]);
            $this->quizzes[$name] = $quiz;
        }

        foreach (self::TOKENS as [$user, $quiz, $workspaceid]) {
            $token = new token(0, (object) [
                'tokenhash' => hash('sha256', $user . $quiz),
                'userid' => $this->users[$user]->id,
                'cmid' => $this->quizzes[$quiz]->cmid,
                'workspaceid' => $workspaceid,
                'timeexpires' => time() + HOURSECS,
            ]);
            $token->create();
        }
    }

    /**
     * Get the context of a test quiz.
     *
     * @param string $quiz Name of the quiz.
     * @return \context_module
     */
    private function get_context(string $quiz): \context_module {
        return \context_module::instance($this->quizzes[$quiz]->cmid);
    }

    /**
     * Export the data of a test user in the contexts of both test quizzes.
     *
     * @param string $user Name of the user.
     */
    private function export_user_data(string $user): void {
        writer::reset();
        $contextids = [$this->get_context('a')->id, $this->get_context('b')->id];
        provider::export_user_data(new approved_contextlist($this->users[$user], 'quizaccess_schoolyear', $contextids));
    }

    /**
     * Get the stored user data of each test quiz, with users by name.
     *
     * Reading the settings row with MUST_EXIST also asserts that the row was not deleted.
     *
     * @return array The user who last modified the settings, or null, and the users of the tokens, by quiz name.
     */
    private function get_user_data(): array {
        global $DB;

        $names = [];
        foreach ($this->users as $name => $user) {
            $names[$user->id] = $name;
        }

        $data = [];
        foreach ($this->quizzes as $name => $quiz) {
            $usermodified = $DB->get_field('quizaccess_schoolyear', 'usermodified', ['quizid' => $quiz->id], MUST_EXIST);
            $tokenusers = [];
            foreach ($DB->get_fieldset_select('quizaccess_schoolyear_token', 'userid', 'cmid = ?', [$quiz->cmid]) as $userid) {
                $tokenusers[] = $names[$userid];
            }
            sort($tokenusers);
            $data[$name] = [
                'usermodified' => $names[$usermodified] ?? null,
                'tokens' => $tokenusers,
            ];
        }
        return $data;
    }

    /**
     * Provider of test users and the test quizzes they have data in.
     *
     * @return array
     */
    public static function contexts_provider(): array {
        return [
            'settings modifier' => ['teacher', ['a', 'b']],
            'tokens in both quizzes' => ['student', ['a', 'b']],
            'token in one quiz' => ['classmate', ['a']],
            'no data' => ['other', []],
        ];
    }

    /**
     * Provider of test quizzes and the test users with data in them.
     *
     * @return array
     */
    public static function users_in_context_provider(): array {
        return [
            'settings modifier and token users' => ['a', ['classmate', 'student', 'teacher']],
            'settings modifier and token user' => ['b', ['student', 'teacher']],
        ];
    }

    /**
     * Provider of test users whose data is deleted in the first test quiz, and the user data left after it.
     *
     * @return array
     */
    public static function delete_provider(): array {
        return [
            'settings modifier' => [['teacher'], [
                'a' => ['usermodified' => null, 'tokens' => ['classmate', 'student']],
                'b' => ['usermodified' => 'teacher', 'tokens' => ['student']],
            ]],
            'token user' => [['student'], [
                'a' => ['usermodified' => 'teacher', 'tokens' => ['classmate']],
                'b' => ['usermodified' => 'teacher', 'tokens' => ['student']],
            ]],
            'settings modifier and token user' => [['teacher', 'student'], [
                'a' => ['usermodified' => null, 'tokens' => ['classmate']],
                'b' => ['usermodified' => 'teacher', 'tokens' => ['student']],
            ]],
            'user without data' => [['other'], [
                'a' => ['usermodified' => 'teacher', 'tokens' => ['classmate', 'student']],
                'b' => ['usermodified' => 'teacher', 'tokens' => ['student']],
            ]],
        ];
    }

    /**
     * Test every string named in the metadata exists.
     */
    public function test_get_metadata_strings_exist(): void {
        $stringmanager = get_string_manager();
        $collection = provider::get_metadata(new collection('quizaccess_schoolyear'));

        foreach ($collection->get_collection() as $item) {
            $identifiers = array_merge([$item->get_summary()], array_values($item->get_privacy_fields()));
            foreach ($identifiers as $identifier) {
                $this->assertTrue($stringmanager->string_exists($identifier, 'quizaccess_schoolyear'), $identifier);
            }
        }
    }

    /**
     * Test the contexts of a user are the quizzes whose settings they last modified or that they have a token for.
     *
     * @param string $user Name of the user.
     * @param string[] $quizzes Names of the quizzes the user has data in.
     * @dataProvider contexts_provider
     */
    public function test_get_contexts_for_userid(string $user, array $quizzes): void {
        $contextlist = provider::get_contexts_for_userid($this->users[$user]->id);

        $expected = [];
        foreach ($quizzes as $quiz) {
            $expected[] = $this->get_context($quiz)->id;
        }
        $this->assertEqualsCanonicalizing($expected, $contextlist->get_contextids());
    }

    /**
     * Test the users in a quiz context are the user who last modified its settings and the users of its tokens.
     *
     * @param string $quiz Name of the quiz.
     * @param string[] $users Names of the users with data in the quiz.
     * @dataProvider users_in_context_provider
     */
    public function test_get_users_in_context(string $quiz, array $users): void {
        $userlist = new userlist($this->get_context($quiz), 'quizaccess_schoolyear');
        provider::get_users_in_context($userlist);

        $expected = [];
        foreach ($users as $user) {
            $expected[] = $this->users[$user]->id;
        }
        $this->assertEqualsCanonicalizing($expected, $userlist->get_userids());
    }

    /**
     * Test the export of the user who last modified the settings has the settings of each quiz, in the quiz context.
     */
    public function test_export_user_data_exports_settings(): void {
        $this->export_user_data('teacher');

        $pluginname = get_string('pluginname', 'quizaccess_schoolyear');
        foreach ($this->quizzes as $name => $quiz) {
            $writer = writer::with_context($this->get_context($name));
            $this->assertEquals((object) [
                'quizid' => $quiz->id,
                'usermodified' => $this->users['teacher']->id,
                'timecreated' => transform::datetime(self::TIMECREATED),
                'timemodified' => transform::datetime(self::TIMEMODIFIED),
            ], $writer->get_data([$pluginname, quiz_settings::TABLE, 1]));
            $this->assertEmpty($writer->get_data([$pluginname, quiz_settings::TABLE, 2]));
            $this->assertEmpty($writer->get_data([$pluginname, token::TABLE, 1]));
        }
    }

    /**
     * Test the export of a token user has their token of each quiz, in the quiz context and without the token hash.
     */
    public function test_export_user_data_exports_tokens_without_hash(): void {
        $this->export_user_data('student');

        $pluginname = get_string('pluginname', 'quizaccess_schoolyear');
        foreach ($this->quizzes as $name => $quiz) {
            $token = token::get_record(['userid' => $this->users['student']->id, 'cmid' => $quiz->cmid]);
            $writer = writer::with_context($this->get_context($name));
            $this->assertEquals((object) [
                'workspaceid' => $token->get('workspaceid'),
                'timecreated' => transform::datetime($token->get('timecreated')),
                'timeexpires' => transform::datetime($token->get('timeexpires')),
            ], $writer->get_data([$pluginname, token::TABLE, 1]));
            $this->assertEmpty($writer->get_data([$pluginname, token::TABLE, 2]));
            $this->assertEmpty($writer->get_data([$pluginname, quiz_settings::TABLE, 1]));
        }
    }

    /**
     * Test the export of a user without data in the quizzes is empty.
     */
    public function test_export_user_data_exports_nothing_for_unrelated_user(): void {
        $this->export_user_data('other');

        foreach (array_keys($this->quizzes) as $name) {
            $this->assertFalse(writer::with_context($this->get_context($name))->has_any_data());
        }
    }

    /**
     * Test deleting all user data in a quiz context deletes its tokens and keeps its settings without the modifier.
     */
    public function test_delete_data_for_all_users_in_context(): void {
        provider::delete_data_for_all_users_in_context($this->get_context('a'));

        $this->assertSame([
            'a' => ['usermodified' => null, 'tokens' => []],
            'b' => ['usermodified' => 'teacher', 'tokens' => ['student']],
        ], $this->get_user_data());
    }

    /**
     * Test deleting the data of a user deletes only their data in the approved context.
     *
     * @param string[] $users Names of the users whose data is deleted, one request each.
     * @param array $expected User data left after the deletion.
     * @dataProvider delete_provider
     */
    public function test_delete_data_for_user(array $users, array $expected): void {
        foreach ($users as $user) {
            $contextlist = new approved_contextlist($this->users[$user], 'quizaccess_schoolyear', [$this->get_context('a')->id]);
            provider::delete_data_for_user($contextlist);
        }

        $this->assertSame($expected, $this->get_user_data());
    }

    /**
     * Test deleting the data of a list of users deletes only their data in the approved context.
     *
     * @param string[] $users Names of the users whose data is deleted.
     * @param array $expected User data left after the deletion.
     * @dataProvider delete_provider
     */
    public function test_delete_data_for_users(array $users, array $expected): void {
        $userids = [];
        foreach ($users as $user) {
            $userids[] = $this->users[$user]->id;
        }
        provider::delete_data_for_users(new approved_userlist($this->get_context('a'), 'quizaccess_schoolyear', $userids));

        $this->assertSame($expected, $this->get_user_data());
    }
}

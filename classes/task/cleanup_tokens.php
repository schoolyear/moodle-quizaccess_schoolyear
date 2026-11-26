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

/**
 * Scheduled task to delete expired entry tokens of the quizaccess_schoolyear plugin.
 *
 * @package    quizaccess_schoolyear
 * @copyright  2026 Schoolyear B.V.
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace quizaccess_schoolyear\task;

use quizaccess_schoolyear\token;

/**
 * Scheduled task to delete expired Schoolyear entry tokens.
 */
class cleanup_tokens extends \core\task\scheduled_task {
    /**
     * Get a descriptive name for this task.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('taskcleanuptokens', 'quizaccess_schoolyear');
    }

    /**
     * Delete the entry tokens that have expired.
     */
    public function execute(): void {
        global $DB;

        $DB->delete_records_select(token::TABLE, 'timeexpires < :now', ['now' => time()]);
    }
}

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
 * Logs a student in from the Schoolyear browser with an entry token and redirects to their quiz.
 *
 * @package    quizaccess_schoolyear
 * @copyright  2026 Schoolyear B.V.
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// The entry token replaces require_login: quizaccess_schoolyear::log_in_with_entry_token logs the user in.
// phpcs:ignore moodle.Files.RequireLogin.Missing
require_once(__DIR__ . '/../../../../config.php');
require_once($CFG->dirroot . '/mod/quiz/locallib.php');
require_once($CFG->dirroot . '/mod/quiz/accessrule/schoolyear/rule.php');

$token = required_param('token', PARAM_ALPHANUM);

// The page URL leaves out the token, so it does not end up in logs.
$PAGE->set_context(context_system::instance());
$PAGE->set_url('/mod/quiz/accessrule/schoolyear/enter.php');

$url = quizaccess_schoolyear::log_in_with_entry_token($token);
redirect($url);

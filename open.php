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
 * Redirects a teacher to a fresh Schoolyear dashboard or settings widget URL.
 *
 * @package    quizaccess_schoolyear
 * @copyright  2026 Schoolyear B.V.
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// The require_login check is done in quizaccess_schoolyear::get_teacher_ui_url, but the automated checker can't see this.
// phpcs:ignore moodle.Files.RequireLogin.Missing
require_once(__DIR__ . '/../../../../config.php');
require_once($CFG->dirroot . '/mod/quiz/locallib.php');
require_once($CFG->dirroot . '/mod/quiz/accessrule/schoolyear/rule.php');

$cmid = required_param('cmid', PARAM_INT);
$target = required_param('target', PARAM_ALPHA);

$PAGE->set_url('/mod/quiz/accessrule/schoolyear/open.php', ['cmid' => $cmid, 'target' => $target]);

$url = quizaccess_schoolyear::get_teacher_ui_url($cmid, $target);
redirect($url);

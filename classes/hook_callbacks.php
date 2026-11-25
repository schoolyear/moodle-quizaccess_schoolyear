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

namespace quizaccess_schoolyear;

/**
 * Callbacks for hooks.
 *
 * @package    quizaccess_schoolyear
 * @copyright  2026 Schoolyear B.V.
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class hook_callbacks {
    /**
     * Listener for the after_config hook.
     *
     * Delegates to quizaccess_schoolyear_after_config(), which older Moodle versions call from lib.php directly.
     *
     * @param \core\hook\after_config $hook
     */
    public static function after_config(\core\hook\after_config $hook): void {
        global $CFG;

        if (during_initial_install() || isset($CFG->upgraderunning)) {
            // Do nothing during installation or upgrade, where Moodle never called the lib.php callback either.
            return;
        }

        require_once($CFG->dirroot . '/mod/quiz/accessrule/schoolyear/lib.php');
        \quizaccess_schoolyear_after_config();
    }
}

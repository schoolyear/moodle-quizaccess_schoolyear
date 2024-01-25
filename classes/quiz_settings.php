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
 * Quiz settings for the quizaccess_schoolyear plugin.
 *
 * @package    quizaccess_schoolyear
 * @copyright  2023 Schoolyear B.V.
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace quizaccess_schoolyear;

/**
 * Entity model to describe Schoolyear quiz settings.
 */
class quiz_settings extends \core\persistent {

    /** Database table name */
    const TABLE = 'quizaccess_schoolyear';

    protected static function define_properties() : array {
        return [
            'quizid' => [
                'type' => PARAM_INT,
            ],
            'schoolyearenabled' => [
                'type' => PARAM_INT,
                'default' => 0,
            ],
            'examid' => [
                'type' => PARAM_TEXT,
                'default' => '',
                'null' => NULL_ALLOWED,
            ],
        ];
    }
}

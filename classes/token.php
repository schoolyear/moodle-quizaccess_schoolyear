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
 * Entry token for the quizaccess_schoolyear plugin.
 *
 * @package    quizaccess_schoolyear
 * @copyright  2026 Schoolyear B.V.
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace quizaccess_schoolyear;

/**
 * Entity model to describe a token that logs a student in from the Schoolyear browser.
 */
class token extends \core\persistent {
    /** Database table name */
    const TABLE = 'quizaccess_schoolyear_token';

    /**
     * Define the properties of the persistent.
     *
     * @return array Properties definition.
     */
    protected static function define_properties(): array {
        return [
            'tokenhash' => [
                'type' => PARAM_ALPHANUM,
            ],
            'userid' => [
                'type' => PARAM_INT,
            ],
            'cmid' => [
                'type' => PARAM_INT,
            ],
            'workspaceid' => [
                'type' => PARAM_ALPHANUMEXT,
            ],
            'timeexpires' => [
                'type' => PARAM_INT,
            ],
        ];
    }
}

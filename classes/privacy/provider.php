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
 * Privacy subsystem implementation for the quizaccess_schoolyear plugin.
 *
 * @package    quizaccess_schoolyear
 * @copyright  2023 Schoolyear B.V.
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace quizaccess_schoolyear\privacy;

use core_privacy\local\metadata\collection;

class provider implements
    \core_privacy\local\metadata\provider {

    public static function get_metadata(collection $items) : collection {
        $items->add_external_location_link(
            'schoolyear',
            [
                'userid' => 'privacy:metadata:schoolyear:userid',
                'idnumber' => 'privacy:metadata:schoolyear:idnumber',
                'firstname' => 'privacy:metadata:schoolyear:fullname',
                'lastname' => 'privacy:metadata:schoolyear:fullname',
            ],
            'privacy:metadata:schoolyear');

        return $items;
    }
}

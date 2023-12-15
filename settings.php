<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle. If not, see <https://www.gnu.org/licenses/>.

/**
 * Configuration settings for the quizaccess_schoolyear plugin.
 *
 * @package    quizaccess_schoolyear
 * @copyright  2023 Schoolyear B.V.
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings->add(new admin_setting_configselect('quizaccess_schoolyear/apibaseaddress',
        get_string('apibaseaddress', 'quizaccess_schoolyear'),
        '',
        'https://api.schoolyear.app',
        array(
            'https://api.schoolyear.app' => 'https://api.schoolyear.app',
            'https://beta.api.schoolyear.app' => 'https://beta.api.schoolyear.app',
            'https://testing.api.schoolyear.app' => 'https://testing.api.schoolyear.app',
            'https://dev.api.schoolyear.app' => 'https://dev.api.schoolyear.app'
        )
    ));

    $settings->add(new admin_setting_configtext('quizaccess_schoolyear/apikey',
        get_string('apikey', 'quizaccess_schoolyear'),
        '',
        null,
        PARAM_RAW));
}

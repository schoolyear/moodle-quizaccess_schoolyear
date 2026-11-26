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
 * Strings for the quizaccess_schoolyear plugin.
 *
 * @package    quizaccess_schoolyear
 * @copyright  2023 Schoolyear B.V.
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Schoolyear';
$string['schoolyearenabled'] = 'Schoolyear Exams';
$string['schoolyearenabledoption'] = 'enabled';
$string['schoolyeardisabledoption'] = 'disabled';
$string['requiresschoolyear'] = 'This quiz requires the Schoolyear application.';
$string['errorverifying'] = 'An error occurred while verifying Schoolyear browser.';
$string['errornoexam'] = 'This quiz is not linked to a Schoolyear exam.';
$string['errornourl'] = 'The Schoolyear API did not return a URL.';
$string['errorunknowntarget'] = 'Unknown Schoolyear page requested.';
$string['errorentrydenied'] = 'This Schoolyear link is not valid or has expired. Close Schoolyear and start the quiz again from Moodle.';
$string['errorloggedinas'] = 'You cannot start Schoolyear while you are logged in as another user.';
$string['startquiz'] = 'Start quiz';
$string['settingswidget'] = 'Settings widget';
$string['opensettingswidget'] = 'Open settings widget';
$string['dashboard'] = 'Dashboard';
$string['opendashboard'] = 'Open dashboard';
$string['savefirst'] = 'Save to access the settings and dashboard buttons for this exam.';
$string['timingerror'] = 'Opening and closing time must be set for a Schoolyear exam.';
$string['apibaseaddress'] = 'Schoolyear API base address';
$string['apikey'] = 'Schoolyear API key';
$string['taskcleanuptokens'] = 'Delete expired Schoolyear entry tokens';
$string['privacy:metadata:schoolyear'] = 'In order to integrate with Schoolyear, user data needs to be exchanged with Schoolyear services.';
$string['privacy:metadata:schoolyear:userid'] = 'The userid is sent from Moodle to allow Schoolyear to distinguish users.';
$string['privacy:metadata:schoolyear:idnumber'] = 'The idnumber is sent from Moodle to allow Schoolyear to identify users (optional if not set).';
$string['privacy:metadata:schoolyear:firstname'] = 'Your first name is sent to Schoolyear to allow a better user experience.';
$string['privacy:metadata:schoolyear:lastname'] = 'Your last name is sent to Schoolyear to allow a better user experience.';
$string['privacy:metadata:schoolyear:email'] = 'Your email address, if set, is sent to Schoolyear as a hint for the account to sign in with.';
$string['privacy:metadata:schoolyear:entryurl'] = 'A URL with a token that logs you in to the quiz is sent to Schoolyear, so the Schoolyear browser can open the quiz.';
$string['privacy:metadata:quizaccess_schoolyear'] = 'Schoolyear settings of a quiz, which link the quiz to its Schoolyear exam.';
$string['privacy:metadata:quizaccess_schoolyear:quizid'] = 'The ID of the quiz the settings belong to.';
$string['privacy:metadata:quizaccess_schoolyear:usermodified'] = 'The ID of the user who last modified the settings.';
$string['privacy:metadata:quizaccess_schoolyear:timecreated'] = 'The time the settings were created.';
$string['privacy:metadata:quizaccess_schoolyear:timemodified'] = 'The time the settings were last modified.';
$string['privacy:metadata:quizaccess_schoolyear_token'] = 'Entry tokens that log a user in to a quiz from the Schoolyear browser.';
$string['privacy:metadata:quizaccess_schoolyear_token:tokenhash'] = 'The hash of the token in the entry URL.';
$string['privacy:metadata:quizaccess_schoolyear_token:userid'] = 'The ID of the user the token logs in.';
$string['privacy:metadata:quizaccess_schoolyear_token:cmid'] = 'The ID of the quiz the token was created for.';
$string['privacy:metadata:quizaccess_schoolyear_token:workspaceid'] = 'The ID of the Schoolyear workspace the token was created for.';
$string['privacy:metadata:quizaccess_schoolyear_token:timeexpires'] = 'The time after which the token is no longer accepted.';
$string['privacy:metadata:quizaccess_schoolyear_token:usermodified'] = 'The ID of the user who last modified the token.';
$string['privacy:metadata:quizaccess_schoolyear_token:timecreated'] = 'The time the token was created.';
$string['privacy:metadata:quizaccess_schoolyear_token:timemodified'] = 'The time the token was last modified.';

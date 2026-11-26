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
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\transform;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use quizaccess_schoolyear\quiz_settings;
use quizaccess_schoolyear\token;

/**
 * Privacy Subsystem implementation for the quizaccess_schoolyear plugin.
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\request\plugin\provider {
    /**
     * Returns metadata about this plugin's privacy data.
     *
     * @param collection $items The collection to add metadata to.
     * @return collection The updated collection.
     */
    public static function get_metadata(collection $items): collection {
        $items->add_external_location_link(
            'schoolyear',
            [
                'userid' => 'privacy:metadata:schoolyear:userid',
                'idnumber' => 'privacy:metadata:schoolyear:idnumber',
                'firstname' => 'privacy:metadata:schoolyear:firstname',
                'lastname' => 'privacy:metadata:schoolyear:lastname',
                'email' => 'privacy:metadata:schoolyear:email',
                'entryurl' => 'privacy:metadata:schoolyear:entryurl',
            ],
            'privacy:metadata:schoolyear'
        );

        $items->add_database_table(
            'quizaccess_schoolyear',
            [
                'quizid' => 'privacy:metadata:quizaccess_schoolyear:quizid',
                'usermodified' => 'privacy:metadata:quizaccess_schoolyear:usermodified',
                'timecreated' => 'privacy:metadata:quizaccess_schoolyear:timecreated',
                'timemodified' => 'privacy:metadata:quizaccess_schoolyear:timemodified',
            ],
            'privacy:metadata:quizaccess_schoolyear'
        );

        $items->add_database_table(
            'quizaccess_schoolyear_token',
            [
                'tokenhash' => 'privacy:metadata:quizaccess_schoolyear_token:tokenhash',
                'userid' => 'privacy:metadata:quizaccess_schoolyear_token:userid',
                'cmid' => 'privacy:metadata:quizaccess_schoolyear_token:cmid',
                'workspaceid' => 'privacy:metadata:quizaccess_schoolyear_token:workspaceid',
                'timeexpires' => 'privacy:metadata:quizaccess_schoolyear_token:timeexpires',
                'usermodified' => 'privacy:metadata:quizaccess_schoolyear_token:usermodified',
                'timecreated' => 'privacy:metadata:quizaccess_schoolyear_token:timecreated',
                'timemodified' => 'privacy:metadata:quizaccess_schoolyear_token:timemodified',
            ],
            'privacy:metadata:quizaccess_schoolyear_token'
        );

        return $items;
    }

    /**
     * Get the list of contexts that contain user information for the specified user.
     *
     * @param int $userid The user to search.
     * @return contextlist The quiz module contexts with data of the user.
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();

        $sql = "SELECT c.id
                  FROM {quizaccess_schoolyear} s
                  JOIN {course_modules} cm ON cm.instance = s.quizid
                  JOIN {modules} m ON m.id = cm.module AND m.name = :modulename
                  JOIN {context} c ON c.instanceid = cm.id AND c.contextlevel = :contextlevel
                 WHERE s.usermodified = :userid";
        $params = [
            'modulename' => 'quiz',
            'contextlevel' => CONTEXT_MODULE,
            'userid' => $userid,
        ];
        $contextlist->add_from_sql($sql, $params);

        $sql = "SELECT c.id
                  FROM {quizaccess_schoolyear_token} t
                  JOIN {context} c ON c.instanceid = t.cmid AND c.contextlevel = :contextlevel
                 WHERE t.userid = :userid";
        $params = [
            'contextlevel' => CONTEXT_MODULE,
            'userid' => $userid,
        ];
        $contextlist->add_from_sql($sql, $params);

        return $contextlist;
    }

    /**
     * Get the list of users who have data within a context.
     *
     * @param userlist $userlist The userlist containing the list of users who have data in this context/plugin combination.
     */
    public static function get_users_in_context(userlist $userlist) {
        $context = $userlist->get_context();
        if (!$context instanceof \context_module) {
            return;
        }

        $sql = "SELECT s.usermodified
                  FROM {quizaccess_schoolyear} s
                  JOIN {course_modules} cm ON cm.instance = s.quizid
                  JOIN {modules} m ON m.id = cm.module AND m.name = :modulename
                 WHERE cm.id = :cmid";
        $params = [
            'modulename' => 'quiz',
            'cmid' => $context->instanceid,
        ];
        $userlist->add_from_sql('usermodified', $sql, $params);

        $sql = "SELECT t.userid
                  FROM {quizaccess_schoolyear_token} t
                 WHERE t.cmid = :cmid";
        $params = [
            'cmid' => $context->instanceid,
        ];
        $userlist->add_from_sql('userid', $sql, $params);
    }

    /**
     * Export all user data for the specified user, in the specified contexts.
     *
     * @param approved_contextlist $contextlist The approved contexts to export information for.
     */
    public static function export_user_data(approved_contextlist $contextlist) {
        global $DB;

        $userid = $contextlist->get_user()->id;
        $pluginname = get_string('pluginname', 'quizaccess_schoolyear');

        foreach ($contextlist->get_contexts() as $context) {
            if ($context->contextlevel != CONTEXT_MODULE) {
                continue;
            }
            $cm = get_coursemodule_from_id('quiz', $context->instanceid);
            if (!$cm) {
                continue;
            }
            $writer = writer::with_context($context);

            $settingslist = $DB->get_records('quizaccess_schoolyear', ['quizid' => $cm->instance, 'usermodified' => $userid], 'id');
            $index = 0;
            foreach ($settingslist as $settings) {
                $index++;
                $writer->export_data([$pluginname, quiz_settings::TABLE, $index], (object) [
                    'quizid' => $settings->quizid,
                    'usermodified' => $settings->usermodified,
                    'timecreated' => transform::datetime($settings->timecreated),
                    'timemodified' => transform::datetime($settings->timemodified),
                ]);
            }

            $tokens = $DB->get_records('quizaccess_schoolyear_token', ['cmid' => $cm->id, 'userid' => $userid], 'id');
            $index = 0;
            foreach ($tokens as $token) {
                $index++;
                $writer->export_data([$pluginname, token::TABLE, $index], (object) [
                    'workspaceid' => $token->workspaceid,
                    'timecreated' => transform::datetime($token->timecreated),
                    'timeexpires' => transform::datetime($token->timeexpires),
                ]);
            }
        }
    }

    /**
     * Delete all data for all users in the specified context.
     *
     * @param \context $context The specific context to delete data for.
     */
    public static function delete_data_for_all_users_in_context(\context $context) {
        global $DB;

        if ($context->contextlevel != CONTEXT_MODULE) {
            return;
        }

        $DB->delete_records('quizaccess_schoolyear_token', ['cmid' => $context->instanceid]);

        $cm = get_coursemodule_from_id('quiz', $context->instanceid);
        if ($cm) {
            $DB->set_field('quizaccess_schoolyear', 'usermodified', 0, ['quizid' => $cm->instance]);
        }
    }

    /**
     * Delete all user data for the specified user, in the specified contexts.
     *
     * @param approved_contextlist $contextlist The approved contexts and user information to delete information for.
     */
    public static function delete_data_for_user(approved_contextlist $contextlist) {
        $userids = [$contextlist->get_user()->id];
        foreach ($contextlist->get_contexts() as $context) {
            self::delete_data_for_users_in_context($context, $userids);
        }
    }

    /**
     * Delete multiple users within a single context.
     *
     * @param approved_userlist $userlist The approved context and user information to delete information for.
     */
    public static function delete_data_for_users(approved_userlist $userlist) {
        self::delete_data_for_users_in_context($userlist->get_context(), $userlist->get_userids());
    }

    /**
     * Delete the tokens of users in a quiz context and remove the users as last modifier of the quiz settings.
     *
     * The settings row itself is kept, because it links the quiz to its Schoolyear exam.
     *
     * @param \context $context The context to delete data in.
     * @param int[] $userids The users to delete data for.
     */
    private static function delete_data_for_users_in_context(\context $context, array $userids): void {
        global $DB;

        if ($context->contextlevel != CONTEXT_MODULE || empty($userids)) {
            return;
        }

        [$insql, $inparams] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED);

        $DB->delete_records_select(
            'quizaccess_schoolyear_token',
            "cmid = :cmid AND userid $insql",
            ['cmid' => $context->instanceid] + $inparams
        );

        $cm = get_coursemodule_from_id('quiz', $context->instanceid);
        if ($cm) {
            $DB->set_field_select(
                'quizaccess_schoolyear',
                'usermodified',
                0,
                "quizid = :quizid AND usermodified $insql",
                ['quizid' => $cm->instance] + $inparams
            );
        }
    }
}

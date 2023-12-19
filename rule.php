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
 * Implementaton of the quizaccess_schoolyear plugin.
 *
 * @package    quizaccess_schoolyear
 * @copyright  2023 Schoolyear B.V.
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

use quizaccess_schoolyear\quiz_settings;
require_once($CFG->dirroot . '/mod/quiz/accessrule/accessrulebase.php');

class quizaccess_schoolyear extends quiz_access_rule_base {

    private const PLUGIN_NAME = 'quizaccess_schoolyear';
    private const X_SY_SIGNATURE_HEADER = 'HTTP_X_SY_SIGNATURE';

    public static function make(quiz $quizobj, $timenow, $canignoretimelimits) {
        if (empty($quizobj->get_quiz()->schoolyearenabled)) {
            return null;
        }

        return new self($quizobj, $timenow);
    }

    public function prevent_access() {
        $result = self::validate_signature();
        if (is_string($result)) {
            return [$result];
        } else if ($result) {
            global $PAGE;
            $PAGE->set_pagelayout('secure');
            return false;
        }

        global $USER;
        if (is_null($USER->idnumber)) {
            return [get_string('invaliduseridnumber', 'quizaccess_schoolyear')];
        }

        $result = self::create_workspace($this->quiz->examid, $this->quiz->cmid, $USER->idnumber);
        return [
            get_string('requiresschoolyear', 'quizaccess_schoolyear'),
            $result,
        ];
    }

    public static function validate_signature() {
        if (isset($_SERVER[self::X_SY_SIGNATURE_HEADER])) {
            $json = json_encode(['x_sy_signature' => trim($_SERVER[self::X_SY_SIGNATURE_HEADER])]);
            $response = self::api_request('POST', '/v2/signature/validate', $json);

            if ($response) {
                return true;
            } else {
                return get_string('errorverifying', 'quizaccess_schoolyear');
            }
        }

        return false;
    }

    public static function encrypt_cookie(string $input) {
        $apikey = get_config(self::PLUGIN_NAME, 'apikey');
        $key = substr(hash('sha256', $apikey, true), 0, 32);
        $cipher = 'aes-256-gcm';
        $ivlen = openssl_cipher_iv_length($cipher);
        $taglen = 16;
        $iv = openssl_random_pseudo_bytes($ivlen);
        $tag = "";
        $ciphertext = openssl_encrypt($input, $cipher, $key, OPENSSL_RAW_DATA, $iv, $tag, "", $taglen);
        return base64_encode($iv.$ciphertext.$tag);
    }

    public static function create_workspace($examid, $cmid, $useridnumber) {
        global $USER, $CFG;
        $syc = rawurlencode(self::encrypt_cookie($_COOKIE['MoodleSession'.$CFG->sessioncookie]));
        $syr = urlencode("/mod/quiz/view.php?id=$cmid");

        $elementid = \core\uuid::generate();
        $json = json_encode([
            'personal_information' => [
                'org_code' => $useridnumber,
                'first_name' => $USER->firstname,
                'last_name' => $USER->lastname,
            ],
            'federated_user_id' => $USER->id,
            'vault' => [
                'content' => [
                    'elements' => [
                        $elementid => [
                            'type' => 'web_page_url',
                            'url' => [
                                'url' => "$CFG->wwwroot/login/index.php?noredirect=1&syc=$syc&syr=$syr",
                            ],
                        ],
                    ],
                    'entry_points' => [
                        ['element_id' => $elementid],
                    ],
                ],
            ],
        ], JSON_UNESCAPED_SLASHES);

        $response = self::api_request("POST", "/v2/exam/$examid/workspace", $json);
        if ($response) {
            $label = get_string('startquiz', 'quizaccess_schoolyear');
            $button = html_writer::start_tag('div', ['class' => 'singlebutton']);
            $button .= html_writer::link($response->onboarding_url, $label, [
                'class' => 'btn btn-primary',
                'title' => $label,
                'target' => '_blank',
            ]);
            $button .= html_writer::end_tag('div');
            return $button;
        }
    }

    public static function add_settings_form_fields(mod_quiz_mod_form $quizform, MoodleQuickForm $mform) {
        $mform->addElement('select', 'schoolyearenabled', get_string('schoolyearenabled', 'quizaccess_schoolyear'),
            [
                0 => get_string('schoolyeardisabledoption', 'quizaccess_schoolyear'),
                1 => get_string('schoolyearenabledoption', 'quizaccess_schoolyear'),
            ]);

        self::add_settings_ui_button($quizform, $mform);
        self::add_dashboard_ui_button($quizform, $mform);
        self::add_settings_dashboard_ui_label($quizform, $mform);
    }

    public static function add_settings_ui_button(mod_quiz_mod_form $quizform, MoodleQuickForm $mform) {
        $record = quiz_settings::get_record(['quizid' => $quizform->get_instance()]);
        if (!empty($record)) {
            $examid = $record->get('examid');

            $response = self::api_request('POST', "/v2/exam/$examid/ui/settings");

            if ($response) {
                $label = get_string('opensettingswidget', 'quizaccess_schoolyear');
                $btn = html_writer::start_tag('div', ['class' => 'singlebutton']);
                $btn .= html_writer::link($response->url, $label, [
                    'class' => 'btn btn-secondary',
                    'title' => $label,
                    'target' => '_blank',
                ]);
                $btn .= html_writer::end_tag('div');
                $btngroup = [$mform->createElement('html', $btn)];
                $mform->addGroup($btngroup, 'sy-settings-btn', get_string('settingswidget', 'quizaccess_schoolyear'), ' ', false);
                $mform->hideIf('sy-settings-btn', 'schoolyearenabled', 'neq', 1);
            }
        }
    }

    public static function add_dashboard_ui_button(mod_quiz_mod_form $quizform, MoodleQuickForm $mform) {
        $record = quiz_settings::get_record(['quizid' => $quizform->get_instance()]);
        if (!empty($record)) {
            $examid = $record->get('examid');

            $response = self::api_request('POST', "/v2/exam/$examid/ui/dashboard");

            if ($response) {
                $label = get_string('opendashboard', 'quizaccess_schoolyear');
                $btn = html_writer::start_tag('div', ['class' => 'singlebutton']);
                $btn .= html_writer::link($response->url, $label, [
                    'class' => 'btn btn-secondary',
                    'title' => $label,
                    'target' => '_blank',
                ]);
                $btn .= html_writer::end_tag('div');
                $btngroup = [$mform->createElement('html', $btn)];
                $mform->addGroup($btngroup, 'sy-dashboard-btn', get_string('dashboard', 'quizaccess_schoolyear'), ' ', false);
                $mform->hideIf('sy-dashboard-btn', 'schoolyearenabled', 'neq', 1);
            }
        }
    }

    public static function add_settings_dashboard_ui_label(mod_quiz_mod_form $quizform, MoodleQuickForm $mform) {
        $record = quiz_settings::get_record(['quizid' => $quizform->get_instance()]);
        if (empty($record)) {
            $msg = get_string('savefirst', 'quizaccess_schoolyear');
            $group = [$mform->createElement('static', 'sy-btn-label', 'alert', $msg)];
            $mform->addGroup($group, 'sy-label-group', '', ' ', false);
            $mform->hideIf('sy-label-group', 'schoolyearenabled', 'neq', 1);
        }
    }

    public static function validate_settings_form_fields(array $errors, array $data, $files, mod_quiz_mod_form $quizform) {
        $name = $data['name'];
        $timeopen = $data['timeopen'];
        $timeclose = $data['timeclose'];
        $schoolyearenabled = $data['schoolyearenabled'];

        if ($schoolyearenabled) {
            if ($timeopen == 0 || $timeclose == 0) {
                $msg = get_string('timingerror', 'quizaccess_schoolyear');
                array_push($errors, $msg);
                \core\notification::error($msg);
                return $errors;
            }

            if ($timeclose - $timeopen > 86400) {
                $msg = get_string('24herror', 'quizaccess_schoolyear');
                array_push($errors, $msg);
                \core\notification::error($msg);
                return $errors;
            }

            // Update if needed.
            $current = $quizform->get_current();
            $quiz = new stdClass();
            $quiz->id = $current->id;
            $quiz->name = $name;
            $quiz->timeopen = $timeopen;
            $quiz->timeclose = $timeclose;

            global $DB;
            $exists = $DB->record_exists(self::PLUGIN_NAME, ['quizid' => $quiz->id]);
            if ($exists) {
                try {
                    self::update_exam($quiz);
                } catch (Exception $e) {
                    $msg = $e->getMessage();
                    array_push($errors, $msg);
                    \core\notification::error($msg);
                    return $errors;
                }
            }
        }

        return $errors;
    }

    public static function get_settings_sql($quizid) {
        return [
            'schoolyearenabled, examid',
            'LEFT JOIN {quizaccess_schoolyear} schoolyear ON schoolyear.quizid = quiz.id',
            [],
        ];
    }

    public static function delete_settings($quiz) {
        self::delete_exam($quiz);
    }

    public static function save_settings($quiz) {
        global $DB;
        $exists = $DB->record_exists(self::PLUGIN_NAME, ['quizid' => $quiz->id]);
        $empty = empty($quiz->schoolyearenabled);

        if (!$exists && !$empty) {
            try {
                self::create_exam($quiz);
            } catch (Exception $e) {
                \core\notification::error('Failed to create Schoolyear exam. '.$e->getMessage());
            }
            return;
        }

        if ($exists && $empty) {
            self::delete_exam($quiz);
            return;
        }
    }

    public static function create_exam($quiz) {
        global $CFG;
        $root = $CFG->wwwroot;
        $url = parse_url($root);
        $protocol = $url['scheme'];
        $hostname = $url['host'];

        $elementid = \core\uuid::generate();
        $json = json_encode([
            'display_name' => $quiz->name,
            'start_time' => gmdate('Y-m-d\TH:i:s\Z', $quiz->timeopen),
            'end_time' => gmdate('Y-m-d\TH:i:s\Z', $quiz->timeclose),
            'expected_workspaces' => null,
            'workspace' => [
                'vault' => [
                    'content' => [
                        'elements' => [
                            \core\uuid::generate() => [
                                'type' => 'web_page_regex',
                                'url_regex' => [
                                    'protocol' => $protocol,
                                    'hostname' => $hostname,
                                    'pathname' => '*/login/index.php',
                                    'search_params' => [
                                        'noredirect' => '1',
                                        'syc' => '*',
                                        'syr' => '*',
                                    ],
                                ],
                            ],
                            \core\uuid::generate() => [
                                'type' => 'web_page_regex',
                                'url_regex' => [
                                    'protocol' => $protocol,
                                    'hostname' => $hostname,
                                    'pathname' => '*/mod/quiz/view.php',
                                    'search_params' => [
                                        'id' => strval($quiz->coursemodule),
                                    ],
                                ],
                            ],
                            \core\uuid::generate() => [
                                'type' => 'web_page_regex',
                                'url_regex' => [
                                    'protocol' => $protocol,
                                    'hostname' => $hostname,
                                    'pathname' => '*/mod/quiz/attempt.php',
                                    'search_params' => [
                                        'attempt' => '*',
                                        'cmid' => strval($quiz->coursemodule),
                                    ],
                                ],
                            ],
                            \core\uuid::generate() => [
                                'type' => 'web_page_regex',
                                'url_regex' => [
                                    'protocol' => $protocol,
                                    'hostname' => $hostname,
                                    'pathname' => '*/mod/quiz/summary.php',
                                    'search_params' => [
                                        'attempt' => '*',
                                        'cmid' => strval($quiz->coursemodule),
                                    ],
                                ],
                            ],
                            \core\uuid::generate() => [
                                'type' => 'web_page_regex',
                                'url_regex' => [
                                    'protocol' => $protocol,
                                    'hostname' => $hostname,
                                    'pathname' => '*/mod/quiz/startattempt.php',
                                ],
                            ],
                            \core\uuid::generate() => [
                                'type' => 'web_page_regex',
                                'url_regex' => [
                                    'protocol' => $protocol,
                                    'hostname' => $hostname,
                                    'pathname' => '*/mod/quiz/processattempt.php',
                                    'search_params' => [
                                        'cmid' => strval($quiz->coursemodule),
                                    ],
                                ],
                            ],
                            \core\uuid::generate() => [
                                'type' => 'web_page_regex',
                                'url_regex' => [
                                    'protocol' => $protocol,
                                    'hostname' => $hostname,
                                    'pathname' => '*/mod/quiz/processattempt.php',
                                    'search_params' => [
                                        'attempt' => '*',
                                    ],
                                ],
                            ],
                            \core\uuid::generate() => [
                                'type' => 'web_page_regex',
                                'url_regex' => [
                                    'protocol' => $protocol,
                                    'hostname' => $hostname,
                                    'pathname' => '*/mod/quiz/processattempt.php',
                                ],
                            ],
                            $elementid => [
                                'type' => 'web_page_regex',
                                'url_regex' => [
                                    'protocol' => $protocol,
                                    'hostname' => $hostname,
                                    'pathname' => '*/mod/quiz/review.php',
                                    'search_params' => [
                                        'attempt' => '*',
                                        'cmid' => strval($quiz->coursemodule),
                                    ],
                                ],
                            ],
                        ],
                        'exit_points' => [
                            ['element_id' => $elementid],
                        ],
                    ],
                ],
            ],
        ], JSON_UNESCAPED_SLASHES);

        $result = self::api_request("POST", "/v2/exam", $json);

        if ($result) {
            global $DB;
            $record = new stdClass();
            $record->quizid = $quiz->id;
            $record->schoolyearenabled = 1;
            $record->examid = $result->id;
            $DB->insert_record(self::PLUGIN_NAME, $record);
        } else {
            throw new Exception('Exam ID is not present.');
        }
    }

    public static function update_exam($quiz) {
        $record = quiz_settings::get_record(['quizid' => $quiz->id]);
        $examid = $record->get('examid');

        $json = json_encode([
            'display_name' => $quiz->name,
            'start_time' => gmdate('Y-m-d\TH:i:s\Z', $quiz->timeopen),
            'end_time' => gmdate('Y-m-d\TH:i:s\Z', $quiz->timeclose),
            'expected_workspaces' => null,
        ]);

        self::api_request('PATCH', "/v2/exam/$examid", $json, 'application/merge-patch+json');
    }

    public static function delete_exam($quiz) {
        $record = quiz_settings::get_record(['quizid' => $quiz->id]);
        $examid = $record->get('examid');

        global $DB;
        $DB->delete_records(self::PLUGIN_NAME, ['quizid' => $quiz->id]);

        self::api_request('PUT', "/v2/exam/$examid/archive");
    }

    public static function api_request(string $method, string $path, string $data = '', $contenttype = 'application/json') {
        $apibaseaddress = get_config(self::PLUGIN_NAME, 'apibaseaddress');
        $apikey = get_config(self::PLUGIN_NAME, 'apikey');

        $options = [
            'CURLOPT_HTTP_VERSION' => 'CURL_HTTP_VERSION_1_1',
            'CURLOPT_ENCODING' => "",
            'CURLOPT_HTTPHEADER' => [
                "Content-Type: " . $contenttype,
                "X-Sy-Api: " . $apikey,
            ],
        ];

        $curl = new curl();
        $res = $curl->post($apibaseaddress . $path, $data, $options);

        if ($curl->get_errno()) {
            throw new Exception('An error occurred while invoking the Schoolyear API.');
        }

        $statuscode = $curl->get_info()['http_code'];
        $json = json_decode($res);

        if ($statuscode >= 400) {
            $msg = 'Schoolyear API error (status: '.$statuscode;

            if (!is_null($res) && is_string($res)) {

                if (!is_null($json->message)) {
                    $msg .= ', message: '.$json->message;
                }
                if (!is_null($json->reason)) {
                    $msg .= ', reason: '.$json->reason;
                }
            }

            $msg .= ')';
            throw new Exception($msg);
        }

        return $json;
    }
}

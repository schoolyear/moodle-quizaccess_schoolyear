<?php

use quizaccess_schoolyear\quiz_settings;
defined('MOODLE_INTERNAL') || die();
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
            return array($result);
        }
        else if ($result) {
            global $PAGE;
            $PAGE->set_pagelayout('secure');
            return false;
        }

        $result = self::create_workspace($this->quiz->examid, $this->quiz->cmid);
        return array(
            get_string('requiresschoolyear', 'quizaccess_schoolyear'),
            $result);
    }

    public static function validate_signature() {
        if (isset($_SERVER[self::X_SY_SIGNATURE_HEADER])) {
            $json = json_encode(array('x_sy_signature' => trim($_SERVER[self::X_SY_SIGNATURE_HEADER])));
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
        $api_key = get_config(self::PLUGIN_NAME, 'apikey');
        $key = substr(hash('sha256', $api_key, true), 0, 32);
        $cipher = 'aes-256-gcm';
        $iv_len = openssl_cipher_iv_length($cipher);
        $tag_length = 16;
        $iv = openssl_random_pseudo_bytes($iv_len);
        $tag = "";
        $ciphertext = openssl_encrypt($input, $cipher, $key, OPENSSL_RAW_DATA, $iv, $tag, "", $tag_length);
        return base64_encode($iv.$ciphertext.$tag);
    }

    public static function create_workspace($examid, $cmid) {
        global $USER, $CFG;
        $syc = rawurlencode(self::encrypt_cookie($_COOKIE['MoodleSession'.$CFG->sessioncookie]));
        $syr = urlencode("/mod/quiz/view.php?id=$cmid");

        $element_id = \core\uuid::generate();
        $json = json_encode(array(
            'personal_information' => array(
                'org_code' => $USER->idnumber,
                'first_name' => $USER->firstname,
                'last_name' => $USER->lastname
            ),
            'federated_user_id' => $USER->id,
            'vault' => array(
                'content' => array(
                    'elements' => array(
                        $element_id => array(
                            'type' => 'web_page_url',
                            'url' => array(
                                'url' => "$CFG->wwwroot?syc=$syc&syr=$syr"
                            )
                        )
                    ),
                    'entry_points' => array(
                        array('element_id' => $element_id)
                    )
                )
            )
        ), JSON_UNESCAPED_SLASHES);

        $response = self::api_request("POST", "/v2/exam/$examid/workspace", $json);

        if ($response) {
            $label = get_string('startquiz', 'quizaccess_schoolyear');
            $button = html_writer::start_tag('div', array('class' => 'singlebutton'));
            $button .= html_writer::link($response->onboarding_url, $label, ['class' => 'btn btn-primary', 'title' => $label, 'target' => '_blank']);
            $button .= html_writer::end_tag('div');
            return $button;
        } else {
            error_log('error creating workspace');
        }
    }

    public static function add_settings_form_fields(mod_quiz_mod_form $quizform, MoodleQuickForm $mform) {
        $mform->addElement('select', 'schoolyearenabled', get_string('schoolyearenabled', 'quizaccess_schoolyear'),
                array(
                    0 => get_string('schoolyeardisabledoption', 'quizaccess_schoolyear'),
                    1 => get_string('schoolyearenabledoption', 'quizaccess_schoolyear'),
                ));

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
                $btn = html_writer::start_tag('div', array('class' => 'singlebutton'));
                $btn .= html_writer::link($response->url, $label, ['class' => 'btn btn-secondary', 'title' => $label, 'target' => '_blank']);
                $btn .= html_writer::end_tag('div');
                $btngroup = array($mform->createElement('html', $btn));
                $mform->addGroup($btngroup, 'sy-settings-btn', get_string('settingswidget', 'quizaccess_schoolyear'), ' ', false);
                $mform->hideIf('sy-settings-btn', 'schoolyearenabled', 'neq', 1);
            } else {
                error_log('failed to generate settings ui link');
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
                $btn = html_writer::start_tag('div', array('class' => 'singlebutton'));
                $btn .= html_writer::link($response->url, $label, ['class' => 'btn btn-secondary', 'title' => $label, 'target' => '_blank']);
                $btn .= html_writer::end_tag('div');
                $btngroup = array($mform->createElement('html', $btn));
                $mform->addGroup($btngroup, 'sy-dashboard-btn', get_string('dashboard', 'quizaccess_schoolyear'), ' ', false);
                $mform->hideIf('sy-dashboard-btn', 'schoolyearenabled', 'neq', 1);
            } else {
                error_log('failed to generate dashboard ui link');
            }
        }
    }

    public static function add_settings_dashboard_ui_label(mod_quiz_mod_form $quizform, MoodleQuickForm $mform) {
        $record = quiz_settings::get_record(['quizid' => $quizform->get_instance()]);
        if (empty($record)) {
            $msg = get_string('savefirst', 'quizaccess_schoolyear');
            $group = array($mform->createElement('static', 'sy-btn-label', 'alert', $msg));
            $mform->addGroup($group, 'sy-label-group', '', ' ', false);
            $mform->hideIf('sy-label-group', 'schoolyearenabled', 'neq', 1);
        }
    }

    public static function validate_settings_form_fields(array $errors, array $data, $files, mod_quiz_mod_form $quizform) {
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

            if ($timeclose-$timeopen > 86400) {
                $msg = get_string('24herror', 'quizaccess_schoolyear');
                array_push($errors, $msg);
                \core\notification::error($msg);
            }
        }

        return $errors;
    }
    
    public static function get_settings_sql($quizid) {
        return array(
            'schoolyearenabled, examid',
            'LEFT JOIN {quizaccess_schoolyear} schoolyear ON schoolyear.quizid = quiz.id',
            array());
    }

    public static function delete_settings($quiz) {
        self::delete_exam($quiz);
    }
    
    public static function save_settings($quiz) {
        if (empty($quiz->schoolyearenabled)) {
            self::delete_exam($quiz);
        } else {
            global $DB;
            if (!$DB->record_exists(self::PLUGIN_NAME, array('quizid' => $quiz->id))) {
                self::create_exam($quiz);
            }
            else {
                self::update_exam($quiz);
            }
        }
    }

    public static function create_exam($quiz) {
        global $CFG;
        $root = $CFG->wwwroot;
        $url = parse_url($root);
        $protocol = $url['scheme'];
        $hostname = $url['host'];
        $pathname = $url['path'] ?? '/';

        $element_id = \core\uuid::generate();
        $json = json_encode(array(
            'display_name' => $quiz->name,
            'start_time' => gmdate('Y-m-d\TH:i:s\Z', $quiz->timeopen),
            'end_time' => gmdate('Y-m-d\TH:i:s\Z', $quiz->timeclose),
            'expected_workspaces' => null,
            'workspace' => array(
                'vault' => array(
                    'content' => array(
                        'elements' => array(
                            \core\uuid::generate() => array(
                                'type' => 'web_page_regex',
                                'url_regex' => array(
                                    'protocol' => $protocol,
                                    'hostname' => $hostname,
                                    'pathname' => $pathname,
                                    'search_params' => array(
                                        'syc' => '*',
                                        'syr' => '*'
                                    )
                                )
                            ),
                            \core\uuid::generate() => array(
                                'type' => 'web_page_regex',
                                'url_regex' => array(
                                    'protocol' => $protocol,
                                    'hostname' => $hostname,
                                    'pathname' => '*/mod/quiz/view.php',
                                    'search_params' => array(
                                        'id' => strval($quiz->coursemodule)
                                    )
                                )
                            ),
                            \core\uuid::generate() => array(
                                'type' => 'web_page_regex',
                                'url_regex' => array(
                                    'protocol' => $protocol,
                                    'hostname' => $hostname,
                                    'pathname' => '*/mod/quiz/attempt.php',
                                    'search_params' => array(
                                        'attempt' => '*',
                                        'cmid' => strval($quiz->coursemodule)
                                    )
                                )
                            ),
                            \core\uuid::generate() => array(
                                'type' => 'web_page_regex',
                                'url_regex' => array(
                                    'protocol' => $protocol,
                                    'hostname' => $hostname,
                                    'pathname' => '*/mod/quiz/summary.php',
                                    'search_params' => array(
                                        'attempt' => '*',
                                        'cmid' => strval($quiz->coursemodule)
                                    )
                                )
                            ),
                            \core\uuid::generate() => array(
                                'type' => 'web_page_regex',
                                'url_regex' => array(
                                    'protocol' => $protocol,
                                    'hostname' => $hostname,
                                    'pathname' => '*/mod/quiz/startattempt.php'
                                )
                            ),
                            \core\uuid::generate() => array(
                                'type' => 'web_page_regex',
                                'url_regex' => array(
                                    'protocol' => $protocol,
                                    'hostname' => $hostname,
                                    'pathname' => '*/mod/quiz/processattempt.php',
                                    'search_params' => array(
                                        'cmid' => strval($quiz->coursemodule)
                                    )
                                )
                            ),
                            $element_id => array(
                                'type' => 'web_page_regex',
                                'url_regex' => array(
                                    'protocol' => $protocol,
                                    'hostname' => $hostname,
                                    'pathname' => '*/mod/quiz/review.php',
                                    'search_params' => array(
                                        'attempt' => '*',
                                        'cmid' => strval($quiz->coursemodule)
                                    )
                                )
                            )
                        ),
                        'exit_points' => array(
                            array('element_id' => $element_id)
                        )
                    )
                )
            )
        ), JSON_UNESCAPED_SLASHES);

        $exam = self::api_request("POST", "/v2/exam", $json);

        if ($exam) {
            global $DB;
            $record = new stdClass();
            $record->quizid = $quiz->id;
            $record->schoolyearenabled = 1;
            $record->examid = $exam->id;
            $DB->insert_record(self::PLUGIN_NAME, $record);
        } else {
            error_log('error creating exam');
        }
    }

    public static function update_exam($quiz) {
        $record = quiz_settings::get_record(['quizid' => $quiz->id]);
        $examid = $record->get('examid');

        $json = json_encode(array(
            'display_name' => $quiz->name,
            'start_time' => gmdate('Y-m-d\TH:i:s\Z', $quiz->timeopen),
            'end_time' => gmdate('Y-m-d\TH:i:s\Z', $quiz->timeclose),
            'expected_workspaces' => null
        ));

        self::api_request('PATCH', "/v2/exam/$examid", $json, 'application/merge-patch+json');
    }

    public static function delete_exam($quiz) {
        $record = quiz_settings::get_record(['quizid' => $quiz->id]);
        $examid = $record->get('examid');

        global $DB;
        $DB->delete_records(self::PLUGIN_NAME, array('quizid' => $quiz->id));

        self::api_request('PUT', "/v2/exam/$examid/archive");
    }

    public static function api_request(string $method, string $path, string $data = '', $content_type = 'application/json') {
        $api_base_address = get_config(self::PLUGIN_NAME, 'apibaseaddress');
        $api_key = get_config(self::PLUGIN_NAME, 'apikey');

        $request_options = array(
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_URL => $api_base_address . $path,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_ENCODING => "",
            CURLOPT_POSTFIELDS => $data,
            CURLOPT_HTTPHEADER => array(
                "Content-Type: " . $content_type,
                "X-Sy-Api: " . $api_key
            ),
        );

        $curl = curl_init();
        curl_setopt_array($curl, $request_options);

        $json = curl_exec($curl);
        $err = curl_error($curl);
        curl_close($curl);

        if ($err) {
            return null;
        }

        return json_decode($json);
    }
}

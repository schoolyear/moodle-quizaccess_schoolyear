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
            'This quiz requires the Schoolyear application.',
            $result);
    }

    public static function validate_signature() {
        if (isset($_SERVER[self::X_SY_SIGNATURE_HEADER])) {
            $json = json_encode(array('x_sy_signature' => trim($_SERVER[self::X_SY_SIGNATURE_HEADER])));
            $response = self::api_request('POST', '/v2/signature/validate', $json);

            if ($response) {
                return true;
            } else {
                return 'An error occurred while verifying Schoolyear browser.';
            }
        }

        return false;
    }

    public static function encrypt_cookie(string $input) {
        $api_key = get_config(self::PLUGIN_NAME, 'apikey');
        $ciphering = 'AES-128-CTR';
        $options = 0;
        $encryption_iv = '5943261928359572';
        $encryption_key = $api_key;
        return openssl_encrypt($input, $ciphering, $encryption_key, $options, $encryption_iv);
    }

    public static function create_workspace($examid, $cmid) {
        global $USER, $CFG;
        $syc = urlencode(self::encrypt_cookie($_COOKIE['MoodleSession'.$CFG->sessioncookie]));
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
            $button = html_writer::start_tag('div', array('class' => 'singlebutton'));
            $button .= html_writer::link($response->onboarding_url, 'Start quiz', ['class' => 'btn btn-primary', 'title' => 'Start exam', 'target' => '_blank']);
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
                $btn = html_writer::start_tag('div', array('class' => 'singlebutton'));
                $btn .= html_writer::link($response->url, 'Open settings widget', ['class' => 'btn btn-secondary', 'title' => 'Go to exam settings', 'target' => '_blank']);
                $btn .= html_writer::end_tag('div');
                $btngroup = array($mform->createElement('html', $btn));
                $mform->addGroup($btngroup, 'sy-settings-btn', 'Settings widget', ' ', false);
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
                $btn = html_writer::start_tag('div', array('class' => 'singlebutton'));
                $btn .= html_writer::link($response->url, 'Open dashboard', ['class' => 'btn btn-secondary', 'title' => 'Go to exam dashboard', 'target' => '_blank']);
                $btn .= html_writer::end_tag('div');
                $btngroup = array($mform->createElement('html', $btn));
                $mform->addGroup($btngroup, 'sy-dashboard-btn', 'Dashboard', ' ', false);
                $mform->hideIf('sy-dashboard-btn', 'schoolyearenabled', 'neq', 1);
            } else {
                error_log('failed to generate dashboard ui link');
            }
        }
    }

    public static function add_settings_dashboard_ui_label(mod_quiz_mod_form $quizform, MoodleQuickForm $mform) {
        $record = quiz_settings::get_record(['quizid' => $quizform->get_instance()]);
        if (empty($record)) {
            $group = array($mform->createElement('static', 'sy-btn-label', 'alert', 'Save to access the settings and dashboard buttons for this exam.'));
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
                $msg = 'Opening and closing time must be set for a Schoolyear exam.';
                array_push($errors, $msg);
                \core\notification::error($msg);
                return $errors;
            }

            if ($timeclose-$timeopen > 86400) {
                $msg = 'A Schoolyear exam must be a maximum of 24 hours.';
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
            error_log('quiz:');
            error_log(print_r($quiz,true));
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
                                        'syt' => '*'
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

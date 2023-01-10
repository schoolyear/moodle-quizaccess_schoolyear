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
            return false;
        }

        $result = self::create_workspace($this->quiz->examid);
        return array($result);
    }

    public static function validate_signature() {
        if (isset($_SERVER[self::X_SY_SIGNATURE_HEADER])) {
            $curl = curl_init();
            curl_setopt_array($curl, [
                CURLOPT_URL => get_config('quizaccess_schoolyear', 'apibaseaddress') . "/v2/signature/validate",
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => "",
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => "POST",
                CURLOPT_POSTFIELDS => "{\"x_sy_signature\":\"" . trim($_SERVER[self::X_SY_SIGNATURE_HEADER]) . "\"}",
                CURLOPT_HTTPHEADER => [
                    "Content-Type: application/json",
                    "X-Sy-Api: " . get_config('quizaccess_schoolyear', 'apikey')
                ],
            ]);
            $response = curl_exec($curl);
            $err = curl_error($curl);
            curl_close($curl);

            if ($err) {
                return 'An error occurred while validating signature.';
            } else {
                return true;
            }
        }

        return false;
    }

    public static function create_workspace($examid) {
        global $USER;

        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => get_config('quizaccess_schoolyear', 'apibaseaddress') . "/v2/exam/" . $examid . "/workspace",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "POST",
            CURLOPT_POSTFIELDS => "{\"personal_information\":{\"org_code\":\"" . $USER->idnumber . "\",\"first_name\":\"" . $USER->firstname . "\",\"last_name\":\"" . $USER->lastname . "\"},\"federated_user_id\": \"" . $USER->idnumber . "\",\"vault\":{\"content\":{\"elements\": {\n        \"d1ea19ba-2b3b-41e7-8904-3d78e4cea066\": {\n          \"type\": \"web_page_url\",\n          \"url\": {\n            \"url\": \"http://localhost:8888/moodle401/mod/quiz/view.php?id=13\"\n          }\n        }\n      },\n      \"entry_points\": [\n        {\n          \"element_id\": \"d1ea19ba-2b3b-41e7-8904-3d78e4cea066\"\n        }\n      ]\n    }\n  }\n}",
            CURLOPT_HTTPHEADER => [
                "Content-Type: application/json",
                "X-Sy-Api: " . get_config('quizaccess_schoolyear', 'apikey')
            ],
        ]);
        $response = curl_exec($curl);
        $err = curl_error($curl);
        curl_close($curl);

        if ($err) {
            return 'An error occurred while generating Schoolyear exam link.';
        }
        else {
            $decoded_json = json_decode($response, false);
            $button = html_writer::start_tag('div', array('class' => 'singlebutton'));
            $button .= html_writer::link($decoded_json->onboarding_url, 'Start Schoolyear exam', ['class' => 'btn btn-primary', 'title' => 'Start exam']);
            $button .= html_writer::end_tag('div');
            return $button;
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
            $curl = curl_init();
            curl_setopt_array($curl, [
                CURLOPT_URL => get_config('quizaccess_schoolyear', 'apibaseaddress') . "/v2/exam/" . $record->get('examid') . "/ui/settings",
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => "",
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => "POST",
                CURLOPT_POSTFIELDS => "",
                CURLOPT_HTTPHEADER => [
                    "Content-Type: application/json",
                    "X-Sy-Api: " . get_config('quizaccess_schoolyear', 'apikey')
                ],
            ]);
            $response = curl_exec($curl);
            $err = curl_error($curl);
            curl_close($curl);
            if ($err) {
                error_log(print_r($err, true));
            } else {
                $decoded_json = json_decode($response, false);
                $btn = html_writer::start_tag('div', array('class' => 'singlebutton'));
                $btn .= html_writer::link($decoded_json->url, 'Open settings', ['class' => 'btn btn-secondary', 'title' => 'Go to exam settings']);
                $btn .= html_writer::end_tag('div');
                $btngroup=array();
                $btngroup[] =& $mform->createElement('html', $btn);
                $mform->addGroup($btngroup, 'sy-settings-btn', 'Settings UI', ' ', false);
                $mform->hideIf('sy-settings-btn', 'schoolyearenabled', 'neq', 1);
            }
        }
    }

    public static function add_dashboard_ui_button(mod_quiz_mod_form $quizform, MoodleQuickForm $mform) {
        $record = quiz_settings::get_record(['quizid' => $quizform->get_instance()]);
        if (!empty($record)) {
            $curl = curl_init();
            curl_setopt_array($curl, [
                CURLOPT_URL => get_config('quizaccess_schoolyear', 'apibaseaddress') . "/v2/exam/" . $record->get('examid') . "/ui/dashboard",
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => "",
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => "POST",
                CURLOPT_POSTFIELDS => "",
                CURLOPT_HTTPHEADER => [
                    "Content-Type: application/json",
                    "X-Sy-Api: " . get_config('quizaccess_schoolyear', 'apikey')
                ],
            ]);
            $response = curl_exec($curl);
            $err = curl_error($curl);
            curl_close($curl);
            if ($err) {
                error_log(print_r($err, true));
            } else {
                $decoded_json = json_decode($response, false);
                $btn = html_writer::start_tag('div', array('class' => 'singlebutton'));
                $btn .= html_writer::link($decoded_json->url, 'Open dashboard', ['class' => 'btn btn-secondary', 'title' => 'Go to exam dashboard']);
                $btn .= html_writer::end_tag('div');
                $btngroup = array();
                $btngroup[] =& $mform->createElement('html', $btn);
                $mform->addGroup($btngroup, 'sy-dashboard-btn', 'Dashboard UI', ' ', false);
                $mform->hideIf('sy-dashboard-btn', 'schoolyearenabled', 'neq', 1);
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
        $element_id = '26ab94c0-81aa-46e9-907f-80a97a2157dc';
        $json = json_encode(array(
            'display_name' => $quiz->name,
            'start_time' => gmdate('Y-m-d\TH:i:s\Z', $quiz->timeopen),
            'end_time' => gmdate('Y-m-d\TH:i:s\Z', $quiz->timeclose),
            'expected_workspaces' => 20,
            'workspace' => array(
                'vault' => array(
                    'content' => array(
                        'elements' => array(
                            '7cea7a20-5b8a-4d75-bd17-096492e5a460' => array(
                                'type' => 'web_page_entire_domain',
                                'url_entire_domain' => array(
                                    'url' => 'http://localhost:8888/',
                                    'require_exact_port' => false
                                )
                            ),
                            $element_id => array(
                                'type' => 'web_page_url',
                                'url' => array(
                                    'url' => 'http://localhost:8888/moodle401/mod/quiz/view.php?id=13',
                                )
                            )
                        ),
                        'exit_points' => array(
                            array(array('element_id' => $element_id))
                        )
                    )
                )
            )
        ));

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
        $json = json_encode(array(
            'display_name' => $quiz->name,
            'start_time' => gmdate('Y-m-d\TH:i:s\Z', $quiz->timeopen),
            'end_time' => gmdate('Y-m-d\TH:i:s\Z', $quiz->timeclose),
            'expected_workspaces' => 20,
        ));
        error_log('json:');
        error_log(print_r($json, true));
        self::api_request('PATCH', '/v2/exam/' . $quiz->examid, $json, 'application/merge-patch+json');
    }

    public static function delete_exam($quiz) {
        global $DB;
        $DB->delete_records(self::PLUGIN_NAME, array('quizid' => $quiz->id));

        $response = self::api_request('PUT', '/v2/exam/examId/archive');
        if ($response) {
            error_log('deleted exam');
        } else {
            error_log('error deleting exam');
        }
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

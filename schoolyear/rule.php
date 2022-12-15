<?php

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/quiz/accessrule/accessrulebase.php');

class quizaccess_schoolyear extends quiz_access_rule_base {
    
    private const X_SY_SIGNATURE_HEADER = 'HTTP_X_SY_SIGNATURE';

    public static function make(quiz $quizobj, $timenow, $canignoretimelimits) {
        if (empty($quizobj->get_quiz()->schoolyearenabled)) {
            return null;
        }

        return new self($quizobj, $timenow);
    }

    public function description() {
        // error_log(print_r($this->quiz, true));
        return array('description');
    }

    public static function add_settings_form_fields(mod_quiz_mod_form $quizform, MoodleQuickForm $mform) {
        $mform->addElement('select', 'schoolyearenabled', get_string('schoolyearenabled', 'quizaccess_schoolyear'),
                array(
                    0 => get_string('schoolyeardisabledoption', 'quizaccess_schoolyear'),
                    1 => get_string('schoolyearenabledoption', 'quizaccess_schoolyear'),
                ));
        $mform->addHelpButton('schoolyearenabled', 'schoolyearenabled', 'quizaccess_schoolyear');
    }
    
    public static function get_settings_sql($quizid) {
        return array(
            'schoolyearenabled, examid',
            'LEFT JOIN {quizaccess_schoolyear} schoolyear ON schoolyear.quizid = quiz.id',
            array());
    }
    
    public static function save_settings($quiz) {
        error_log("save_settings(): enter");

        global $DB;
        if (empty($quiz->schoolyearenabled)) {
            $DB->delete_records('quizaccess_schoolyear', array('quizid' => $quiz->id));
        } else {
            if (!$DB->record_exists('quizaccess_schoolyear', array('quizid' => $quiz->id))) {
                $curl = curl_init();
                curl_setopt_array($curl, [
                    CURLOPT_URL => get_config('quizaccess_schoolyear', 'apibaseaddress') . "/v2/exam",
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_ENCODING => "",
                    CURLOPT_MAXREDIRS => 10,
                    CURLOPT_TIMEOUT => 30,
                    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                    CURLOPT_CUSTOMREQUEST => "POST",
                    CURLOPT_POSTFIELDS => "{\"display_name\":\"" . $quiz->name . "\",\"start_time\":\"" . gmdate("Y-m-d\TH:i:s\Z", $quiz->timeopen) . "\",\"end_time\":\"" . gmdate("Y-m-d\TH:i:s\Z", $quiz->timeclose) . "\",\"expected_workspaces\":20,\"workspace\":{\"vault\":{\"content\":{\"elements\":{\"7cea7a20-5b8a-4d75-bd17-096492e5a460\":{\"type\":\"web_page_entire_domain\",\"url_entire_domain\":{\"url\":\"http://localhost:8888/\",\"require_exact_port\":false}},\"26ab94c0-81aa-46e9-907f-80a97a2157dc\":{\"type\":\"web_page_url\",\"url\":{\"url\":\"http://localhost:8888/moodle401/mod/quiz/view.php?id=13\"}}},\"exit_points\":[{\"element_id\":\"26ab94c0-81aa-46e9-907f-80a97a2157dc\"}]}}}}",
                    CURLOPT_HTTPHEADER => [
                        "Content-Type: application/json",
                        "X-Sy-Api: " . get_config('quizaccess_schoolyear', 'apikey')
                    ],
                ]);
                
                $response = curl_exec($curl);
                $err = curl_error($curl);
                
                curl_close($curl);
                
                if ($err) {
                    error_log("cURL Error #:" . $err);
                } else {
                    error_log($response);
                    $exam = json_decode($response);
                    
                    $record = new stdClass();
                    $record->quizid = $quiz->id;
                    $record->schoolyearenabled = 1;
                    $record->examid = $exam->id;
                    $DB->insert_record('quizaccess_schoolyear', $record);
                }
            }
        }

        error_log("save_settings(): exit");
    }
    
    public static function delete_settings($quiz) {
        global $DB;
        $DB->delete_records('quizaccess_schoolyear', array('quizid' => $quiz->id));
    }

    public function prevent_access() {
        global $USER;

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
                error_log("cURL Error #:" . $err);
            } else {
                error_log($response);
                return false;
            }
        }
        
        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => get_config('quizaccess_schoolyear', 'apibaseaddress') . "/v2/exam/" . $this->quiz->examid . "/workspace",
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
            error_log("cURL Error #:" . $err);
        }

        $decoded_json = json_decode($response, false);

        $buttonlink = html_writer::start_tag('div', array('class' => 'singlebutton'));
        $buttonlink .= html_writer::link($decoded_json->onboarding_url, 'Open Schoolyear browser',
            ['class' => 'btn btn-primary', 'title' => 'Open Schoolyear browser']);
        $buttonlink .= html_writer::end_tag('div');

        return array('This exam requires the Schoolyear browser.', $buttonlink);
    }
}

<?php

defined('MOODLE_INTERNAL') || die();

function local_schoolyear_login_extend_navigation(global_navigation $nav) {
    global $PAGE, $CFG;
    if ($PAGE->pagetype == 'site-index') {
        $query_string = $_SERVER['QUERY_STRING'];
        parse_str($query_string, $query_params);
        error_log(print_r($query_params, true));

        if (!array_key_exists('syc', $query_params)) {
            return;
        }

        if (!array_key_exists('syr', $query_params)) {
            return;
        }

        $encoded_cookie = $query_params['syc'];
        error_log('encoded_cookie:'.$encoded_cookie);
        $encrypted_cookie = urldecode($encoded_cookie);
        error_log('encrypted_cookie:'.$encrypted_cookie);
        $decrypted_cookie = decrypt_cookie($encrypted_cookie);
        error_log('decrypted_cookie:'.$decrypted_cookie);
        setcookie('MoodleSession'.$CFG->sessioncookie, $decrypted_cookie, 0, $CFG->sessioncookiepath);

        $encoded_url = $query_params['syr'];
        $decoded_url = urldecode($encoded_url);
        header('Location: '.$CFG->sessioncookiepath.ltrim($decoded_url, '/'));
        die();
    }
}

function decrypt_cookie(string $input) {
    $api_key = get_config('quizaccess_schoolyear', 'apikey');
    $ciphering = 'AES-128-CTR';
    $options = 0;
    $decryption_iv = '1234567891011121';
    $decryption_key = $api_key;
    return openssl_decrypt($input, $ciphering, $decryption_key, $options, $decryption_iv);
}
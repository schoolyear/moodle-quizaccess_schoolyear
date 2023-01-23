<?php

defined('MOODLE_INTERNAL') || die();

function local_schoolyear_login_extend_navigation(global_navigation $nav) {
    global $PAGE, $CFG;
    if ($PAGE->pagetype == 'site-index') {
        $query_string = $_SERVER['QUERY_STRING'];
        parse_str($query_string, $query_params);

        if (!array_key_exists('syc', $query_params) || !array_key_exists('syr', $query_params)) {
            return;
        }

        $encoded_cookie = $query_params['syc'];
        $encrypted_cookie = urldecode($encoded_cookie);
        $decrypted_cookie = decrypt_cookie($encrypted_cookie);
        setcookie('MoodleSession'.$CFG->sessioncookie, $decrypted_cookie, 0, $CFG->sessioncookiepath);

        $encoded_url = $query_params['syr'];
        $decoded_url = urldecode($encoded_url);
        header('Location: '.$CFG->sessioncookiepath.ltrim($decoded_url, '/'));
        die();
    }
}

function decrypt_cookie(string $input) {
    $api_key = get_config('quizaccess_schoolyear', 'apikey');
    $encrypted = base64_decode($input);
    $key = substr(hash('sha256', $api_key, true), 0, 32);
    $cipher = 'aes-256-gcm';
    $iv_len = openssl_cipher_iv_length($cipher);
    $tag_length = 16;
    $iv = substr($encrypted, 0, $iv_len);
    $ciphertext = substr($encrypted, $iv_len, -$tag_length);
    $tag = substr($encrypted, -$tag_length);
    return openssl_decrypt($ciphertext, $cipher, $key, OPENSSL_RAW_DATA, $iv, $tag);
}
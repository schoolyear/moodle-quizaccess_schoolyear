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
 * Implementaton of the quizaccess_schoolyear plugin.
 *
 * @package    quizaccess_schoolyear
 * @copyright  2023 Schoolyear B.V.
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

function quizaccess_schoolyear_after_config() {
    global $PAGE, $CFG;
    if ($PAGE->pagetype == 'login-index') {
        $query_string = $_SERVER['QUERY_STRING'];
        parse_str($query_string, $query_params);

        if (!array_key_exists('syc', $query_params) || !array_key_exists('syr', $query_params)) {
            return;
        }

        $encoded_cookie = $query_params['syc'];
        $encrypted_cookie = rawurldecode($encoded_cookie);
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
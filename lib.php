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

function quizaccess_schoolyear_after_config() {
    global $PAGE, $CFG;
    if ($PAGE->pagetype == 'login-index') {
        $syc = optional_param('syc', '', PARAM_TEXT);
        if (empty($syc)) {
            return;
        }

        $syr = optional_param('syr', '', PARAM_TEXT);
        if (empty($syr)) {
            return;
        }

        $encryptedCookie = rawurldecode($syc);
        $decryptedCookie = decrypt_cookie($encryptedCookie);
        setcookie('MoodleSession'.$CFG->sessioncookie, $decryptedCookie, 0, $CFG->sessioncookiepath);

        $decodedUrl = urldecode($syr);
        header('Location: '.$CFG->sessioncookiepath.ltrim($decodedUrl, '/'));
        die();
    }
}

function decrypt_cookie(string $input) {
    $apiKey = get_config('quizaccess_schoolyear', 'apikey');
    $encrypted = base64_decode($input);
    $key = substr(hash('sha256', $apiKey, true), 0, 32);
    $cipher = 'aes-256-gcm';
    $ivLen = openssl_cipher_iv_length($cipher);
    $tagLen = 16;
    $iv = substr($encrypted, 0, $ivLen);
    $ciphertext = substr($encrypted, $ivLen, -$tagLen);
    $tag = substr($encrypted, -$tagLen);
    return openssl_decrypt($ciphertext, $cipher, $key, OPENSSL_RAW_DATA, $iv, $tag);
}

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

/**
 * Hook to capture the Schoolyear cookie and redirect params.
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

        $encryptedcookie = rawurldecode($syc);
        $decryptedcookie = decrypt_cookie($encryptedcookie);
        setcookie('MoodleSession'.$CFG->sessioncookie, $decryptedcookie, 0, $CFG->sessioncookiepath);

        $decodedurl = urldecode($syr);
        header('Location: '.ltrim($CFG->wwwroot, '/').$decodedurl);
        die();
    }
}

/**
 * Decrypts the given string using the Schoolyear API key as decryption key.
 */
function decrypt_cookie(string $input) {
    $apikey = get_config('quizaccess_schoolyear', 'apikey');
    $encrypted = base64_decode($input);
    $key = substr(hash('sha256', $apikey, true), 0, 32);
    $cipher = 'aes-256-gcm';
    $ivlen = openssl_cipher_iv_length($cipher);
    $taglen = 16;
    $iv = substr($encrypted, 0, $ivlen);
    $ciphertext = substr($encrypted, $ivlen, -$taglen);
    $tag = substr($encrypted, -$taglen);
    return openssl_decrypt($ciphertext, $cipher, $key, OPENSSL_RAW_DATA, $iv, $tag);
}

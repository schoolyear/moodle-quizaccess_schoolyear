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

namespace quizaccess_schoolyear;

use quizaccess_schoolyear;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->dirroot . '/mod/quiz/locallib.php');
require_once($CFG->dirroot . '/mod/quiz/mod_form.php');
require_once($CFG->dirroot . '/mod/quiz/accessrule/schoolyear/rule.php');

/**
 * PHPUnit tests for plugin rule class.
 *
 * @package    quizaccess_schoolyear
 * @copyright  2026 Schoolyear B.V.
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \quizaccess_schoolyear
 */
final class rule_test extends \advanced_testcase {
    /** Mocked curl response that only an API call made by the code under test would consume. */
    private const SENTINEL = 'sentinel';

    /** Entry token of the tests. */
    private const TOKEN = 'entrytoken';

    /** Schoolyear workspace ID of the tests. */
    private const WORKSPACE_ID = '3f1c9a52-6a1e-4c2b-9b7e-2d4f8e0a1b6c';

    /** Signature header of a request from the workspace of the tests. */
    private const HEADER = 'signature.' . self::WORKSPACE_ID . '.totp';

    /** API response body of a created workspace. */
    private const WORKSPACE_RESPONSE = [
        'workspace_id' => self::WORKSPACE_ID,
        'onboarding_url' => 'https://app.schoolyear.app/onboarding',
    ];

    /** @var \stdClass $course Test course to contain quiz. */
    private $course;

    /** @var \stdClass $quiz A test quiz with a Schoolyear exam. */
    private $quiz;

    /**
     * Called before every test.
     */
    public function setUp(): void {
        global $DB;

        parent::setUp();

        $this->resetAfterTest();
        set_config('apibaseaddress', 'https://api.schoolyear.test', 'quizaccess_schoolyear');
        set_config('apikey', 'apikey', 'quizaccess_schoolyear');

        $this->course = $this->getDataGenerator()->create_course();
        $this->quiz = $this->getDataGenerator()->create_module('quiz', ['course' => $this->course->id]);
        $DB->insert_record('quizaccess_schoolyear', [
            'quizid' => $this->quiz->id,
            'schoolyearenabled' => 1,
            'examid' => 'exam',
        ]);
    }

    /**
     * Called after every test.
     */
    public function tearDown(): void {
        unset($_GET['sesskey']);
        unset($_SERVER['HTTP_X_SY_SIGNATURE']);
        parent::tearDown();
    }

    /**
     * Assert that the code under test made no API call, so the sentinel response is still queued.
     */
    private function assert_no_api_call(): void {
        $this->assertSame(self::SENTINEL, (new \curl())->get('https://api.schoolyear.test/'));
    }

    /**
     * Log in a new user enrolled in the test course and pass their sesskey in the request.
     *
     * @param int|string $role Role ID or shortname to enrol the user with.
     */
    private function log_in_enrolled_user($role): void {
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $this->course->id, $role);
        $this->setUser($user);
        $_GET['sesskey'] = sesskey();
    }

    /**
     * Store the entry token of the tests for a user of the test quiz.
     *
     * @param int $userid ID of the user the token logs in.
     * @param int $timeexpires Time the token expires.
     */
    private function create_token(int $userid, int $timeexpires): void {
        $token = new token(0, (object) [
            'tokenhash' => hash('sha256', self::TOKEN),
            'userid' => $userid,
            'cmid' => $this->quiz->cmid,
            'workspaceid' => self::WORKSPACE_ID,
            'timeexpires' => $timeexpires,
        ]);
        $token->create();
    }

    /**
     * Assert that one entry token is stored, for the current user and the test quiz, and get it.
     *
     * @return \stdClass The token record.
     */
    private function assert_one_token_stored(): \stdClass {
        global $DB, $USER;

        $records = $DB->get_records('quizaccess_schoolyear_token');
        $this->assertCount(1, $records);
        $record = reset($records);
        $this->assertEquals($USER->id, $record->userid);
        $this->assertEquals($this->quiz->cmid, $record->cmid);
        $this->assertSame(self::WORKSPACE_ID, $record->workspaceid);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $record->tokenhash);
        return $record;
    }

    /**
     * Log in with the entry token of the tests, from a request with the signature header of its workspace.
     *
     * @return \moodle_url The URL returned by the method under test.
     */
    private function log_in_with_entry_token(): \moodle_url {
        $_SERVER['HTTP_X_SY_SIGNATURE'] = self::HEADER;

        \curl::mock_response('{"ok":true}');
        // Hide the session header errors of complete_user_login().
        return @quizaccess_schoolyear::log_in_with_entry_token(self::TOKEN);
    }

    /**
     * Combine each method that returns a teacher UI URL with each case of a URL provider.
     *
     * @param array $urlcases Cases of a URL provider.
     * @return array
     */
    private static function with_ui_url_methods(array $urlcases): array {
        $cases = [];
        foreach (self::ui_url_method_provider() as $methodname => $methodargs) {
            foreach ($urlcases as $urlname => $urlargs) {
                $cases["$methodname, $urlname"] = array_merge($methodargs, $urlargs);
            }
        }
        return $cases;
    }

    /**
     * Provider of signature validation response bodies and whether each one means the signature is valid.
     *
     * @return array
     */
    public static function signature_response_provider(): array {
        return [
            'ok true' => ['{"ok":true}', true],
            'ok false' => ['{"ok":false}', false],
            'no ok field' => ['{}', false],
        ];
    }

    /**
     * Provider of the methods that return a teacher UI URL.
     *
     * @return array
     */
    public static function ui_url_method_provider(): array {
        return [
            'settings' => ['get_settings_ui_url'],
            'dashboard' => ['get_dashboard_ui_url'],
        ];
    }

    /**
     * Provider of URLs the plugin accepts from the API.
     *
     * @return array
     */
    public static function valid_url_provider(): array {
        return [
            'subdomain' => ['https://dashboard.exams.schoolyear.app/x'],
            'upper case host' => ['https://DASHBOARD.EXAMS.SCHOOLYEAR.APP/x'],
            'domain' => ['https://schoolyear.app/x'],
        ];
    }

    /**
     * Provider of URLs the plugin rejects from the API.
     *
     * @return array
     */
    public static function invalid_url_provider(): array {
        return [
            'http' => ['http://dashboard.exams.schoolyear.app/x'],
            'other domain' => ['https://evil.test/x'],
            'domain name as suffix' => ['https://evilschoolyear.app/x'],
            'domain as subdomain of other domain' => ['https://schoolyear.app.evil.test/x'],
            'domain as user info' => ['https://dashboard.exams.schoolyear.app@evil.test/x'],
            'other domain before backslash' => ['https://evil.test\\@dashboard.exams.schoolyear.app/x'],
            'javascript' => ['javascript:alert(1)'],
            'protocol-relative' => ['//dashboard.exams.schoolyear.app/x'],
            'path' => ['/local/path'],
        ];
    }

    /**
     * Provider of the methods that return a teacher UI URL, with each URL the plugin accepts.
     *
     * @return array
     */
    public static function ui_url_method_valid_url_provider(): array {
        return self::with_ui_url_methods(self::valid_url_provider());
    }

    /**
     * Provider of the methods that return a teacher UI URL, with each URL the plugin rejects.
     *
     * @return array
     */
    public static function ui_url_method_invalid_url_provider(): array {
        return self::with_ui_url_methods(self::invalid_url_provider());
    }

    /**
     * Provider of the methods behind open.php and start.php, with their arguments after the course module ID.
     *
     * @return array
     */
    public static function script_method_provider(): array {
        return [
            'open.php' => ['get_teacher_ui_url', ['settings']],
            'start.php' => ['get_student_onboarding_url', []],
        ];
    }

    /**
     * Provider of the targets open.php accepts.
     *
     * @return array
     */
    public static function target_provider(): array {
        return [
            'settings' => ['settings'],
            'dashboard' => ['dashboard'],
        ];
    }

    /**
     * Provider of workspace response bodies that miss a field the plugin needs.
     *
     * @return array
     */
    public static function incomplete_workspace_response_provider(): array {
        return [
            'no onboarding URL' => [['workspace_id' => self::WORKSPACE_ID]],
            'no workspace ID' => [['onboarding_url' => 'https://app.schoolyear.app/onboarding']],
        ];
    }

    /**
     * Provider of quiz close times, user override close times and the expiry time of the entry token.
     *
     * @return array
     */
    public static function close_time_provider(): array {
        return [
            'quiz close time' => [1900000000, null, 1900000000 + HOURSECS],
            'user override of the close time' => [1900000000, 1900086400, 1900086400 + HOURSECS],
        ];
    }

    /**
     * Provider of entry requests that are denied, and the API response body if the request reaches the API.
     *
     * @return array
     */
    public static function entry_denial_provider(): array {
        return [
            'no header' => [null, self::TOKEN, HOURSECS, null],
            'malformed header' => ['signature.' . self::WORKSPACE_ID, self::TOKEN, HOURSECS, null],
            'unknown token' => [self::HEADER, 'unknowntoken', HOURSECS, null],
            'expired token' => [self::HEADER, self::TOKEN, -1, null],
            'other workspace' => ['signature.00000000-0000-0000-0000-000000000000.totp', self::TOKEN, HOURSECS, null],
            'signature rejected' => [self::HEADER, self::TOKEN, HOURSECS, '{"ok":false}'],
        ];
    }

    /**
     * Provider of whether MFA is ready.
     *
     * @return array
     */
    public static function mfa_provider(): array {
        return [
            'MFA ready' => [true],
            'MFA not ready' => [false],
        ];
    }

    /**
     * Test the start button outside the Schoolyear browser links to start.php without calling the API.
     */
    public function test_prevent_access_links_to_start_script(): void {
        unset($_SERVER['HTTP_X_SY_SIGNATURE']);
        $this->setUser($this->getDataGenerator()->create_user());

        \curl::mock_response(self::SENTINEL);
        $rule = quizaccess_schoolyear::make(\mod_quiz\quiz_settings::create($this->quiz->id), time(), false);
        $result = $rule->prevent_access();
        $this->assert_no_api_call();

        $url = new \moodle_url('/mod/quiz/accessrule/schoolyear/start.php', [
            'cmid' => $this->quiz->cmid,
            'sesskey' => sesskey(),
        ]);
        $this->assertCount(2, $result);
        $this->assertStringContainsString('href="' . $url->out() . '"', $result[1]);
    }

    /**
     * Test access is allowed only when the API accepts the signature.
     *
     * @param string $body Response body of the API.
     * @param bool $valid Whether the signature is valid.
     * @dataProvider signature_response_provider
     */
    public function test_prevent_access_checks_signature(string $body, bool $valid): void {
        $_SERVER['HTTP_X_SY_SIGNATURE'] = 'signature';

        \curl::mock_response($body);
        $rule = quizaccess_schoolyear::make(\mod_quiz\quiz_settings::create($this->quiz->id), time(), false);
        $result = $rule->prevent_access();

        $expected = $valid ? false : [get_string('errorverifying', 'quizaccess_schoolyear')];
        $this->assertSame($expected, $result);
    }

    /**
     * Test validating the signature without the header returns false without calling the API.
     */
    public function test_validate_signature_returns_false_without_header(): void {
        unset($_SERVER['HTTP_X_SY_SIGNATURE']);

        \curl::mock_response(self::SENTINEL);
        $result = quizaccess_schoolyear::validate_signature();
        $this->assert_no_api_call();

        $this->assertFalse($result);
    }

    /**
     * Test validating the signature accepts only an API response with ok set to true.
     *
     * @param string $body Response body of the API.
     * @param bool $valid Whether the signature is valid.
     * @dataProvider signature_response_provider
     */
    public function test_validate_signature_checks_ok_field(string $body, bool $valid): void {
        $_SERVER['HTTP_X_SY_SIGNATURE'] = 'signature';

        \curl::mock_response($body);
        $result = quizaccess_schoolyear::validate_signature();

        $expected = $valid ? true : get_string('errorverifying', 'quizaccess_schoolyear');
        $this->assertSame($expected, $result);
    }

    /**
     * Test creating a workspace returns the API response.
     *
     * @param string $url Onboarding URL in the API response.
     * @dataProvider valid_url_provider
     */
    public function test_create_workspace_returns_response(string $url): void {
        $this->setUser($this->getDataGenerator()->create_user());

        $body = ['onboarding_url' => $url] + self::WORKSPACE_RESPONSE;
        \curl::mock_response(json_encode($body));
        $entryurl = new \moodle_url('/mod/quiz/accessrule/schoolyear/enter.php', ['token' => self::TOKEN]);
        $response = quizaccess_schoolyear::create_workspace('exam', $entryurl, '');

        $this->assertEquals((object) $body, $response);
    }

    /**
     * Test creating a workspace throws when the API response misses the onboarding URL or the workspace ID.
     *
     * @param array $body Response body of the API.
     * @dataProvider incomplete_workspace_response_provider
     */
    public function test_create_workspace_throws_on_incomplete_response(array $body): void {
        $this->setUser($this->getDataGenerator()->create_user());

        \curl::mock_response(json_encode($body));
        $entryurl = new \moodle_url('/mod/quiz/accessrule/schoolyear/enter.php', ['token' => self::TOKEN]);

        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessage(get_string('errornourl', 'quizaccess_schoolyear'));
        quizaccess_schoolyear::create_workspace('exam', $entryurl, '');
    }

    /**
     * Test creating a workspace throws when the onboarding URL in the API response is not an HTTPS Schoolyear URL.
     *
     * @param string $url Onboarding URL in the API response.
     * @dataProvider invalid_url_provider
     */
    public function test_create_workspace_rejects_invalid_url(string $url): void {
        $this->setUser($this->getDataGenerator()->create_user());

        \curl::mock_response(json_encode(['onboarding_url' => $url] + self::WORKSPACE_RESPONSE));
        $entryurl = new \moodle_url('/mod/quiz/accessrule/schoolyear/enter.php', ['token' => self::TOKEN]);

        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessage(get_string('errorinvalidurl', 'quizaccess_schoolyear'));
        quizaccess_schoolyear::create_workspace('exam', $entryurl, '');
    }

    /**
     * Test a teacher UI URL method returns the URL from the API response.
     *
     * @param string $method Name of the method under test.
     * @param string $url URL in the API response.
     * @dataProvider ui_url_method_valid_url_provider
     */
    public function test_get_ui_url_returns_url(string $method, string $url): void {
        \curl::mock_response(json_encode(['url' => $url]));

        $this->assertSame($url, quizaccess_schoolyear::$method('exam'));
    }

    /**
     * Test a teacher UI URL method throws when the API response has no URL.
     *
     * @param string $method Name of the method under test.
     * @dataProvider ui_url_method_provider
     */
    public function test_get_ui_url_throws_without_url(string $method): void {
        \curl::mock_response('{}');

        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessage(get_string('errornourl', 'quizaccess_schoolyear'));
        quizaccess_schoolyear::$method('exam');
    }

    /**
     * Test a teacher UI URL method throws when the URL in the API response is not an HTTPS Schoolyear URL.
     *
     * @param string $method Name of the method under test.
     * @param string $url URL in the API response.
     * @dataProvider ui_url_method_invalid_url_provider
     */
    public function test_get_ui_url_rejects_invalid_url(string $method, string $url): void {
        \curl::mock_response(json_encode(['url' => $url]));

        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessage(get_string('errorinvalidurl', 'quizaccess_schoolyear'));
        quizaccess_schoolyear::$method('exam');
    }

    /**
     * Test the quiz settings form links both buttons to open.php without calling the API.
     */
    public function test_settings_form_links_to_open_script(): void {
        $this->setAdminUser();

        $quizform = $this->createMock('mod_quiz_mod_form');
        $quizform->method('get_instance')->willReturn($this->quiz->id);
        $quizform->method('get_coursemodule')->willReturn(get_coursemodule_from_id('quiz', $this->quiz->cmid));
        $mform = new \MoodleQuickForm('test', 'post', '');

        \curl::mock_response(self::SENTINEL);
        quizaccess_schoolyear::add_settings_form_fields($quizform, $mform);
        $this->assert_no_api_call();

        $groups = ['settings' => 'sy-settings-btn', 'dashboard' => 'sy-dashboard-btn'];
        foreach ($groups as $target => $group) {
            $url = new \moodle_url('/mod/quiz/accessrule/schoolyear/open.php', [
                'cmid' => $this->quiz->cmid,
                'target' => $target,
                'sesskey' => sesskey(),
            ]);
            $html = $mform->getElement($group)->getElements()[0]->toHtml();
            $this->assertStringContainsString('href="' . $url->out() . '"', $html);
        }
    }

    /**
     * Test the methods behind the scripts require the user to be enrolled in the course.
     *
     * @param string $method Name of the method under test.
     * @param array $args Arguments after the course module ID.
     * @dataProvider script_method_provider
     */
    public function test_script_method_requires_enrolment(string $method, array $args): void {
        $this->setUser($this->getDataGenerator()->create_user());

        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessage('Unsupported redirect detected, script execution terminated');
        quizaccess_schoolyear::$method($this->quiz->cmid, ...$args);
    }

    /**
     * Test the methods behind the scripts require a valid sesskey.
     *
     * @param string $method Name of the method under test.
     * @param array $args Arguments after the course module ID.
     * @dataProvider script_method_provider
     */
    public function test_script_method_requires_sesskey(string $method, array $args): void {
        $this->log_in_enrolled_user('editingteacher');
        $_GET['sesskey'] = 'wrong';

        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessage(get_string('invalidsesskey', 'error'));
        quizaccess_schoolyear::$method($this->quiz->cmid, ...$args);
    }

    /**
     * Test the methods behind the scripts require the capability to use them.
     *
     * @param string $method Name of the method under test.
     * @param array $args Arguments after the course module ID.
     * @dataProvider script_method_provider
     */
    public function test_script_method_requires_capability(string $method, array $args): void {
        // The role may view the quiz, so require_login() passes and the capability check is reached.
        $roleid = create_role('Quiz viewer', 'quizviewer', '');
        assign_capability('mod/quiz:view', CAP_ALLOW, $roleid, \context_system::instance()->id);
        $this->log_in_enrolled_user($roleid);

        $this->expectException(\required_capability_exception::class);
        quizaccess_schoolyear::$method($this->quiz->cmid, ...$args);
    }

    /**
     * Test the methods behind the scripts require the quiz to have a Schoolyear exam.
     *
     * @param string $method Name of the method under test.
     * @param array $args Arguments after the course module ID.
     * @dataProvider script_method_provider
     */
    public function test_script_method_requires_exam(string $method, array $args): void {
        global $DB;

        $DB->delete_records('quizaccess_schoolyear', ['quizid' => $this->quiz->id]);
        $this->log_in_enrolled_user('editingteacher');

        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessage(get_string('errornoexam', 'quizaccess_schoolyear'));
        quizaccess_schoolyear::$method($this->quiz->cmid, ...$args);
    }

    /**
     * Test a teacher gets the URL from the API response for each target.
     *
     * @param string $target The Schoolyear page to open.
     * @dataProvider target_provider
     */
    public function test_get_teacher_ui_url_returns_url(string $target): void {
        $this->log_in_enrolled_user('editingteacher');

        \curl::mock_response(json_encode(['url' => 'https://app.schoolyear.app/ui']));
        $url = quizaccess_schoolyear::get_teacher_ui_url($this->quiz->cmid, $target);

        $this->assertSame('https://app.schoolyear.app/ui', $url);
    }

    /**
     * Test an unknown target is rejected without calling the API.
     */
    public function test_get_teacher_ui_url_rejects_unknown_target(): void {
        $this->log_in_enrolled_user('editingteacher');

        \curl::mock_response(self::SENTINEL);
        $exception = null;
        try {
            quizaccess_schoolyear::get_teacher_ui_url($this->quiz->cmid, 'unknown');
        } catch (\moodle_exception $e) {
            $exception = $e;
        }
        $this->assert_no_api_call();

        $this->assertInstanceOf(\moodle_exception::class, $exception);
        $this->assertSame(get_string('errorunknowntarget', 'quizaccess_schoolyear'), $exception->getMessage());
    }

    /**
     * Test a student gets the onboarding URL from the API response.
     */
    public function test_get_student_onboarding_url_returns_url(): void {
        $this->log_in_enrolled_user('student');

        \curl::mock_response(json_encode(self::WORKSPACE_RESPONSE));
        $url = quizaccess_schoolyear::get_student_onboarding_url($this->quiz->cmid);

        $this->assertSame('https://app.schoolyear.app/onboarding', $url);
    }

    /**
     * Test onboarding stores an entry token that expires one hour after the student's quiz close time.
     *
     * @param int $timeclose Close time of the quiz.
     * @param int|null $overridetimeclose Close time of the user override, or null for no override.
     * @param int $timeexpires Expected expiry time of the token.
     * @dataProvider close_time_provider
     */
    public function test_get_student_onboarding_url_stores_token(
        int $timeclose,
        ?int $overridetimeclose,
        int $timeexpires
    ): void {
        global $DB, $USER;

        $DB->set_field('quiz', 'timeclose', $timeclose, ['id' => $this->quiz->id]);
        $this->log_in_enrolled_user('student');
        if ($overridetimeclose !== null) {
            $DB->insert_record('quiz_overrides', [
                'quiz' => $this->quiz->id,
                'userid' => $USER->id,
                'timeclose' => $overridetimeclose,
            ]);
        }

        \curl::mock_response(json_encode(self::WORKSPACE_RESPONSE));
        quizaccess_schoolyear::get_student_onboarding_url($this->quiz->cmid);

        $this->assertEquals($timeexpires, $this->assert_one_token_stored()->timeexpires);
    }

    /**
     * Test onboarding stores an entry token that expires in 24 hours when the quiz has no close time.
     */
    public function test_get_student_onboarding_url_stores_token_without_close_time(): void {
        global $DB;

        $DB->set_field('quiz', 'timeclose', 0, ['id' => $this->quiz->id]);
        $this->log_in_enrolled_user('student');

        \curl::mock_response(json_encode(self::WORKSPACE_RESPONSE));
        $this->setCurrentTimeStart();
        quizaccess_schoolyear::get_student_onboarding_url($this->quiz->cmid);

        $this->assertTimeCurrent($this->assert_one_token_stored()->timeexpires - DAYSECS);
    }

    /**
     * Test onboarding stores no entry token when the API response has no onboarding URL.
     */
    public function test_get_student_onboarding_url_stores_no_token_without_url(): void {
        global $DB;

        $this->log_in_enrolled_user('student');

        \curl::mock_response(json_encode(['workspace_id' => self::WORKSPACE_ID]));
        $exception = null;
        try {
            quizaccess_schoolyear::get_student_onboarding_url($this->quiz->cmid);
        } catch (\moodle_exception $e) {
            $exception = $e;
        }

        $this->assertInstanceOf(\moodle_exception::class, $exception);
        $this->assertSame(get_string('errornourl', 'quizaccess_schoolyear'), $exception->getMessage());
        $this->assertSame(0, $DB->count_records('quizaccess_schoolyear_token'));
    }

    /**
     * Test onboarding stores no entry token when the onboarding URL in the API response is not an HTTPS Schoolyear URL.
     */
    public function test_get_student_onboarding_url_stores_no_token_with_invalid_url(): void {
        global $DB;

        $this->log_in_enrolled_user('student');

        \curl::mock_response(json_encode(['onboarding_url' => 'https://evil.test/x'] + self::WORKSPACE_RESPONSE));
        $exception = null;
        try {
            quizaccess_schoolyear::get_student_onboarding_url($this->quiz->cmid);
        } catch (\moodle_exception $e) {
            $exception = $e;
        }

        $this->assertInstanceOf(\moodle_exception::class, $exception);
        $this->assertSame(get_string('errorinvalidurl', 'quizaccess_schoolyear'), $exception->getMessage());
        $this->assertSame(0, $DB->count_records('quizaccess_schoolyear_token'));
    }

    /**
     * Test onboarding is refused in a "log in as" session, without an API call or an entry token.
     */
    public function test_get_student_onboarding_url_rejects_loggedinas_session(): void {
        global $DB;

        $student = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($student->id, $this->course->id, 'student');
        $this->setAdminUser();
        \core\session\manager::loginas($student->id, \context_system::instance());
        $_GET['sesskey'] = sesskey();

        \curl::mock_response(self::SENTINEL);
        $exception = null;
        try {
            quizaccess_schoolyear::get_student_onboarding_url($this->quiz->cmid);
        } catch (\moodle_exception $e) {
            $exception = $e;
        }
        $this->assert_no_api_call();

        $this->assertInstanceOf(\moodle_exception::class, $exception);
        $this->assertSame(get_string('errorloggedinas', 'quizaccess_schoolyear'), $exception->getMessage());
        $this->assertSame(0, $DB->count_records('quizaccess_schoolyear_token'));
    }

    /**
     * Test an entry request is denied and nobody is logged in, without an API call unless the request passes the local checks.
     *
     * @param string|null $header Signature header of the request, or null for no header.
     * @param string $token Entry token of the request.
     * @param int $expiresin Seconds from now until the stored token expires.
     * @param string|null $body Response body of the API, or null if the request must not reach the API.
     * @dataProvider entry_denial_provider
     */
    public function test_log_in_with_entry_token_denies_request(
        ?string $header,
        string $token,
        int $expiresin,
        ?string $body
    ): void {
        $this->create_token($this->getDataGenerator()->create_user()->id, time() + $expiresin);
        if ($header !== null) {
            $_SERVER['HTTP_X_SY_SIGNATURE'] = $header;
        }

        \curl::mock_response($body ?? self::SENTINEL);
        $exception = null;
        try {
            quizaccess_schoolyear::log_in_with_entry_token($token);
        } catch (\moodle_exception $e) {
            $exception = $e;
        }
        if ($body === null) {
            $this->assert_no_api_call();
        }

        $this->assertInstanceOf(\moodle_exception::class, $exception);
        $this->assertSame(get_string('errorentrydenied', 'quizaccess_schoolyear'), $exception->getMessage());
        $this->assertFalse(isloggedin());
    }

    /**
     * Test a valid entry request logs the user of the token in and returns the quiz URL, every time the browser starts.
     */
    public function test_log_in_with_entry_token_logs_user_in(): void {
        global $DB, $USER;

        $user = $this->getDataGenerator()->create_user();
        $this->create_token($user->id, time() + HOURSECS);
        $quizurl = new \moodle_url('/mod/quiz/view.php', ['id' => $this->quiz->cmid]);

        $url = $this->log_in_with_entry_token();

        $this->assertEquals($user->id, $USER->id);
        $this->assertSame($quizurl->out(false), $url->out(false));
        $this->assertTrue($DB->record_exists('quizaccess_schoolyear_token', ['tokenhash' => hash('sha256', self::TOKEN)]));

        // The Schoolyear browser opens the entry URL again in a new session when it starts again.
        $this->setUser(null);
        $url = $this->log_in_with_entry_token();

        $this->assertEquals($user->id, $USER->id);
        $this->assertSame($quizurl->out(false), $url->out(false));
    }

    /**
     * Test a valid entry request returns the quiz URL when the user of the token is already logged in.
     */
    public function test_log_in_with_entry_token_keeps_same_user_logged_in(): void {
        global $USER;

        $user = $this->getDataGenerator()->create_user();
        $this->create_token($user->id, time() + HOURSECS);
        $this->setUser($user);

        $url = $this->log_in_with_entry_token();

        $this->assertEquals($user->id, $USER->id);
        $this->assertSame((new \moodle_url('/mod/quiz/view.php', ['id' => $this->quiz->cmid]))->out(false), $url->out(false));
    }

    /**
     * Test a valid entry request is rejected when another user is logged in.
     */
    public function test_log_in_with_entry_token_rejects_other_logged_in_user(): void {
        $this->create_token($this->getDataGenerator()->create_user()->id, time() + HOURSECS);
        $other = $this->getDataGenerator()->create_user();
        $this->setUser($other);

        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessage(get_string('alreadyloggedin', 'error', format_string(fullname($other))));
        $this->log_in_with_entry_token();
    }

    /**
     * Test a valid entry request does not log a suspended user in.
     */
    public function test_log_in_with_entry_token_rejects_suspended_user(): void {
        $this->create_token($this->getDataGenerator()->create_user(['suspended' => 1])->id, time() + HOURSECS);

        $exception = null;
        try {
            $this->log_in_with_entry_token();
        } catch (\moodle_exception $e) {
            $exception = $e;
        }

        $this->assertInstanceOf(\moodle_exception::class, $exception);
        $this->assertSame(get_string('suspended', 'auth'), $exception->getMessage());
        $this->assertFalse(isloggedin());
    }

    /**
     * Test logging in with an entry token marks the session as passed MFA when MFA is ready.
     *
     * @param bool $ready Whether MFA is ready.
     * @dataProvider mfa_provider
     */
    public function test_log_in_with_entry_token_sets_mfa_pass_state(bool $ready): void {
        global $SESSION;

        if (!class_exists(\tool_mfa\manager::class)) {
            $this->markTestSkipped('MFA is part of Moodle from 4.3.');
        }
        set_config('enabled', (int) $ready, 'tool_mfa');
        set_config('enabled', 1, 'factor_nosetup');
        $this->create_token($this->getDataGenerator()->create_user()->id, time() + HOURSECS);

        $this->log_in_with_entry_token();

        $this->assertSame($ready, !empty($SESSION->tool_mfa_authenticated));
    }

    /**
     * Test deleting a quiz deletes its entry tokens and keeps those of other quizzes.
     */
    public function test_delete_settings_deletes_entry_tokens_of_deleted_quiz(): void {
        global $DB;

        $user = $this->getDataGenerator()->create_user();
        $otherquiz = $this->getDataGenerator()->create_module('quiz', ['course' => $this->course->id]);
        $this->create_token($user->id, time() + HOURSECS);
        $othertoken = new token(0, (object) [
            'tokenhash' => hash('sha256', 'othertoken'),
            'userid' => $user->id,
            'cmid' => $otherquiz->cmid,
            'workspaceid' => self::WORKSPACE_ID,
            'timeexpires' => time() + HOURSECS,
        ]);
        $othertoken->create();

        \curl::mock_response('{}');
        course_delete_module($this->quiz->cmid);

        $this->assertFalse($DB->record_exists('quizaccess_schoolyear_token', ['cmid' => $this->quiz->cmid]));
        $this->assertTrue($DB->record_exists('quizaccess_schoolyear_token', ['cmid' => $otherquiz->cmid]));
    }

    /**
     * Test disabling Schoolyear for a quiz deletes its entry tokens.
     */
    public function test_save_settings_deletes_entry_tokens_when_schoolyear_is_disabled(): void {
        global $DB;

        $this->create_token($this->getDataGenerator()->create_user()->id, time() + HOURSECS);

        \curl::mock_response('{}');
        quizaccess_schoolyear::save_settings((object) ['id' => $this->quiz->id, 'schoolyearenabled' => 0]);

        $this->assertSame(0, $DB->count_records('quizaccess_schoolyear_token'));
    }
}

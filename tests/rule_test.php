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

    /** @var \stdClass $course Test course to contain quiz. */
    private $course;

    /** @var \stdClass $quiz A test quiz with a Schoolyear exam. */
    private $quiz;

    /**
     * Called before every test.
     */
    public function setUp(): void {
        global $CFG, $DB;

        parent::setUp();

        $this->resetAfterTest();
        set_config('apibaseaddress', 'https://api.schoolyear.test', 'quizaccess_schoolyear');
        set_config('apikey', 'apikey', 'quizaccess_schoolyear');
        $_COOKIE['MoodleSession' . $CFG->sessioncookie] = 'session';

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
        global $CFG;

        unset($_COOKIE['MoodleSession' . $CFG->sessioncookie]);
        unset($_GET['sesskey']);
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
     * Test creating a workspace returns the onboarding URL from the API response.
     */
    public function test_create_workspace_returns_onboarding_url(): void {
        $this->setUser($this->getDataGenerator()->create_user());

        \curl::mock_response(json_encode(['onboarding_url' => 'https://app.schoolyear.test/onboarding']));
        $url = quizaccess_schoolyear::create_workspace('exam', $this->quiz->cmid, '');

        $this->assertSame('https://app.schoolyear.test/onboarding', $url);
    }

    /**
     * Test creating a workspace throws when the API response has no onboarding URL.
     */
    public function test_create_workspace_throws_without_onboarding_url(): void {
        $this->setUser($this->getDataGenerator()->create_user());

        \curl::mock_response('{}');

        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessage(get_string('errornourl', 'quizaccess_schoolyear'));
        quizaccess_schoolyear::create_workspace('exam', $this->quiz->cmid, '');
    }

    /**
     * Test a teacher UI URL method returns the URL from the API response.
     *
     * @param string $method Name of the method under test.
     * @dataProvider ui_url_method_provider
     */
    public function test_get_ui_url_returns_url(string $method): void {
        \curl::mock_response(json_encode(['url' => 'https://app.schoolyear.test/ui']));

        $this->assertSame('https://app.schoolyear.test/ui', quizaccess_schoolyear::$method('exam'));
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

        \curl::mock_response(json_encode(['url' => 'https://app.schoolyear.test/ui']));
        $url = quizaccess_schoolyear::get_teacher_ui_url($this->quiz->cmid, $target);

        $this->assertSame('https://app.schoolyear.test/ui', $url);
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

        \curl::mock_response(json_encode(['onboarding_url' => 'https://app.schoolyear.test/onboarding']));
        $url = quizaccess_schoolyear::get_student_onboarding_url($this->quiz->cmid);

        $this->assertSame('https://app.schoolyear.test/onboarding', $url);
    }
}

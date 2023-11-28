<?php

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {

    $settings->add(new admin_setting_configselect('quizaccess_schoolyear/apibaseaddress',
        get_string('apibaseaddress', 'quizaccess_schoolyear'),
        '',
        'https://api.schoolyear.app',
        array(
            'https://api.schoolyear.app' => 'https://api.schoolyear.app',
            'https://beta.api.schoolyear.app' => 'https://beta.api.schoolyear.app',
            'https://testing.api.schoolyear.app' => 'https://testing.api.schoolyear.app',
            'https://dev.api.schoolyear.app' => 'https://dev.api.schoolyear.app'
        )
    ));

    $settings->add(new admin_setting_configtext('quizaccess_schoolyear/apikey',
        get_string('apikey', 'quizaccess_schoolyear'),
        '',
        null,
        PARAM_RAW));
}

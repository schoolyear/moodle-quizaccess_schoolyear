<?php

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {

    $settings->add(new admin_setting_configselect('quizaccess_schoolyear/apibaseaddress',
        'Schoolyear API base address',
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
        'Schoolyear API key',
        '',
        null,
        PARAM_RAW));
}

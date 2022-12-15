<?php

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    
    $settings->add(new admin_setting_configtext('quizaccess_schoolyear/apibaseaddress',
        'Schoolyear API base address',
        '',
        'https://dev.api.schoolyear.app',
        PARAM_RAW));

    $settings->add(new admin_setting_configtext('quizaccess_schoolyear/apikey',
        'Schoolyear API key',
        '',
        '',
        PARAM_RAW));
}

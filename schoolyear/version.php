<?php

defined('MOODLE_INTERNAL') || die();

$plugin->component = 'quizaccess_schoolyear';
$plugin->release = 'v1.0';
$plugin->version = 2023090601;
$plugin->requires = 2020061500;
$plugin->maturity = MATURITY_STABLE;

$plugin->dependencies = array(
    'local_schoolyear_login' => ANY_VERSION,
);
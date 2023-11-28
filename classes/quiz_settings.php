<?php
namespace quizaccess_schoolyear;

class quiz_settings extends \core\persistent {

    const TABLE = 'quizaccess_schoolyear';

    protected static function define_properties() : array {
        return array(
            'quizid' => array(
                'type' => PARAM_INT,
            ),
            'schoolyearenabled' => array(
                'type' => PARAM_INT,
                'default' => 0,
            ),
            'examid' => array(
                'type' => PARAM_TEXT,
                'default' => '',
                'null' => NULL_ALLOWED,
            ),
        );
    }
}

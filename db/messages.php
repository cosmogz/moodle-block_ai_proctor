<?php
defined('MOODLE_INTERNAL') || die();

$messageproviders = [
    'studentbanned' => [
        'capability' => 'moodle/site:sendmessage',
        'defaults' => [
            'popup' => MESSAGE_PERMITTED + MESSAGE_DEFAULT_LOGGEDIN + MESSAGE_DEFAULT_LOGGEDOFF,
            'email' => MESSAGE_PERMITTED + MESSAGE_DEFAULT_LOGGEDOFF,
        ],
    ],
    'violationdetected' => [
        'capability' => 'moodle/grade:viewall',
        'defaults' => [
            'popup' => MESSAGE_PERMITTED + MESSAGE_DEFAULT_LOGGEDIN,
            'email' => MESSAGE_PERMITTED,
        ],
    ],
];

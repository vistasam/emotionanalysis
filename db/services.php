<?php
$functions = array(
    'blocks_emotionanalysis_capture_emotions' => array(
        'classname' => 'block_emotionanalysis_external',
        'methodname' => 'store_emotion',
        'classpath' => 'blocks/emotionanalysis/externallib.php',
        'description' => 'Store emotion data',
        'type' => 'write',
        'ajax' => true,
        'services' => array(MOODLE_OFFICIAL_MOBILE_SERVICE),
        'capabilities' => '',
    ),
    'blocks_emotionanalysis_session_tracker' => array(
        'classname' => 'block_emotionanalysis_external',
        'methodname' => 'session_tracker',
        'classpath' => 'blocks/emotionanalysis/externallib.php',
        'description' => 'Update information for session',
        'type' => 'write',
        'ajax' => true,
        'services' => array(MOODLE_OFFICIAL_MOBILE_SERVICE),
        'capabilites' => '',
    ),
    'blocks_emotionanalysis_prev_activity_checker' => array(
        'classname' => 'block_emotionanalysis_external',
        'methodname' => 'activity_checker',
        'classpath' => 'blocks/emotionanalysis/externallib.php',
        'description' => 'Checking Previous Activity if Exists in Db',
        'type' => 'read',
        'ajax' => true,
        'services' => array(MOODLE_OFFICIAL_MOBILE_SERVICE),
        'capabilites' => '',
    ),
    'block_emotionanalysis_fetch_captured_data' => array(
        'classname' => 'block_emotionanalysis_external',
        'methodname' => 'fetch_data',
        'classpath' => 'blocks/emotionanalysis/externallib.php',
        'description' => 'Fetching student data from database',
        'type' => 'write',
        'ajax' => true,
        'services' => array(MOODLE_OFFICIAL_MOBILE_SERVICE),
        'capabilities' => '',
    ),
    'blocks_emotionanalysis_course_result' => array(
        'classname' => 'block_emotionanalysis_external',
        'methodname' => 'course_result',
        'classpath' => 'blocks/emotionanalysis/externallib.php',
        'description' => 'Show the course result',
        'type' => 'read',
        'ajax' => true,
        'services' => array(MOODLE_OFFICIAL_MOBILE_SERVICE),
        'capabilites' => '',
    ),
    'block_emotionanalysis_fetch_emotion_data' => array(
        'classname' => 'block_emotionanalysis_external',
        'methodname' => 'fetch_emotions',
        'classpath' => 'blocks/emotionanalysis/externallib.php',
        'description' => 'Fetching Emotion Data from Database ',
        'type' => 'write',
        'ajax' => true,
        'services' => array(MOODLE_OFFICIAL_MOBILE_SERVICE),
        'capabilities' => '',
    ),
)
?>

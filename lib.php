<?php
use core\output\notification;

defined('MOODLE_INTERNAL') || die;

require_once(dirname(__FILE__) . '/../../config.php'); // Include Moodle configuration file
require_once(__DIR__ . '/externallib.php');
function block_emotionanalysis_before_footer(){
    global $PAGE,$USER;
    $userId = $USER->id;
    if(isloggedin($userId)) {
        $data = check_enrolled_courses($userId);
        if (count($data['enrolledcourses']) > 0) {
            if ($PAGE->url->compare(new moodle_url('/my/'))) {
                $url = new moodle_url('/blocks/emotionanalysis/result.php');
                $link = '<a href="' . $url . '">View results</a>';

                // Display the notification with the link.
                $message = 'Results From Emotion Analysis Block. ' . $link;
                \core\notification::add($message, notification::NOTIFY_SUCCESS);
            }
        }
    }
}
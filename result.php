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

/**
 * Results views from emotionanalysis.
 *
 * @package   block_emotionanalysis
 * @copyright Rohit
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


require_once(dirname(__FILE__) . '/../../config.php'); // Include Moodle configuration file
require_once(__DIR__ . '/externallib.php');
global $DB, $USER, $PAGE;

try {
    require_login();
} catch (coding_exception|require_login_exception|moodle_exception $e) {
}
try {
    $PAGE->set_context(context_system::instance());
} catch (dml_exception $e) {
}
$PAGE->set_title("View results here");
try {
    $PAGE->set_url(new moodle_url('/blocks/emotionanalysis/result.php'));
} catch (coding_exception $e) {
}
$PAGE->set_heading('Emotion Analysis Results');
$PAGE->requires->css(new moodle_url('/blocks/emotionanalysis/styles.css'));
$PAGE->requires->js_call_amd('block_emotionanalysis/result');
$enrolledcoursesdata = check_enrolled_courses($USER->id);

if(empty($enrolledcoursesdata))
{
    $redirect = new moodle_url('/');
    redirect($redirect);
} else {
        $templatecontext = (object)
        [
            'heading' => 'Emotion Analysis Results',
            'CourseElementLabel' => 'Course',
            'UserElementLabel' => 'User',
            'VideoElementLabel' => 'Video',
            'enrolledcoursesdata' => $enrolledcoursesdata,
            'UserId' => $USER->id,

        ];

    echo $OUTPUT->header();

    echo $OUTPUT->render_from_template('block_emotionanalysis/result',$templatecontext);

    echo $OUTPUT->footer();

}

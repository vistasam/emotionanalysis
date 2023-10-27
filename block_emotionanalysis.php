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
 * Block emotion_analaysis
 * @package   block_emotionanalysis
 * @copyright 2023 onwards Rohit
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

class block_emotionanalysis extends block_base {
    function init()
    {
        $this->title = 'Emotion Analysis Block';
    }
    /**
     * Core function, specifies where the block can be used.
     * @return array
     */
    public function applicable_formats() {
        return array('course-view' => false, 'mod' => true);
    }
    function get_content()
    {
        global $COURSE,$CFG;
        $cmid = $this->page->cm->id; // Course module Id for tracking
        $insid = $this->page->cm->instance; // Instance id for tracking
        $cid = $COURSE->id; // Course Id
        $modType = $this->page->cm->module;
        $this->page->requires->css(new moodle_url('/blocks/emotionanalysis/styles.css'));
        $this->page->requires->js_call_amd('block_emotionanalysis/recognition');
        $this->page->requires->js_call_amd('block_emotionanalysis/prevActivityChecker');
        $this->page->requires->js_call_amd('block_emotionanalysis/sessionTracker');
        $this->page->requires->js('/blocks/emotionanalysis/amd/build/face-api.min.js',true);

        if($this->content !== NULL)
        {
            return $this->content;
        }
        // Passing the data for the database to make a unique entry for each user
        $hiddenData =
            '<p type="hidden" id="course-id-val" value = "'.base64_encode($cid).'"/>
            <p type="hidden" id="course-module-id" value = "'.base64_encode($cmid).'"/>
            <p type="hidden" id="course-instance-id" value = "'.base64_encode($insid).'"/>
            <p type="hidden" id="mod-id" value = "'.base64_encode($modType).'"/>';
        // Initialize the camera tag
        $html = html_writer::start_tag('video',[
            'id' => 'live-view',
            'height' => '120',
            'width' => '160',
            'muted' => 'muted',
            'autoplay' => 'autoplay'
        ]);
        $html .= html_writer::tag('source', null,['src' => null]);
        $html .= html_writer::end_tag('video');

        // Create an empty div with the id "emotion-bar"
        $html .= html_writer::start_div('emotion-bar-detection');
        $html .= html_writer::start_tag('p', ['id' => 'FacialExpressionMonitor']);
        $html .= 'Facial Expression Monitor';
        $html .= html_writer::end_tag('p');


        // Create the three emotion segments within the "emotion-bar" div
        $html .= html_writer::start_div('emotion-segment', ['id' => 'happy-segment']);
        $html .= html_writer::end_div();

        $html .= html_writer::start_div('emotion-segment', ['id' => 'neutral-segment']);
        $html .= html_writer::end_div();

        $html .= html_writer::start_div('emotion-segment', ['id' => 'sad-segment']);
        $html .= html_writer::end_div();

        $html .= html_writer::end_div();

        $html .= $hiddenData;
        $this->content = new stdClass;
        $this->content->text = $html;

        return $this->content;

    }
}
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
 * Javascript to initialise the recognition of a student facial expression.
 *
 * @module     block_emotionanalysis/recognition
 * @copyright  2023 Rohit <rx18008@edu.rta.lv>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
// eslint-disable-next-line no-unused-vars
define(['jquery', './recognition', 'core/ajax','./youTubeManager'], function ($, recognition, Ajax,youTubeManager) {
    let moduleType = recognition.value;
    let videoElement = recognition.videoElement;
    let requestData = {
        "values": {
            courseId: recognition.courseId,
            instanceId: recognition.instanceId,
            resourceId: recognition.resourceId,
        }
    };
    // eslint-disable-next-line no-unused-vars
    let request = {
        methodname: 'blocks_emotionanalysis_session_tracker',
        args: requestData,
    };
    if (moduleType === 17) {
        // eslint-disable-next-line no-undef,promise/catch-or-return
        youTubeManager.readyPromise.then(() => {
            // eslint-disable-next-line no-unused-vars
            youTubeManager.player.addEventListener('onStateChange', function(event) {
                if (event.data === 2) {
                    let lastActiveTime = youTubeManager.currentTime;
                    sessionTracker(lastActiveTime);
                }
            });
        });
    } else if (moduleType === 19) {
        videoElement.addEventListener('pause', () => {
            let lastActiveTime = videoElement.currentTime;
            sessionTracker(lastActiveTime);
        });
    }
    /**
     *Function to send the session Tracker Time
     * @param {int} lastActiveTime
     */
    function sessionTracker(lastActiveTime) {
        let requestData = {
            "values": {
                courseId: recognition.courseId,
                lastActiveTime: Math.floor(lastActiveTime),
                instanceId: recognition.instanceId,
                resourceId: recognition.resourceId,
            }
        };
        // eslint-disable-next-line no-unused-vars
        let request = {
            methodname: 'blocks_emotionanalysis_session_tracker',
            args: requestData,
        };
        Ajax.call([request])[0].done(function (data) {
            // eslint-disable-next-line no-console
            console.log(data);
        }).fail(function(data) {
            // eslint-disable-next-line no-console
            console.log(data.errorcode);
        });
    }
});
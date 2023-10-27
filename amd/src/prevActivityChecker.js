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
 * @module     block_emotionanalysis/prevActivityChecker
 * @copyright  2023 Rohit <rx18008@edu.rta.lv>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define([
    'jquery', 'core/ajax', './recognition', 'core/modal_factory', 'core/modal_events', 'core/str','./youTubeManager'],
    // eslint-disable-next-line no-unused-vars
    function($, Ajax, recognition, ModalFactory, ModalEvents, String,youTubeManager) {
        // eslint-disable-next-line no-unused-vars
        let unfinishActivityText = String.get_string('unfinish_activity_text', 'block_emotionanalysis');
        // eslint-disable-next-line no-unused-vars
        let finishActivityText = String.get_string('finished_activity_text', 'block_emotionanalysis');
        let videoType = recognition.value;
        let totalDuration;
        let request;
        let requestData;
        if (videoType === 19) {
            totalDuration = recognition.videoElement.duration;
            if (isNaN(totalDuration)) {
                location.reload();
            }
            previousActivityChecker();
        } else {
        // eslint-disable-next-line no-undef,promise/catch-or-return
        youTubeManager.readyPromise.then(() => {
            // eslint-disable-next-line no-unused-vars
            youTubeManager.player.addEventListener('onReady', function(event) {
                totalDuration = youTubeManager.totalDuration;
                // eslint-disable-next-line no-console
                    previousActivityChecker();
            });
        });
        }
        /**
         *Function to check previous activity
         */
        function previousActivityChecker() {
            requestData = {
                "values": {
                    courseId: recognition.courseId,
                    deleteConfirmation: 0,
                    instanceId: recognition.instanceId,
                    resourceId: recognition.resourceId,
                }
            };
            // eslint-disable-next-line no-unused-vars
            request = {
                methodname: 'blocks_emotionanalysis_prev_activity_checker',
                args: requestData,
            };
            Ajax.call([request])[0].done(function(data) {
                if (data.activity_finish_date) {
                    showModal(data.timestamp, finishActivityText, data.activity_finish_date, videoType);
                } else {
                    let totalDuration = recognition.videoElement.duration;
                    if (isNaN(totalDuration)) {
                        location.reload();
                    }
                    if (data.timestamp !== null) {
                        if (Math.abs(totalDuration - data.timestamp)) {
                            showModal(data.timestamp, unfinishActivityText, data.activity_finish_date, videoType);
                        }
                    }
                }
                // eslint-disable-next-line no-unused-vars
            }).fail(function (data) {
                // eslint-disable-next-line no-console
                console.log(data);
            });
        }
        /**
         *@param {int} timestamp from previous activity
         *@param {Text} msgText text for confirmation
         *@param {bool} activityFinishStatus true or false for the confirmation
         * @param {int} videoType to check the video Type if embedded or normal
         */
        function showModal(timestamp, msgText, activityFinishStatus, videoType) {
            // eslint-disable-next-line promise/catch-or-return
            ModalFactory.create({
                type: ModalFactory.types.SAVE_CANCEL,
                title: String.get_string('previous_activity', 'block_emotionanalysis'),
                // eslint-disable-next-line max-len
                body: msgText,
                // eslint-disable-next-line promise/always-return
            }).then(function(modal) {
                // eslint-disable-next-line camelcase,promise/always-return
                if (activityFinishStatus) {
                    modal.setSaveButtonText("No");
                    modal.modal.find('.btn-secondary[data-action="cancel"]').text('Yes');
                } else {
                    modal.setSaveButtonText('Yes');
                    modal.modal.find('.btn-secondary[data-action="cancel"]').text('No');
                }
                let root = modal.getRoot();
                root.on(ModalEvents.save, function() {
                    if (videoType === 17) {
                        youTubeManager.player.seekTo(timestamp, false);
                    } else {
                        recognition.videoElement.currentTime = timestamp;
                    }
                });
                root.on(ModalEvents.cancel, function() {
                    modalHandler(timestamp, activityFinishStatus);
                });
                root.on(ModalEvents.outsideClick, function() {
                    modalHandler(timestamp, activityFinishStatus);
                });
                // eslint-disable-next-line babel/no-unused-expressions
                modal.modal.find('.close').hide();
                modal.show();
            });
        }
        /**
         * @param {int} timestamp passed from previous Modal
         * @param {bool} activityFinishStatus for further actions
         * Handling the Modal for cancel events
         * */
        function modalHandler(timestamp, activityFinishStatus) {
            ModalFactory.create({
                type: ModalFactory.types.SAVE_CANCEL,
                title: String.get_string('confirmation_title', 'block_emotionanalysis'),
                body: String.get_string('confirmation_warning', 'block_emotionanalysis'),
            }).then(function(modal) {
                // eslint-disable-next-line no-console
                modal.setSaveButtonText(String.get_string('confirmation_yes', 'block_emotionanalysis'));
                if (activityFinishStatus) {
                    modal.modal.find('.btn-secondary[data-action="cancel"]').text("No");
                } else {
                    // eslint-disable-next-line max-len
                    modal.modal.find('.btn-secondary[data-action="cancel"]').text("No I will Resume");
                }
                let root = modal.getRoot();
                root.on(ModalEvents.save, function() {
                    requestData.values.deleteConfirmation = 1;
                    Ajax.call([request])[0].done(function(data) {
                        // eslint-disable-next-line no-console
                        console.log(data);
                    });
                });
                root.on(ModalEvents.cancel, function() {
                    if (!activityFinishStatus) {
                        recognition.lectureVideo.currentTime = timestamp;
                    }
                });
                root.on(ModalEvents.outsideClick, function() {
                    if (activityFinishStatus) {
                        showModal(timestamp, finishActivityText, activityFinishStatus);
                    } else {
                        showModal(timestamp, unfinishActivityText, activityFinishStatus);
                    }
                });
                modal.modal.find('.close').hide();
                modal.show();
            });
        }
    });


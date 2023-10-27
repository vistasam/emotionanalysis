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


define(['jquery', 'core/ajax', 'core/url', './youTubeManager', 'core/str'], function($, Ajax, url, youTubeManager, String) {
    let camArea = document.getElementById("live-view");
    // eslint-disable-next-line no-unused-vars
    let modelLoadedStatus = false;
    let predictedEmotion;
    let videoElement;
    let videoWatchTime;
    let modelsUrl = url.fileUrl('/blocks/emotionanalysis/thirdpartylibs/models', '');
    Promise.all([
        // eslint-disable-next-line no-undef
        faceapi.nets.tinyFaceDetector.loadFromUri(modelsUrl),
        // eslint-disable-next-line no-undef
        faceapi.nets.faceLandmark68Net.loadFromUri(modelsUrl),
        // eslint-disable-next-line no-undef
        faceapi.nets.faceRecognitionNet.loadFromUri(modelsUrl),
        // eslint-disable-next-line no-undef
        faceapi.nets.faceExpressionNet.loadFromUri(modelsUrl),
        // eslint-disable-next-line no-undef
        faceapi.nets.ageGenderNet.loadFromUri(modelsUrl),
        // eslint-disable-next-line no-undef
        faceapi.nets.ssdMobilenetv1.loadFromUri(modelsUrl),
        // eslint-disable-next-line promise/always-return
    ]).then(function() {
        modelLoadedStatus = true;
    });
    const categorizedEmotions = {
        positive: 0,
        neutral: 0,
        negative: 0
    };

    const happySegment = document.getElementById('happy-segment');
    const neutralSegment = document.getElementById('neutral-segment');
    const sadSegment = document.getElementById('sad-segment');
    happySegment.style.backgroundColor = 'blue';
    neutralSegment.style.backgroundColor = 'yellow';
    sadSegment.style.backgroundColor = 'red';
    const segmentHeight = '20px'; // You can adjust this as needed
    happySegment.style.height = segmentHeight;
    neutralSegment.style.height = segmentHeight;
    sadSegment.style.height = segmentHeight;
    const transitionDuration = '0.5s'; // You can adjust this as needed
    happySegment.style.transition = `width ${transitionDuration}`;
    neutralSegment.style.transition = `width ${transitionDuration}`;
    sadSegment.style.transition = `width ${transitionDuration}`;
    // eslint-disable-next-line no-unused-vars
    let detectionInterval;
    let element = document.getElementById("mod-id");
    let value = parseInt(atob(element.getAttribute("value")), 10);
    if (value === 17) {
        // Reference the iframe element by its index (you can modify this to match your specific use case)
        let iframe = document.getElementsByTagName('iframe')[0];
        if (iframe) {
            // Set the id attribute for the iframe
            iframe.setAttribute('id', 'another-love');
            // Get the current src attribute
            let currentSrc = iframe.getAttribute('src');
            // Split the current src at the "?" character
            let srcParts = currentSrc.split('?');
            // Create the new src with "?enablejsapi=1" and the original video ID
            let newSrc = srcParts[0] + '?enablejsapi=1';
            // Update the src attribute with the modified URL
            iframe.setAttribute('src', newSrc);
        }
        // eslint-disable-next-line no-undef
        youTubeManager.readyPromise.then(() => {
            // eslint-disable-next-line no-unused-vars,no-undef
            youTubeManager.player.addEventListener('onStateChange', (event) => {
                // eslint-disable-next-line no-undef
                if (event.data === YT.PlayerState.PLAYING) {
                    enableWebCam();
                    videoWatchTime = youTubeManager.currentTime;
                    // eslint-disable-next-line no-undef
                } else if (event.data === 2) {
                    disableWebCam();
                }
            });
        }).catch(error => {
            // eslint-disable-next-line no-console
            console.error(error.message);
        });
    } else if (value === 19) {
        let videoTags = document.getElementsByTagName("video");
        videoElement = videoTags[1];
        videoElement.addEventListener("play", () => {
            videoWatchTime = videoElement.currentTime;
            enableWebCam();
        });
        videoElement.addEventListener("pause", () => {
            disableWebCam();
        });
    }
    // Returning common variable to use in other modules
    return {
        courseId: getDecodedValue("course-id-val"),
        resourceId: getDecodedValue("course-module-id"),
        instanceId: getDecodedValue("course-instance-id"),
        value: value,
        videoElement: videoElement,
    };
    /**
     * Function to Enable WebCam for emotion Capture
     */
    function enableWebCam() {
        if (navigator.mediaDevices && navigator.mediaDevices.getUserMedia) {
            // Not adding audio because we need only video
            // eslint-disable-next-line promise/always-return
            navigator.mediaDevices.getUserMedia({video: true}).then(function(stream) {
                let camArea = document.getElementById("live-view");
                camArea.srcObject = stream;
                camArea.play();
                captureFacialExpression();
            }).catch(function() {
                if (value === 19) {
                    videoElement.pause();
                    String.get_string('no_camera_permission', 'block_emotionanalysis')
                        .then(function(text) {
                            window.alert(text);
                        });
                } else if (value === 17) {
                    youTubeManager.player.pauseVideo(); // Pause the YouTube video
                    youTubeManager.player.addEventListener('onStateChange', function(event) {
                        // eslint-disable-next-line no-undef
                        if (event.data === YT.PlayerState.PAUSED) {
                            String.get_string('no_camera_permission', 'block_emotionanalysis')
                                .then(function(text) {
                                    window.alert(text);
                                });
                            camArea.srcObject = null;
                        }
                    });
                }
            });
        }
    }

    /**
     * Function to disable WebCam
     */
    function disableWebCam() {
        camArea.srcObject = null;
        clearInterval(detectionInterval);
    }

    /**
     * function to capture Facial Expression
     */
    function captureFacialExpression() {
        let noFaceFound;
        let prevEmotionState;
        detectionInterval = setInterval(async() =>{
            // eslint-disable-next-line no-undef
            let detections = await faceapi.detectAllFaces(camArea,
                // eslint-disable-next-line no-undef
                new faceapi.TinyFaceDetectorOptions({scoreThreshold: 0.3,
                    inputsize: 320})).withFaceExpressions().withAgeAndGender();
            if (detections.length === 0) {
                // eslint-disable-next-line no-undef,max-len
                detections = await faceapi.detectAllFaces(camArea,
                    // eslint-disable-next-line no-undef
                    new faceapi.SsdMobilenetv1Options({scoreThreshold: 0.3,
                        inputsize: 320})).withFaceExpressions().withAgeAndGender();
            }
            if (detections.length > 0) {
                let gender = detections[0].gender;
                let ageGroup = detections[0].age;
                const result = getVideoTimeStamp(value);
                // Sorting the array
                predictedEmotion = Object.fromEntries(Object.entries(detections[0].expressions).sort(([, a], [, b]) => b - a));
                let emotionState = Object.keys(predictedEmotion)[0];
                let secondEmotion = Object.keys(predictedEmotion)[1];
                if (emotionState === 'happy'
                    || (emotionState === 'surprised' && secondEmotion === 'happy')
                    || (emotionState === 'neutral' && secondEmotion === 'happy')) {
                    categorizedEmotions.positive++;
                } else if (emotionState === 'neutral') {
                    categorizedEmotions.neutral++;
                } else {
                    categorizedEmotions.negative++;
                }
                // eslint-disable-next-line no-unused-vars
                let totalEmotion = Object.values(categorizedEmotions).reduce((acc, value) => acc + value, 0);
                requestAnimationFrame(() => updateEmotionBar(totalEmotion));
                if (emotionState !== prevEmotionState) {
                    let requestData = {
                        "values": {
                            "emotionState": emotionState,
                            "videoTimeStamp": Math.floor(result.currentTime),
                            "courseId": getDecodedValue("course-id-val"),
                            "resourceId": getDecodedValue("course-module-id"),
                            "instanceId": getDecodedValue("course-instance-id"),
                            "totalDuration": Math.floor(result.totalDuration),
                            "videoWatchTime": Math.floor(videoWatchTime),
                            "gender": gender,
                            "ageGroup": ageGroup,
                        }
                    };
                    prevEmotionState = emotionState;
                    let request = {
                        methodname: 'blocks_emotionanalysis_capture_emotions',
                        args: requestData
                    };
                    Ajax.call([request])[0].done(function(data) {
                        // eslint-disable-next-line no-console
                        console.log(data);
                    }).fail(function(data) {
                        // eslint-disable-next-line no-console
                        console.log(data.error);
                    });
                    noFaceFound = 0;
                }
            } else {
                // eslint-disable-next-line no-console
                console.log("No face Found");
                noFaceFound = ++noFaceFound;
                if (noFaceFound > 10) {
                    if (value === 19)
                    {
                        videoElement.pause();
                        window.alert("we are unable to find any face Please adjust your camera or Position");
                    } else {
                        youTubeManager.player.pauseVideo(); // Pause the YouTube video
                        youTubeManager.player.addEventListener('onStateChange', function(event) {
                            // eslint-disable-next-line no-undef
                            if (event.data === YT.PlayerState.PAUSED) {
                                // The YouTube video has been paused, continue with other code.
                                window.alert("We are unable to find any face. Please adjust your camera or position.");
                                camArea.srcObject = null;
                            }
                        });
                    }
                    clearInterval(detectionInterval);
                    camArea.srcObject = null;
                    noFaceFound = 0;
                }
            }
        }, 1000);
    }
    /**
     * Javascript to getDecodedValue.
     * @param {string} id getting the id of the current block
     * @return {string}
     */
    function getDecodedValue(id) {
        let element = document.getElementById(id);
        // Return the decoded value
        return atob(element.getAttribute("value"));
    }

    /**
     * Function get Video Timestamp
     *@param {int} moduleType
     * @return {int} currentTime
     * @return {int} totalDuration
     */
    function getVideoTimeStamp(moduleType) {
        if (moduleType === 17) {
            return {
                currentTime: youTubeManager.player.getCurrentTime(),
                totalDuration: youTubeManager.player.getDuration()
            };
        } else if (moduleType === 19) {
            return {
                currentTime: videoElement.currentTime,
                totalDuration: videoElement.duration
            };
        }
    }
    /**
     *Function to update emotion bar
     * @param {int} totalEmotion number of total collected values
     */
    function updateEmotionBar(totalEmotion) {
        const happySegment = document.getElementById('happy-segment');
        const neutralSegment = document.getElementById('neutral-segment');
        const sadSegment = document.getElementById('sad-segment');

        // eslint-disable-next-line no-undef
        happySegment.style.width = `${(categorizedEmotions.positive / totalEmotion) * 100}%`;
        // eslint-disable-next-line no-undef
        neutralSegment.style.width = `${(categorizedEmotions.neutral / totalEmotion) * 100}%`;
        // eslint-disable-next-line no-undef
        sadSegment.style.width = `${(categorizedEmotions.negative / totalEmotion) * 100}%`;
    }
});
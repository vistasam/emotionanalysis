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
 * // Fetch the captured results and present them in a graphical form
 * @module     block_emotionanalysis/result
 * @copyright  2023 Rohit <rx18008@edu.rta.lv>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// eslint-disable-next-line no-unused-vars
define(
    ['jquery', 'core/ajax', 'core/chartjs', 'core/modal_factory', 'core/modal_events', 'core/str'],
    function($, Ajax, Chart, ModalFactory, ModalEvents, String,) {
        // eslint-disable-next-line no-unused-vars
        let response;
        let fetchRequest;
        let semotionChart;
        let sctx = document.getElementById('schart');
        const courseSelect = document.getElementById('course-select');
        const userSelect = document.getElementById('user-select');
        const videoSelect = document.getElementById('video-select');
        const chartType = document.getElementById('chartType');
        let dataTable = document.getElementById('additional-information');
        const emotionContainer = document.getElementById("emotion-container");
        let courseSelectedValue;
        // Calling the Activity Checker on Document Ready
        $(document).ready(function() {
            resultFetcher();
        });
        chartType.addEventListener('change', function() {
            updateSubmitButton();
        });
        videoSelect.addEventListener('change', function() {
            $("#chartType").val('');
            updateSubmitButton();
        });
        // Reset Button Event
        $('#reset-button').click(function() {
            $('#course-select').prop('selectedIndex', 0);
            removeexistingvalues('user-select');
            removeexistingvalues('video-select');
            $('#chartType').prop('selectedIndex', 0);
            $('#submit-button').prop('disabled', true);
            $('#delete-button').prop('disabled', true);
            while (emotionContainer.firstChild) {
                emotionContainer.removeChild(emotionContainer.firstChild);
            }
            dataTable.innerHTML = '';
            if (semotionChart !== null) {
                semotionChart.destroy();
            }
        });
        $("#delete-button").click(function() {
            confirmationModal();
        });
        $("#submit-button").click(function() {
            courseSelectedValue = courseSelect.value;
            let studentSelectedValue = userSelect.value;
            let videoSelectedValue = videoSelect.value;
            let chartSelectedType = chartType.value;
            if (courseSelect.value !== '' &&
                userSelect.value == '' &&
                videoSelect.value == '' &&
                chartType.value !== '') {
                let requestData = {
                    values: {
                        courseId: courseSelectedValue,
                    }
                };
                let request = {
                    methodname: 'blocks_emotionanalysis_course_result',
                    args: requestData,
                };
                // eslint-disable-next-line no-unused-vars
                Ajax.call([request])[0].done(function(data) {
                    const selectedOption = courseSelect.options[courseSelect.selectedIndex]; // Get the selected option
                    const selectedText = selectedOption.text; // Get the text of the selected option
                    let emotionCounts = chartArray(data.videoEmotion);
                    const emotionCountsArray = emotionCounts.map(item => ({
                        count: item.countofemotion,
                        state: item.emotion_state
                    }));
                    // eslint-disable-next-line no-unused-vars
                    const [countOfSecond, totalDetectedEmotions] = updateEmotionCounts(emotionCountsArray);
                    generategraph(Object.entries(countOfSecond), sctx, chartSelectedType);
                    // eslint-disable-next-line no-console
                    let barData = mergeEmotions(data.videoEmotion);
                    while (dataTable.rows.length > 0) {
                        dataTable.deleteRow(0);
                    }
                    // Create a new row for the dataTable headers
                    let headerRow = dataTable.insertRow();
                    // Create a single header cell for the additional information
                    let headerCell = headerRow.insertCell();
                    headerCell.classList.add('text-center');
                    headerCell.colSpan = 3;
                    headerCell.textContent = 'Additional Information';

                    // Create a new row for Course Name
                    let courseRow = dataTable.insertRow();
                    let courseRowlabelCell = courseRow.insertCell();
                    courseRowlabelCell.textContent = 'Course Name';
                    let courseRowValue = courseRow.insertCell();
                    courseRowValue.textContent = selectedText;
                    if (data.flag)
                    {
                        // Create a new row for  Total student
                        let studentRow = dataTable.insertRow();
                        // Create cells for Total students name
                        let studentLabelCell = studentRow.insertCell();
                        studentLabelCell.textContent = 'Total Enrolled Student';

                        let studentValueCell = studentRow.insertCell();
                        studentValueCell.textContent = data.enrolledStudentsCount;

                        let activeStudentRow = dataTable.insertRow();
                        // Create cells for Total students name
                        let activeStudentLabel = activeStudentRow.insertCell();
                        activeStudentLabel.textContent = 'Number of Student Active in Course';

                        let activeStudentCell = activeStudentRow.insertCell();
                        activeStudentCell.textContent = data.totalStudents;

                        let finishedActivityRow = dataTable.insertRow();
                        // Create cells for Total students name
                        let finishedActivityLabel = finishedActivityRow.insertCell();
                        finishedActivityLabel.textContent = 'Number of Students Finished Activity (Even Single) ';

                        let finishedActivityCell = finishedActivityRow.insertCell();
                        finishedActivityCell.textContent = data.finishActivity;
                    } else
                    {
                        // Create a new row for  Total student
                        let studentRow = dataTable.insertRow();
                        // Create cells for Total students name
                        let studentLabelCell = studentRow.insertCell();
                        studentLabelCell.textContent = 'Student Name';

                        let studentValueCell = studentRow.insertCell();
                        studentValueCell.textContent = data.userName;
                    }
                    const colors = {
                        positive: "blue",
                        neutral: "yellow",
                        negative: "red",
                    };
                    while (emotionContainer.firstChild) {
                        emotionContainer.removeChild(emotionContainer.firstChild);
                    }
                    // Bar Result for Videos
                    barData.forEach(item => {
                        const emotionSegment = document.createElement("div");
                        const emotionTextSegment = document.createElement("div");
                        emotionSegment.classList.add("emotion-bar");
                        emotionTextSegment.classList.add("emotion-bar-detail");
                        const maxCount = item.emotions.positive + item.emotions.neutral + item.emotions.negative;
                        // Add the title here
                        const titleSegment = document.createElement("div");
                        titleSegment.textContent = "Lecture Title - "+ item.resourceTitle;
                        titleSegment.style.marginBottom = '10px'; // Add margin for spacing

                        emotionTextSegment.appendChild(titleSegment);
                        Object.entries(item.emotions).forEach(([emotion, count]) => {
                            const segment = document.createElement("div");
                            segment.style.marginBottom = '20px';
                            segment.style.backgroundColor = colors[emotion];
                            segment.style.height = '20px';
                            segment.style.width = `${(count / maxCount) * 100}%`;
                            const labelSegment = document.createElement("div");
                            const percentage = ((count / maxCount) * 100).toFixed(2);
                            labelSegment.style.marginBottom = '5px';
                            labelSegment.textContent = `${emotion.charAt(0).toUpperCase() + emotion.slice(1)} ${percentage}%`;
                            emotionTextSegment.appendChild(labelSegment);
                            emotionSegment.appendChild(segment);
                        });
                        emotionContainer.appendChild(emotionTextSegment);
                        emotionContainer.appendChild(emotionSegment);
                    });
                }).fail(function(data) {
                    // eslint-disable-next-line no-console
                    console.log(data);
                });
            }
            if (
                courseSelect.value !== '' &&
                userSelect.value !== '' &&
                videoSelect.value !== '' &&
                chartType.value !== ''
            ) {
                for (let i = 0; i < response.length; i++) {
                    let obj = response[i];
                    if (
                        obj.courseid == courseSelectedValue &&
                        obj.userid == studentSelectedValue &&
                        obj.instanceid == videoSelectedValue) {
                        fetchRequest = obj;
                        break;
                    }
                }
                // eslint-disable-next-line no-unused-vars
                let requestData = {
                    values: {
                        courseId: courseSelectedValue,
                        userId: studentSelectedValue,
                        instanceId: videoSelectedValue,
                        resourceId: fetchRequest.resourceid,
                    }
                };
                let request = {
                    methodname: 'block_emotionanalysis_fetch_emotion_data',
                    args: requestData,
                };
                Ajax.call([request])[0].done(function(data) {
                    // eslint-disable-next-line no-console,no-unused-vars
                    const emotionCountsArray = data.emotioncounts.map(item => ({
                        count: item.countofemotion,
                        state: item.emotion_state
                    }));
                    // eslint-disable-next-line no-unused-vars
                    const [countOfSecond, totalDetectedEmotions] = updateEmotionCounts(emotionCountsArray);
                    generategraph(Object.entries(countOfSecond), sctx, chartSelectedType);
                    // eslint-disable-next-line no-unused-vars
                    let totalDuration = fetchRequest.totalduration;
                    createDataRectangles(data.formattedData);

                    // Clear the existing dataTable data for new generation
                    while (dataTable.rows.length > 0) {
                        dataTable.deleteRow(0);
                    }
                    // Create a new row for the dataTable headers
                    let headerRow = dataTable.insertRow();
                    // Create a single header cell for the additional information
                    let headerCell = headerRow.insertCell();
                    headerCell.classList.add('text-center');
                    headerCell.colSpan = 3;
                    headerCell.textContent = 'Additional Information';

                    // Create a new row for student name
                    let studentRow = dataTable.insertRow();

                    // Create cells for student name
                    let studentLabelCell = studentRow.insertCell();
                    studentLabelCell.textContent = 'Student Name';

                    let studentValueCell = studentRow.insertCell();
                    studentValueCell.textContent = fetchRequest.userFullName;

                    // Create a new row for activity start time
                    let startTimeRow = dataTable.insertRow();

                    // Create cells for activity start time
                    let startTimeLabelCell = startTimeRow.insertCell();
                    startTimeLabelCell.textContent = 'Activity Start Time';

                    let activityStartTime = timeStampConvertor(fetchRequest.activity_start_time);
                    let startTimeValueCell = startTimeRow.insertCell();
                    startTimeValueCell.textContent = activityStartTime;

                    // Create a new row for activity finish time
                    let finishTimeRow = dataTable.insertRow();

                    // Create cells for activity finish time
                    let finishTimeLabelCell = finishTimeRow.insertCell();
                    finishTimeLabelCell.textContent = 'Activity Finish Time';

                    let finishTimeValueCell = finishTimeRow.insertCell();
                    finishTimeValueCell.textContent = fetchRequest.activity_finish_time;

                    let resetActivityRow = dataTable.insertRow();
                    // Create cells for activity finish time
                    let resetActivityLabelCell = resetActivityRow.insertCell();
                    resetActivityLabelCell.textContent = 'Activity Reset Status';

                    let resetActivityValueCell = resetActivityRow.insertCell();
                    resetActivityValueCell.textContent = fetchRequest.reset_activity;

                    let progressStatusRow = dataTable.insertRow();
                    let progressValue = ((data.maxTimeStamp * 100) / fetchRequest.totalduration).toFixed(1);
                    let minutes = Math.floor(data.maxTimeStamp / 60);
                    let seconds = data.maxTimeStamp % 60;
                    let timeFormatted = minutes + ':' + seconds.toString().padStart(2, '0');
                    // Create cell for activity progress
                    let progressStatusLabelCell = progressStatusRow.insertCell();
                    progressStatusLabelCell.textContent = "Progress";
                    let progressStatusValueCell = progressStatusRow.insertCell();
                    progressStatusValueCell.textContent = progressValue + "%" + " (" + timeFormatted + " minutes)";

                    let activityTotalDuration = dataTable.insertRow();
                    // Creating cells for activity duration
                    let activityTotalDurationLabelCell = activityTotalDuration.insertCell();
                    activityTotalDurationLabelCell.textContent = 'Total Duration of Lecture';

                    let totalMinutes = Math.floor(fetchRequest.totalduration / 60);
                    let activityTotalDurationCellValue = activityTotalDuration.insertCell();
                    activityTotalDurationCellValue.textContent = totalMinutes + ' Minutes';

                    let totalNumberOfEmotions = dataTable.insertRow();
                    let totalNumberOfEmotionsLabelCell = totalNumberOfEmotions.insertCell();
                    totalNumberOfEmotionsLabelCell.textContent = 'Total Detected Number of Emotions';

                    let totalNumberOfEmotionValueCell = totalNumberOfEmotions.insertCell();
                    totalNumberOfEmotionValueCell.textContent = totalDetectedEmotions;
                    // Create a new row for the dataTable headers
                    let sessionRow = dataTable.insertRow();
                    let sessionInformation;
                    if (data.sessionData) {
                        sessionInformation = "Available";
                    }
                    let sessionCell = sessionRow.insertCell();
                    sessionCell.classList.add('text-center');
                    sessionCell.colSpan = 3;
                    sessionCell.textContent = 'Session Information ' + ' - ' + sessionInformation + ' Click Here';

                    $('#delete-button').prop('disabled', false);
                    let sessionTable = document.createElement('table');
                    sessionTable.classList.add('table', 'table-striped', 'table-bordered');

                    // Create the table headers
                    let sheaderRow = sessionTable.insertRow();
                    let idHeader = sheaderRow.insertCell();
                    idHeader.textContent = 'ID';
                    let startTimeHeader = sheaderRow.insertCell();
                    startTimeHeader.textContent = 'Session Start Time';
                    let finishTimeHeader = sheaderRow.insertCell();
                    finishTimeHeader.textContent = 'Session Finish Time';
                    let watchDurationHeader = sheaderRow.insertCell();
                    watchDurationHeader.textContent = 'Watch Duration';

                    data.sessionData.forEach(function (session, index) {
                        let sessionRow = sessionTable.insertRow();
                        let idCell = sessionRow.insertCell();
                        idCell.textContent = index + 1;
                        let startTimeCell = sessionRow.insertCell();
                        startTimeCell.textContent = timeStampConvertor(session.session_start_time);
                        let finishTimeCell = sessionRow.insertCell();
                        finishTimeCell.textContent = timeStampConvertor(session.session_finish_time);
                        let watchDurationCell = sessionRow.insertCell();
                        watchDurationCell.textContent = session.start_watch_time + ' - ' + session.finish_watch_time;
                    });
                    $(sessionCell).on('click', () => {
                        if (sessionTable.parentNode === document.body) {
                            document.body.removeChild(sessionTable);
                        } else {
                            document.body.appendChild(sessionTable);
                        }
                    });
                });
            }
        });

        /**
         * Ajax call for fetching the available data
         */
        function resultFetcher() {
            // eslint-disable-next-line no-unused-vars
            let UserId = $("#UsedIdVal").val();
            let request = {
                methodname: 'block_emotionanalysis_fetch_captured_data',
                args: {'UserId': UserId}
            };
            Ajax.call([request])[0].done(function(data) {
                response = data.fetchData;
                const courses = data.fetchData.map(item => ({
                    courseId: item.courseid,
                    courseName: item.coursename
                }));
                // Remove duplicate values
                const uniqueCourses = courses.reduce((unique, course) => {
                    const isCourseExist = unique.find(c => c.courseId === course.courseId && c.courseName === course.courseName);
                    if (!isCourseExist) {
                        unique.push(course);
                    }
                    return unique;
                }, []);
                // Populating the values into Select Element
                uniqueCourses.forEach(course => {
                    const option = document.createElement('option');
                    option.value = course.courseId;
                    option.textContent = course.courseName;
                    courseSelect.appendChild(option);
                });
                // Attach event listener to the course select element
                courseSelect.addEventListener('change', function(event) {
                    handleCourseSelection(event, data);
                });
                // Attach event listener to the course select element
                videoSelect.addEventListener('change', function(event) {
                    handleVideoSelection(event, data);
                });
            }).fail(function(data) {
                // eslint-disable-next-line no-console
                console.log(data);
            });
        }
        /**
         *@param {int} event id of the event for filtering
         *@param {array} data is response from ajax call
         */
        // Function to handle course selection
        /**
         * @param {int} event id of the event for filter of data
         * @param {array} data is response of ajax call
         */
        function handleCourseSelection(event, data) {
            updateSubmitButton();
            $('#delete-button').prop('disabled', true);
            $("#chartType").val('');
            const selectedCourseId = event.target.value;
            // Filter videos based on the selected course
            const filteredVideos = data.fetchData.filter(item => item.courseid == selectedCourseId);
            const videoSelect = document.getElementById('video-select');
            removeexistingvalues('video-select');
            // Populate the video select element
            const videoIds = [...new Set(filteredVideos.map(item => item.instanceid))];
            videoIds.forEach(videoId => {
                const video = filteredVideos.find(item => item.instanceid == videoId);
                const option = document.createElement('option');
                option.value = videoId;
                option.textContent = video.lectureTitle;
                videoSelect.appendChild(option);
            });
        }

// Function to handle video selection
        /**
         * @param {int} event id of the event for filter of data
         * @param {array} data is response of ajax call
         */
        function handleVideoSelection(event, data) {
            updateSubmitButton();
            $('#delete-button').prop('disabled', true);
            const selectedVideoId = event.target.value;
            const courseSelectedValue = document.getElementById('course-select').value;
            // Filter users based on the selected video and course
            // eslint-disable-next-line max-len
            const filteredUsers = data.fetchData.filter(item => item.instanceid == selectedVideoId && item.courseid == courseSelectedValue);
            const userSelect = document.getElementById('user-select');
            removeexistingvalues('user-select');
            // Populate the user select element
            const userIds = [...new Set(filteredUsers.map(item => item.userid))];
            userIds.forEach(userId => {
                const user = filteredUsers.find(item => item.userid == userId);
                const option = document.createElement('option');
                option.value = userId;
                option.textContent = user.userFullName;
                userSelect.appendChild(option);
            });
        }
        /**
         *@param {string} id of the select element
         */
        function removeexistingvalues(id) {
            // eslint-disable-next-line no-console
            const selectedElement = document.getElementById(id);
            if (id !== 'chartType') {
                if (selectedElement.options.length > 1) {
                    // Remove existing options
                    while (selectedElement.options.length > 1) {
                        selectedElement.options[1].remove();
                    }
                }
            }
        }
        // eslint-disable-next-line valid-jsdoc
        /**
         *@param {array} countsArray
         */
        function updateEmotionCounts(countsArray) {
            const emotion = {
                angry: 0,
                disgusted: 0,
                fearful: 0,
                happy: 0,
                neutral: 0,
                sad: 0,
                surprised: 0
            };
            let totalDetectedEmotions = 0;
            for (let i = 0; i < countsArray.length; i++) {
                const {count, state} = countsArray[i];
                if (emotion.hasOwnProperty(state)) {
                    emotion[state] = count;
                    totalDetectedEmotions += count;
                }
            }
            return [emotion, totalDetectedEmotions];
        }
        /**
         *Function to validate data
         */
        function updateSubmitButton() {
            /*if (courseSelect.value !== '' && userSelect.value !== '' && videoSelect.value !== '' && chartType.value !== '') {
                $('#submit-button').prop('disabled', false);
            } else {
                $('#submit-button').prop('disabled', true);
            }*/
        }
        /**
         *@param {array} input of emotions to generate chart
         *@param {string} ctx of graph area
         *@param {string} chartType of type of graph
         */
        function generategraph(input, ctx, chartType) {
            // eslint-disable-next-line no-eq-null
            if (ctx.id === 'schart' && semotionChart != null) {
                semotionChart.destroy();
                // eslint-disable-next-line no-eq-null
            }
            const newChart = new Chart(ctx, {
                type: chartType,
                data: {
                    labels: input.map(i => i[0].charAt(0).toUpperCase() + i[0].slice(1)),
                    datasets: [{
                        label: 'Count of Emotions',
                        data: input.map(i => i[1]),
                        borderWidth: 1,
                        backgroundColor: [
                            'rgba(255, 99, 132)',
                            'rgba(255, 159, 64)',
                            'rgba(255, 205, 86)',
                            'rgba(75, 192, 192)',
                            'rgba(54, 162, 235)',
                            'rgba(153, 102, 255)',
                            'rgba(201, 203, 207)'
                        ],
                    }]
                },
            });
            if (ctx.id === 'schart') {
                semotionChart = newChart;
            }
        }
        /**
         * Modal to ask for confirmation to delete the data
         */
        function confirmationModal() {
            ModalFactory.create({
                type: ModalFactory.types.SAVE_CANCEL,
                title: String.get_string('delete_record_confirmation', 'block_emotionanalysis'),
                body: String.get_string('delete_cofirmation_body', 'block_emotionanalysis'),
            }).then(function(modal) {
                modal.setSaveButtonText("Yes");
                let root = modal.getRoot();
                root.on(ModalEvents.save, function() {
                    let footer = Y.one('.modal-footer');
                    footer.setContent('Deleting...');
                    let spinner = M.util.add_spinner(Y, footer);
                    spinner.show();
                    deleteRecords();
                });
                modal.show();
            });
        }
        /**
         * Ajax Call for delete records
         * */
        function deleteRecords() {
            let deleteReqeustData = {
                values: {
                    courseId:  courseSelect.value,
                    userId: userSelect.value,
                    instanceId: videoSelect.value,
                    resourceId: fetchRequest.resourceid,
                }
            };
            let deleteRequest = {
                methodname: 'blocks_emotionanalysis_delete_records',
                args: deleteReqeustData,
            };
            // eslint-disable-next-line no-unused-vars
            Ajax.call([deleteRequest])[0].done(function(data) {
                window.location.reload();
                // eslint-disable-next-line no-unused-vars
            }).fail(function(data) {
                window.location.reload();
            });
        }
        /**
         *function to create datapoint
         * @param {array} formattedData
         */
        function createDataRectangles(formattedData) {
            const dataContainer = document.getElementById('data-container');
            const infoDisplay = document.getElementById('info-display');

            // eslint-disable-next-line no-unused-vars
            formattedData.forEach((data, index) => {
                // eslint-disable-next-line no-unused-vars
                const emotion = data.label.toLowerCase();
                const rectangle = document.createElement('div');
                const emoji = getEmoji(emotion);
                rectangle.className = 'data-rectangle ' + emotion;
                rectangle.innerHTML = `${emoji}`;

                // Add a mouseover event listener to display info in the fixed div
                rectangle.addEventListener('mouseover', () => {
                    infoDisplay.innerHTML = `Emotion: ${data.label}${getEmoji(emotion)} <br>Timestamp: ${data.value}`;
                    infoDisplay.style.backgroundColor = "skyblue";
                });

                // Add a mouseout event listener to hide the info when not hovering
                rectangle.addEventListener('mouseout', () => {
                    infoDisplay.innerHTML = '';
                    infoDisplay.style.backgroundColor = "";
                });

                dataContainer.appendChild(rectangle);
            });
        }
        /**
         *Function To Return Emoji
         * @param {string} emotion
         * @return {string} emojiMap
         */
        function getEmoji(emotion) {
            const emojiMap = {
                'happy': '\u{1F604}', // 😄
                'sad': '\u{1F622}', // 😢
                'angry': '\u{1F621}', // 😡
                'disgusted': '\u{1F92E}', // 🤮
                'surprised': '\u{1F632}', // 😲
                'fearful': '\u{1F628}', // 😨
                'neutral': '\u{1F610}',
            };

            // Return the emoji for the specified emotion, or a default emoji if not found
            return emojiMap[emotion] || '\u{1F610}'; // Use a neutral emoji as the default
        }

        // Format the timestamp to minutes:seconds
        /**
         *@param {int} timestampInSeconds
         */
        // eslint-disable-next-line no-unused-vars
        function formatTimestamp(timestampInSeconds) {
            const minutes = Math.floor(timestampInSeconds / 60);
            const seconds = timestampInSeconds % 60;
            return `${minutes}:${seconds.toString().padStart(2, '0')}`;
        }
        /**
         * function to seprate result from whole data
         *@param {array} existingData
         */
        function chartArray(existingData) {
            // Helper object to keep track of merged values
            const mergedMap = {};

            existingData.forEach(item => {
                item.emotions.forEach(emotion => {
                    const emotionState = emotion.emotion_state;
                    const countOfEmotion = emotion.count;

                    if (!mergedMap[emotionState]) {
                        mergedMap[emotionState] = {
                            emotion_state: emotionState,
                            countofemotion: 0,
                        };
                    }

                    mergedMap[emotionState].countofemotion += countOfEmotion;
                });
            });

            // Convert the mergedMap object back to an array
            const newData = Object.values(mergedMap);

            return newData;
        }
        /**
         *Function To mergeEmotions
         * @param {array} videoEmotionData
         */
        function mergeEmotions(videoEmotionData) {
            const mergedData = {};

            videoEmotionData.forEach((videoEmotion) => {
                const resourceid = videoEmotion.resourceid;
                const resourceTitle = videoEmotion.resourceTitle;
                const emotions = videoEmotion.emotions;

                if (!mergedData[resourceid]) {
                    mergedData[resourceid] = {
                        resourceid: resourceid,
                        resourceTitle: resourceTitle,
                        emotions: {
                            positive: 0,
                            neutral: 0,
                            negative: 0,
                        },
                    };
                }

                const mergedEmotions = mergedData[resourceid].emotions;

                emotions.forEach((emotion) => {
                    const emotionState = emotion.emotion_state;
                    const count = emotion.count;

                    // Categorize the emotion
                    const category = categorizeEmotion(emotionState);
                    if (category === "positive") {
                        mergedEmotions.positive += count;
                    } else if (category === "neutral") {
                        mergedEmotions.neutral += count;
                    } else if (category === "negative") {
                        mergedEmotions.negative += count;
                    }
                });
            });

            return Object.values(mergedData);
        }

        /**
         * @param {string} emotionState
         *function to categotize Emotion State
         */
        function categorizeEmotion(emotionState) {
            if (["happy", "surprised"].includes(emotionState)) {
                return "positive";
            } else if (emotionState === "neutral") {
                return "neutral";
            } else {
                return "negative";
            }
        }
        // Function to create an emotion bar
        /**
         *@param {string} container
         * @param {string} type
         * @param {int} count
         */
        // eslint-disable-next-line no-unused-vars
        function createEmotionSegment(container, type, count) {
            const segment = document.createElement('div');
            segment.className = `emotion-segment ${type}`;
            segment.style.width = `${count}%`;
            container.appendChild(segment);
        }
    });

/**
 * @param {int} unixTimeStamp input unix timestamp
 * @returns {string} readable timestamp
 */
function timeStampConvertor(unixTimeStamp) {
    const date = new Date(unixTimeStamp * 1000);

    // Extract the different components of the date and time
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0'); // Zero-padding for single-digit months
    const day = String(date.getDate()).padStart(2, '0'); // Zero-padding for single-digit days
    const hours = String(date.getHours()).padStart(2, '0'); // Zero-padding for single-digit hours
    const minutes = String(date.getMinutes()).padStart(2, '0'); // Zero-padding for single-digit minutes
    const seconds = String(date.getSeconds()).padStart(2, '0'); // Zero-padding for single-digit seconds
    // Construct the readable timestamp
    const readableTimestamp = `${day}-${month}-${year} ${hours}:${minutes}:${seconds}`;
    return readableTimestamp;
}
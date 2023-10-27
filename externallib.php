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
 * Ajax call for the emotionanalysis block
 *
 * @copyright 2023 Rohit
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @package   block_emotionanalysis
 */

defined('MOODLE_INTERNAL') || die;
global $CFG;
require_once($CFG->libdir . "/externallib.php");
class block_emotionanalysis_external extends external_api
{
    /**
     * Returns description of method parameters
     * @return external_function_parameters
     */
    public static function store_emotion_parameters()
    {
        return new external_function_parameters([
            'values' => new external_single_structure([
                'emotionState' => new external_value(PARAM_TEXT, 'Current expression state'),
                'videoTimeStamp' => new external_value(PARAM_FLOAT, 'Current facial expression timestamp on video lecture'),
                'courseId' => new external_value(PARAM_INT, 'Current Course id'),
                'resourceId' => new external_value(PARAM_INT, 'Resrouce id associated with the video lecture'),
                'instanceId' => new external_value(PARAM_INT, 'Instance id to make sure correct data is entered realted with the video'),
                'totalDuration' => new external_value(PARAM_INT, 'Total Duration of the lecture to measure completion progress'),
                'videoWatchTime' => new external_value(PARAM_INT, 'Contains the starting time where user start video'),
                'gender' => new external_value(PARAM_TEXT, 'detected gender'),
                'ageGroup' => new external_value(PARAM_FLOAT, 'age group')
            ])
        ]);
    }

    /**
     * Validate Captured data and store into database
     * @throw dml_exception
     * return true if data is inserted successfully
     * @throws dml_exception
     * @throws moodle_exception
     */
    public static function store_emotion($values){
        require_login();
        global $DB, $USER;
        $loggedInUser = intval($USER->id);
        $currentTimeStamp = $values['videoTimeStamp'];
        self::validate_parameters(self::store_emotion_parameters(), ['values' => $values]);
        $dataExists =false;
        // setting cache to false assuming data is not exists in cache
        $timeStamp = time();
        $cache = cache::make('block_emotionanalysis', 'coursemodules');
        $data = $cache->get('course_module_data');
        if (!$data) {
            // Fetching latest records of course_modules and setting in cache
            $updatedData = $DB->get_records('course_modules');
            //Updating the cache
            $cache->set('course_module_data', $updatedData);
            $data = $cache->get('course_module_data');
            // Checking in cache if values exists in moodle
            $dataExists = checkDataExistence($data, $values);
            if (!$dataExists) {
                throw new moodle_exception(get_string('errorMsg', 'block_emotionanalysis') . ' 1001MNFID');
            }
        } else {
            // Assuming cache is already set checking in cache for validation
            $dataExists = checkDataExistence($data, $values);
            // Not found in cache checking in database and updating the cache
            if(!$dataExists)
            {
                $course_module = $DB->get_record('course_modules', [
                    'course' => $values['courseId'],
                    'instance' => $values['instanceId'],
                    'id' => $values['resourceId']
                ]);
                // if found in database updating the cache
                if($course_module)
                {
                    $updatedData  = $DB->get_records('course_modules');
                    $cache->set('course_module_data',$updatedData);
                } else
                {
                    throw new moodle_exception(get_string('errorMsg','block_emotionanalysis') .' 1001MNFID');
                }
            }
        }
        //Validation of user access
        if (!has_capability('mod/quiz:attempt', context_course::instance($values['courseId']), $loggedInUser)) {
            throw new moodle_exception(get_string('errorMsg', 'block_emotionanalysis') . ' ErrorCode - 1002UNEIC');
        }
        //validation of EmotionState
        $validEmotions = ['happy', 'sad', 'angry', 'surprised', 'fearful', 'neutral', 'disgusted'];
        $emotionstate = strtolower($values['emotionState']);
        if (!in_array($emotionstate, $validEmotions)) {
            throw new moodle_exception(get_string('errorMsg', 'block_emotionanalysis') . ' ErrorCode - 1003ESNT');
        }
        // Cache to check the timestamp shouldn't exceed the total Duration
        $totalDurationCache = 'totalDuration' . '_' . $values['courseId'] . '_' . $values['resourceId'] . '_' . $values['instanceId'];
        $dataCache = cache::make('block_emotionanalysis', 'datavalidation');
        $totalDurationTime = $dataCache->get($totalDurationCache);
        if (!$totalDurationTime) {
            $dataCache->set($totalDurationCache, $values['totalDuration']);
            $totalDurationTime = $dataCache->get($totalDurationCache);
        }

        if ($currentTimeStamp < $totalDurationTime) {
            $activity_finish_date = null;
        } else if ($currentTimeStamp == $totalDurationTime) {
            $activity_finish_date = time();
        } else {
            throw new moodle_exception(get_string('errorMsg', 'block_emotionanalysis') . ' ErrorCode : 1009TSNV');
        }
        $existingRecord = check_existing_record($loggedInUser, $values);
        if (!$existingRecord && !is_null($activity_finish_date)) {
            throw new moodle_exception(get_string('errorMsg', 'block_emotionanalysis') . ' ErrorCode : 1010ASFTS');
        } elseif (!$existingRecord && is_null($activity_finish_date)) {
            $gender = mapGenderToNumber($values['gender']);
            $ageGroup = mapAgeToGroup($values['ageGroup']);
            $datatoinsert = new stdClass();
            $datatoinsert->user_id = $loggedInUser;
            $datatoinsert->course_id = $values['courseId'];
            $datatoinsert->instance_id = $values['instanceId'];
            $datatoinsert->resource_id = $values['resourceId'];
            $datatoinsert->total_duration = $values['totalDuration'];
            $datatoinsert->activity_start_date = $timeStamp;
            $datatoinsert->gender = $gender;
            $datatoinsert->age_group = $ageGroup;
            $insertedrecords = $DB->insert_record('block_ea_user_records_holder', $datatoinsert);

            if ($insertedrecords) {
                $user_record_id = $insertedrecords;
            } else {
                throw new moodle_exception(get_string('errorMsg', 'block_emotionanalysis') . ' ErrorCode : 1008EIPID');
            }
        } else {
            $user_record_id = $existingRecord->id;
        }
        if ($user_record_id) {
            $emotiondata = new stdClass();
            $emotiondata->user_record_id = $user_record_id;
            $emotiondata->emotion_state = $emotionstate;
            $emotiondata->video_timestamp = $values['videoTimeStamp'];

            if ($DB->insert_record('block_emotionanalysis', $emotiondata)) {
                if ($activity_finish_date) {
                    $existingRecord->activity_finish_date = $activity_finish_date;
                    $DB->update_record('block_ea_user_records_holder', $existingRecord);
                }
            } else {
                throw new moodle_exception(get_string('errorMsg', 'block_emotionanalysis') . ' ErrorCode : 1008EIPID');
            }
            if ($values['videoTimeStamp'] > $values['videoWatchTime']) {
                $timeStampCalculator = $values['videoTimeStamp'] - $values['videoWatchTime'];

                $sessionStartTime = $timeStamp - $timeStampCalculator;
            } else {
                $sessionStartTime = $timeStamp;
            }
            // Unique page identifier for checking if the same page is active or not
            $pageIdentifier = 'PageIdentifier' . '_' . $values['courseId'] . '_' . $values['resourceId'] . '_' . $values['instanceId'] . $user_record_id;
            $sessionStartCache = $pageIdentifier.'_sessionStartTime';
            $sessionFinishCache = $pageIdentifier.'_sessionFinishTime';
            $lastFinishSession = $dataCache->get($sessionFinishCache);
            $break = 300;
            if(!$dataCache->get($pageIdentifier)) {
                if($break > ($sessionStartTime - $lastFinishSession))
                {
                    $dataCache->set($pageIdentifier,true);
                }
                else {
                $sessionData = new stdClass();
                $sessionData->user_record_id = $user_record_id;
                $sessionData->session_start_time = $sessionStartTime;
                $sessionData->start_watch_time = $values['videoWatchTime'];
                $lastRecordId =  $DB->insert_record('block_ea_session_tracker', $sessionData);
                    if ($lastRecordId)
                    {

                        $dataCache->set($sessionStartCache,$sessionStartTime);
                        $dataCache->set($pageIdentifier,true);
                    } else {
                        throw new moodle_exception("Something Went Wrong While Inserting Session Data");
                    }
                }
            }
            else
            {
                return true;
            }
        } else
        {
            throw new moodle_exception("Something Went Wrong Please Contact Support");
        }
    }
    /**
     * Returns description of method
     * @return external_description
     */
    public static function store_emotion_returns(){
        return new external_value(PARAM_BOOL,'TRUE IF NOT ERROR IN INSERTING DATA');
    }
    /**
     * Returns description of method parameters
     * @return external_function_parameters
     */
    public static function activity_checker_parameters(){
        return new external_function_parameters([
            'values' => new external_single_structure([
                'courseId' => new external_value(PARAM_INT,'Id of course'),
                'resourceId' => new external_value(PARAM_INT,'Id of the resource'),
                'instanceId' => new external_value(PARAM_INT,'Id of the instance'),
                'deleteConfirmation' => new external_value(PARAM_INT,'DeleteConfirmation flag')
            ])
        ]);
    }
    /**
     * Return the activity checker with the last timestamp
     * @return {timestamp} of the video where user left watching
     * @return {deleteConfirmation} value also
     * @throws Exception
     */
    public static function activity_checker($values){
    require_login();
    self::validate_parameters(self::activity_checker_parameters(),['values' => $values]);
    global $DB, $USER;
    $loggedInUser = $USER->id;
    $maxTimeStamp = null;
    $courseModuleCache = cache::make('block_emotionanalysis','coursemodules');
    $data = $courseModuleCache->get('course_module_data');
    $deleteConfirmation  = $values['deleteConfirmation'];
    // Checking the courseModuleCache is set or not
    if(!$data) {
    // Fetching latest records of course_modules and setting in courseModuleCache
    $updatedData  = $DB->get_records('course_modules');
    $courseModuleCache->set('course_module_data',$updatedData);

    //updating the value of courseModuleCache
    $data = $courseModuleCache->get('course_module_data');

    // Checking in courseModuleCache if values exists in moodle
    $dataExist = checkDataExistence($data,$values);

    if(!$dataExist) {
        throw new moodle_exception(get_string('errorMsg','block_emotionanalysis') .' 1001MNFID');
    }
    } else {
    // Assuming courseModuleCache is already set,checking in courseModuleCache for validation
    $dataExist = checkDataExistence($data,$values);
    // Not found in courseModuleCache checking in database and updating the courseModuleCache
    if(!$dataExist)
    {
        $course_module = $DB->get_record('course_modules', [
            'course' => $values['courseId'],
            'instance' => $values['instanceId'],
            'id' => $values['resourceId']
        ]);

        // if found in database updating the courseModuleCache
        if($course_module)
        {
            $updatedData  = $DB->get_records('course_modules');
            $courseModuleCache->set('course_module_data',$updatedData);
        } else
        {
            throw new moodle_exception(get_string('errorMsg','block_emotionanalysis') .' 1001MNFID');
        }
    }
    }
    $existingRecord = check_existing_record($loggedInUser,$values);
    if($existingRecord && $deleteConfirmation === 0 ){
    $user_record_id = $existingRecord->id;


        // Unique page identifier for checking if the same page is active or not
        $pageIdentifier = 'PageIdentifier' . '_' . $values['courseId'] . '_' . $values['resourceId'] . '_' . $values['instanceId'] . $user_record_id;
        $sessionFinishCache = $pageIdentifier.'_sessionFinishTime';
        $lastSessionData = $DB->get_record_sql(
            "SELECT session_start_time, start_watch_time, finish_watch_time, session_finish_time 
     FROM {block_ea_session_tracker} 
     WHERE user_record_id = :user_record_id 
     ORDER BY session_finish_time DESC 
     LIMIT 1",
            ['user_record_id' => $user_record_id]
        );
        $last_finish_time = $lastSessionData->finish_watch_time;
        if($last_finish_time === null)
        {
            // Select the maximum timestamp from the table for resume the activity
            $maxTimeStamp = $DB->get_field('block_emotionanalysis', 'MAX(video_timestamp)', ['user_record_id' => $user_record_id]);
            $sessionFinishTime = $lastSessionData->session_start_time + $maxTimeStamp;

            // Update the database
            $DB->execute(
                "UPDATE {block_ea_session_tracker} 
     SET session_finish_time = :new_session_finish_time, finish_watch_time = :new_finish_watch_time 
     WHERE session_start_time = :session_start_time 
     AND start_watch_time = :start_watch_time 
     AND user_record_id = :user_record_id",
                [
                    'new_session_finish_time' => $sessionFinishTime, // Replace with the new value for session_finish_time.
                    'new_finish_watch_time' => $maxTimeStamp,       // Replace with the new value for finish_watch_time.
                    'session_start_time' => $lastSessionData->session_start_time,
                    'start_watch_time' => $lastSessionData->start_watch_time,
                    'user_record_id' => $user_record_id
                ]
            );
            $dataCache = cache::make('block_emotionanalysis','datavalidation');
            $dataCache->set($sessionFinishCache,$sessionFinishTime);
            $dataCache->set($pageIdentifier,false);
        } else
        {
            $maxTimeStamp = $last_finish_time;
        }

        if($maxTimeStamp == null ) {
            return ['timestamp' => null,'deleted' => false , 'activity_finish_date' => null,];
        } else  {
            return ['timestamp' => $maxTimeStamp, 'deleted' => false, 'activity_finish_date' => $existingRecord->activity_finish_date];
        }
    } elseif (!$existingRecord && $deleteConfirmation === 0){
        return ['timestamp' => null, 'deleted' => true, 'activity_finish_date' => null ];
    } else if ($deleteConfirmation === 1) {
        $existingRecord = check_existing_record($loggedInUser,$values);
        if(!$existingRecord)
        {
            throw new moodle_exception(get_string('ErrorMsg', 'block_emotionanalysis' . ' ErrorCode : 1007NRFT'));
        }

        $transactions  = $DB->start_delegated_transaction();

        $delete_emotion_data = $DB->delete_records('block_emotionanalysis', [
            'user_record_id' => $existingRecord->id]);

        $delete_session_data = $DB->delete_records('block_ea_session_tracker', [
            'user_record_id' => $existingRecord->id]);

        $existingRecord->reset_activity += 1;
        $existingRecord->activity_finish_date = null;
        $update_data = $DB->update_record('block_ea_user_records_holder', $existingRecord);

        if($delete_emotion_data && $delete_session_data && $update_data )
        {
            $DB->commit_delegated_transaction($transactions);
            return ['timestamp' => null, 'deleted' => true, 'activity_finish_date' => null ];
        }
    } else {
        throw new moodle_exception(get_string('errorMsg', 'block_emotionanalysis') . ' ErrorCode : 1006PAFE');
        }
    }

    /**
     * Returns description of method return value
     * @return external_description
     */
    public static function activity_checker_returns(){
        return new external_single_structure([
            'timestamp' => new external_value(PARAM_INT,'Last timestamp to check if the student has the activity started or not'),
            'deleted' => new external_value(PARAM_BOOL,'Status of record remove'),
            'activity_finish_date' => new external_value(PARAM_TEXT, 'Activity finish date')
        ]);
    }
    /**
     * Returns description of method parameters
     * @return external_function_parameters
     */
    public static function fetch_data_parameters(){
        return new external_function_parameters(
            ['UserId' => new external_value(PARAM_INT,'User id')],
        );
    }
    /**
     * Returns description of method parameters
     * @return array[] of  fetched data stored in table
     */
    public static function fetch_data($UserId){
        global $DB,$USER;
        $cUserId = intval($USER->id);
        self::validate_parameters(self::fetch_data_parameters(), array('UserId' => $UserId));
        if ($cUserId !== $UserId){
            throw new moodle_exception(get_string('errorMsg','block_emotionanalysis') . 'ErrorCode : 1004UUI');
        } else {
            $results = check_enrolled_courses($UserId);

            $fetchData = fetch_data_from_table($results);

            return ['fetchData' => $fetchData];
        }
    }
    /**
     * Returns
     * @return external_single_structure description
     */
    public static function fetch_data_returns() {
        return new external_single_structure([
            'fetchData' => new external_multiple_structure(
                new external_single_structure([
                    'id' => new external_value(PARAM_INT,'User_record_id'),
                    'userid' => new external_value(PARAM_INT,'Id of the student'),
                    'userFullName' => new external_value(PARAM_TEXT,'Name of the student'),
                    'instanceid' => new external_value(PARAM_INT,'instance id'),
                    'lectureTitle' => new external_value(PARAM_TEXT,'Lecture Title'),
                    'courseid' => new external_value(PARAM_INT,'Course id'),
                    'coursename' => new external_value(PARAM_TEXT,'Course Name'),
                    'resourceid' => new external_value(PARAM_INT,'Resource id '),
                    'totalduration' => new external_value(PARAM_INT,'Total video duration'),
                    'activity_start_time' => new external_value(PARAM_TEXT,'Activity start date'),
                    'activity_finish_time' => new external_value(PARAM_TEXT,'Activity finish date'),
                    'reset_activity' => new external_value(PARAM_INT,'Activity reset count'),
                    'record_analyze' => new external_value(PARAM_INT,'Activity result analyze count'),
                ])
            )
        ]);
    }
    /**
     * Returns description of method parameters
     * @return external_function_parameters
     */
    public static function course_result_parameters(){
        return new external_function_parameters([
            'values' => new external_single_structure([
                'courseId' => new external_value(PARAM_INT,'Id of course'),
            ])
        ]);
    }
    /**
     * Fetch the Course Result
     * @param {int} $values contains Courseid
     */
    public static function course_result($values){
        global $DB,$USER;
        self::validate_parameters(self::course_result_parameters(),['values' => $values]);
        $courseId = $values['courseId'];
        // Condition to check the requested User is teacher or Student
        if(has_capability('moodle/course:manageactivities',context_course::instance($courseId), $USER->id)) {
            $sql = "SELECT id,resource_id,instance_id FROM {block_ea_user_records_holder} WHERE course_id = :courseid";
            $params = ['courseid' => $courseId];
            $allRecordsId = $DB->get_records_sql($sql, $params);
            if($allRecordsId)
            {
                foreach ($allRecordsId as $key => $data) {
                    // Fetch module data based on Course Id, Instance Id, and Resource Id
                    $moduleData = $DB->get_record('course_modules', ['course' => $courseId, 'instance' => $data->instance_id, 'id' => $data->resource_id]);

                    // Update the 'Module' field in the $courseData array
                    $allRecordsId[$key]->module = isset($moduleData->module) ? $moduleData->module : '';
                }
                foreach($allRecordsId as $recordId) {
                    if($recordId->module == 19)
                    {
                        //Fetching Lecture Name
                        $lectureTitles = $DB->get_record('resource', ['id' => $recordId->instance_id], 'name');
                        $lectureTitle = $lectureTitles->name;
                    } else if($recordId->module == 17)
                    {
                        //Fetching Lecture Name
                        $lectureTitles = $DB->get_record('page', ['id' => $recordId->instance_id, 'course' => $courseId], 'name');
                        $lectureTitle = $lectureTitles->name;
                    }
                    $courseData[] = array(
                        'Id' => $recordId->id,
                        'resourceId' => $recordId->resource_id,
                        'resourceTitle' => $lectureTitle
                    );
                }
            }
            $videoEmotions = [];

            // Emotion State and Count of Emotion for All Videos
            foreach($courseData as $cData)
            {
                $emotions = [];
                $video = $cData['Id'];
                $resourceId = $cData['resourceId'];
                $resourceTitle = $cData['resourceTitle'];
                $sql = "SELECT emotion_state, COUNT(*) AS count
            FROM {block_emotionanalysis}
            WHERE user_record_id =  ?
            GROUP BY emotion_state";

                $params = [$cData['Id']];
                $emotionsData = $DB->get_records_sql($sql, $params);

                foreach ($emotionsData as $emotion) {
                    // Store each emotion and its count in the array
                    $emotions[] = [
                        'emotion_state' => $emotion->emotion_state,
                        'count' => $emotion->count,
                    ];
                }
                $videoEmotions[] = [
                    'video_id' => $video,
                    'resourceid' => $resourceId,
                    'resourceTitle' => $resourceTitle,
                    'emotions' => $emotions,
                ];
            }

            // Sql to Check the Active Student in the Course
            $sql = "SELECT COUNT(DISTINCT user_id) AS user_count
                        FROM {block_ea_user_records_holder}";
            $totalNumberofStudents = $DB->count_records_sql($sql);

            // Code to Get the Emotions of All activites Together
            $studentIds = array_column($courseData, 'Id'); // Extract 'id' values into a simple array
            $placeholders = implode(',', array_fill(0, count($studentIds), '?')); // Create placeholders
            $sql = "SELECT emotion_state, COUNT(emotion_state) AS countofemotion
        FROM {block_emotionanalysis}
        WHERE user_record_id IN ($placeholders) 
        GROUP BY emotion_state";

            $params = $studentIds; // Pass the array of student IDs as parameters

            $emotionsCount = $DB->get_records_sql($sql, $params);
            foreach($emotionsCount as $emotionCount)
            {
                $countoff_arr[] = $emotionCount;
            }

            // Sql to get the Total Student Enrolled in Course
            $sql = "SELECT COUNT(DISTINCT u.id)
        FROM {user} u
        JOIN {user_enrolments} ue ON u.id = ue.userid
        JOIN {enrol} e ON ue.enrolid = e.id
        JOIN {role_assignments} ra ON u.id = ra.userid
        JOIN {context} ctx ON ra.contextid = ctx.id
        JOIN {role} r ON ra.roleid = r.id
        WHERE e.courseid = :courseid
        AND ue.status = 0
        AND ctx.contextlevel = :contextlevel
        AND r.shortname = 'student'"; // Exclude users with the 'student' role

            $params = [
                'courseid' => $courseId,
                'contextlevel' => CONTEXT_COURSE, // Use CONTEXT_COURSE to filter course-level context
            ];
            $enrolledStudentsCount = $DB->count_records_sql($sql, $params);

            // Sql to Check how many Student finished the Activity
            $finishActivity =$DB->count_records_sql("SELECT COUNT(*) FROM {block_ea_user_records_holder} WHERE activity_finish_date IS NOT NULL");
            return array(
                'flag' => true,
                'userName' => null,
                'totalStudents' => $totalNumberofStudents,
                'enrolledStudentsCount' => $enrolledStudentsCount,
                'finishActivity' => $finishActivity,
                'videoEmotion' => $videoEmotions

            );
        }
        elseif (has_capability('mod/quiz:attempt',context_course::instance($values['courseId']), $USER->id))
        {

            $userId = $USER->id;
            $params = ['userid' => $userId , 'courseid' => $courseId];
            $sql = 'SELECT COUNT(activity_finish_date) FROM {block_ea_user_records_holder} WHERE user_id =:userid AND course_id = :courseid';
            $finishActivity = $DB->count_records_sql($sql,$params);
            $sql = 'SELECT id,resource_id,instance_id FROM {block_ea_user_records_holder} WHERE user_id = :userid AND course_id = :courseid';
            $allRecordsId = $DB->get_records_sql($sql, $params);
            if($allRecordsId)
            {
                foreach ($allRecordsId as $key => $data) {
                    // Fetch module data based on Course Id, Instance Id, and Resource Id
                    $moduleData = $DB->get_record('course_modules', ['course' => $courseId, 'instance' => $data->instance_id, 'id' => $data->resource_id]);

                    // Update the 'Module' field in the $courseData array
                    $allRecordsId[$key]->module = isset($moduleData->module) ? $moduleData->module : '';
                }
                foreach($allRecordsId as $recordId) {
                    if($recordId->module == 19)
                    {
                        //Fetching Lecture Name
                        $lectureTitles = $DB->get_record('resource', ['id' => $recordId->instance_id], 'name');
                        $lectureTitle = $lectureTitles->name;
                    } else if($recordId->module == 17)
                    {
                        //Fetching Lecture Name
                        $lectureTitles = $DB->get_record('page', ['id' => $recordId->instance_id, 'course' => $courseId], 'name');
                        $lectureTitle = $lectureTitles->name;
                    }
                    $courseData[] = array(
                        'Id' => $recordId->id,
                        'resourceId' => $recordId->resource_id,
                        'resourceTitle' => $lectureTitle
                    );
                }
            }
            $videoEmotions = [];

            // Emotion State and Count of Emotion for All Videos
            foreach($courseData as $cData)
            {
                $emotions = [];
                $video = $cData['Id'];
                $resourceId = $cData['resourceId'];
                $resourceTitle = $cData['resourceTitle'];
                $sql = "SELECT emotion_state, COUNT(*) AS count
            FROM {block_emotionanalysis}
            WHERE user_record_id =  ?
            GROUP BY emotion_state";

                $params = [$cData['Id']];
                $emotionsData = $DB->get_records_sql($sql, $params);

                foreach ($emotionsData as $emotion) {
                    // Store each emotion and its count in the array
                    $emotions[] = [
                        'emotion_state' => $emotion->emotion_state,
                        'count' => $emotion->count,
                    ];
                }
                $videoEmotions[] = [
                    'video_id' => $video,
                    'resourceid' => $resourceId,
                    'resourceTitle' => $resourceTitle,
                    'emotions' => $emotions,
                ];
            }

            return array(
                'flag' => false,
                'userName' => $USER->firstname. ' ' . $USER->lastname,
                'totalStudents' => 0,
                'enrolledStudentsCount' => 0,
                'finishActivity' => $finishActivity,
                'videoEmotion' => $videoEmotions
            );
        }
    }
    /**
     *Return coursedata with the Id available
     */
    public static function course_result_returns(){
        return new external_single_structure([
            'flag' => new external_value(PARAM_BOOL,'Teacher flag'),
            'userName' => new external_value(PARAM_TEXT,'Name of the User'),
            'totalStudents' => new external_value(PARAM_INT,'Total Number of Students'),
            'enrolledStudentsCount' => new external_value(PARAM_INT,'Total Enrolled Students'),
            'finishActivity' => new external_value(PARAM_INT,'Total Number of Students Finished The Activity'),
            'videoEmotion' => new external_multiple_structure(
                new external_single_structure([
                    'video_id' => new external_value(PARAM_INT, 'Identification of Id'),
                    'resourceid' => new external_value(PARAM_TEXT, 'Resource ID'),
                    'resourceTitle' => new external_value(PARAM_TEXT,'Resource Name'),
                    'emotions' => new external_multiple_structure(
                        new external_single_structure([
                            'emotion_state' => new external_value(PARAM_TEXT, 'Emotion State'),
                            'count' => new external_value(PARAM_INT,'count of emotion')
                        ])
                    )
                ])
            ),
        ]);
    }
    /**
     * Returns description of method parameters
     * @return external_function_parameters
     */
    public static function fetch_emotions_parameters(){
        return new external_function_parameters([
            'values' => new external_single_structure([
                'courseId' => new external_value(PARAM_INT,'Selected course id'),
                'userId' => new external_value(PARAM_INT,'Select Student Id'),
                'instanceId' => new external_value(PARAM_INT,'Instance id for verification of Video Lecture'),
                'resourceId' => new external_value(PARAM_INT,'Resource Id for video details')
            ])
        ]);
    }
    /**
     * Returns description of method parameters
     * @return array|bool
     */
    public static function fetch_emotions($values){
        require_login();
        global $DB,$USER;
        self::validate_parameters(self::fetch_emotions_parameters(), array('values' => $values));
        $courseId = $values['courseId'];
        // logged-in user to check the authentication
        $cUserId = $USER->id;
        // Check if the user has teacher rights
        if (has_capability('moodle/course:manageactivities', context_course::instance($courseId), $cUserId) ||
            (has_capability('mod/quiz:attempt',context_course::instance($courseId), $cUserId) && $cUserId == $values['userId'])) {
            // Validation of received data
            $existingRecord = $DB->get_record('block_ea_user_records_holder', [
                'user_id' => $values['userId'],
                'instance_id' => $values['instanceId'],
                'resource_id' => $values['resourceId'],
                'course_id' => $values['courseId'],
            ]);
            if(!$existingRecord)
            {
                throw new moodle_exception(get_string('errorMsg', 'block_emotionanalysis') . ' ErrorCode : 1005VORDF');
            } else {
                $user_record_id = $existingRecord->id;

                $params = ['user_record_id' => $user_record_id];

                $sql = "SELECT emotion_state ,
                     COUNT(emotion_state) AS countofemotion
                FROM {block_emotionanalysis}
                WHERE user_record_id = :user_record_id 
                GROUP BY emotion_state";

                // Prepare the SQL query to fetch data from the table
                $emotionTimeLineData = "SELECT * FROM {block_emotionanalysis} WHERE user_record_id = :user_record_id";

                // Execute the query using Moodle's database API
                $timeLineResults = $DB->get_records_sql($emotionTimeLineData, $params);

                // Prepare an array to store the formatted data
                $formattedData = [];

                // Loop through the result set and format the data
                foreach ($timeLineResults as $timeLineResult) {
                    // Convert the emotion_state and video_timestamp to desired format
                    $label = ucfirst($timeLineResult->emotion_state);
                    $value = $timeLineResult->video_timestamp;

                    // Add the formatted data to the array
                    $formattedData[] = ['label' => $label, 'value' => $value];
                }
                $sessionSql = 'SELECT 
                                session_start_time,session_finish_time,start_watch_time,finish_watch_time 
                                FROM {block_ea_session_tracker}
                                WHERE user_record_id = :user_record_id';
                $sessionsData = $DB->get_records_sql($sessionSql, $params);
                $emotions = $DB->get_records_sql($sql, $params);
                // Select the maximum timestamp from the table
                $maxTimeStamp = $DB->get_field('block_ea_session_tracker', 'MAX(finish_watch_time)', ['user_record_id' => $user_record_id]);
                if(!empty($sessionsData))
                {
                    foreach($sessionsData as $data)
                    {
                        $sessionData[] = $data;
                    }
                }
                if(empty($emotions)){
                    return array(
                        'emotioncounts' => array(
                            array(
                                'countofemotion' => 0,
                                'emotion_state' => 0
                            )
                        ),
                        'maxTimeStamp' => $maxTimeStamp,
                        'sessionData' => [],
                        'formattedData' => $formattedData,
                    );
                } else {
                    foreach($emotions as $emotion) {
                        $countoff_arr[] = $emotion;
                    }
                    return array(
                        'emotioncounts' => $countoff_arr,
                        'maxTimeStamp' => $maxTimeStamp,
                        'sessionData' => $sessionData,
                        'formattedData' => $formattedData,
                    );
                }
            }
        }
        else {
            throw new moodle_exception(get_string('errorMsg', 'block_emotionanalysis') . ' ErrorCode : 1004UUI');
        }
    }
    /**
     * * Returns array of emotionstate with count
     */
    public static function fetch_emotions_returns(){
        return new external_single_structure([
            'emotioncounts' => new external_multiple_structure(
                new external_single_structure([
                    'countofemotion' => new external_value(PARAM_INT, 'count of each emotion state'),
                    'emotion_state' => new external_value(PARAM_TEXT, 'Emotion State'),
                ])
            ),
            'maxTimeStamp' => new external_value(PARAM_INT,'Max Time Stamp watched'),
            'sessionData' => new external_multiple_structure(
                new external_single_structure([
                    'session_start_time' => new external_value(PARAM_INT, 'starting Time of session'),
                    'session_finish_time' => new external_value(PARAM_INT,'finish time of session'),
                    'start_watch_time' => new external_value(PARAM_INT,'video Start Time Stamp'),
                    'finish_watch_time' => new external_value(PARAM_INT,'video finish Time Stamp')
                ])
            ),
            'formattedData' => new external_multiple_structure(
                new external_single_structure([
                    'label' => new external_value(PARAM_TEXT,'Emotion Label'),
                    'value' => new external_value(PARAM_INT,'Timestamp of emotion in video'),
                ])
            )
        ]);
    }
    /**
     * Returns description of method parameters
     * @return external_function_parameters
     */
    public static function session_tracker_parameters(){
        return new external_function_parameters([
            'values' => new external_single_structure([
                'courseId' => new external_value(PARAM_INT,'Selected course id'),
                'lastActiveTime' => new external_value(PARAM_INT,'Select Student Id'),
                'instanceId' => new external_value(PARAM_INT,'Instance id for verification of Video Lecture'),
                'resourceId' => new external_value(PARAM_INT,'Resource Id for video details')
            ])
        ]);
    }
    /**
     * Tracking Session for a student
     */
    public static function session_tracker($values){
        require_login();
        global $DB,$USER;
        $userId = $USER->id;
        self::validate_parameters(self::session_tracker_parameters(), array('values' => $values));
        if (!has_capability('mod/quiz:attempt', context_course::instance($values['courseId']), $userId)) {
            throw new moodle_exception(get_string('errorMsg', 'block_emotionanalysis') . ' ErrorCode - 1002UNEIC');
        } else {
            // Define the conditions
            $conditions = array(
                'course_id' => $values['courseId'],
                'instance_id' => $values['instanceId'],
                'user_id' => $userId,
                'resource_id' => $values['resourceId']
            );
            // Retrieve the record
            $record = $DB->get_record('block_ea_user_records_holder', $conditions);
            if($record)
            {
                $user_record_id = $record->id;
            }
            else
            {
                throw new moodle_exception("Something went wrong");
            }
            // Unique page identifier for checking if the same page is active or not
            $pageIdentifier = 'PageIdentifier' . '_' . $values['courseId'] . '_' . $values['resourceId'] . '_' . $values['instanceId'] . $user_record_id;
            $sessionStartCache = $pageIdentifier.'_sessionStartTime';
            $sessionFinishCache = $pageIdentifier.'_sessionFinishTime';
            $dataCache = cache::make('block_emotionanalysis','datavalidation');
            $sessionStartTime = $dataCache->get($sessionStartCache);
            if ($pageIdentifier)
            {
                $sessionData = $DB->get_records_sql(
                    "SELECT * FROM {block_ea_session_tracker} WHERE user_record_id = ? AND session_start_time = ?",
                    [$user_record_id,$sessionStartTime]
                );

                if ($sessionData)
                {
                    $finishTime = time();
                    $singleSession = reset($sessionData); // Get the single record
                    $singleSession->finish_watch_time = $values['lastActiveTime']; // Set the finish time (you can set it as needed)
                    $singleSession->session_finish_time =  $finishTime;// Update the modification time
                    $DB->update_record('block_ea_session_tracker', $singleSession);
                    $dataCache->set($sessionFinishCache,$finishTime);
                    $dataCache->set($pageIdentifier,false);

                }
                else
                {
                    return false;
                }
            }
        }
    }
    public static function session_tracker_returns(){
       return new external_value(PARAM_BOOL,"True if found");
    }
}
function fetch_data_from_table($results)
{
    require_login();
    global $DB,$USER;
    $enrolledcourses = $results['enrolledcourses'];
    $teacherFlag = $results['teacher'];
    $fetchedData = array();
    if($teacherFlag === true)
    {
        foreach ($enrolledcourses as $course) {
            $courseid = $course['courseId'];
            $courseData = $DB->get_records_select('block_ea_user_records_holder', "course_id = :courseid", ['courseid' => $courseid]);

            foreach ($courseData as $key => $data) {
                // Fetch module data based on Course Id, Instance Id, and Resource Id
                $moduleData = $DB->get_record('course_modules', ['course' => $data->course_id, 'instance' => $data->instance_id, 'id' => $data->resource_id]);

                // Update the 'Module' field in the $courseData array
                $courseData[$key]->module = isset($moduleData->module) ? $moduleData->module : '';
            }
            //Creating Final Array
            foreach ($courseData as $record) {
                // Fetching Username also
                $user = $DB->get_record('user', ['id' => $record->user_id], 'firstname, lastname');
                $fullname = $user->firstname . ' ' . $user->lastname;
                if($record->module == 19)
                {
                    //Fetching Lecture Name
                    $lectureTitles = $DB->get_record('resource', ['id' => $record->instance_id], 'name');
                    $lectureTitle = $lectureTitles->name;
                } else if($record->module == 17)
                {
                    //Fetching Lecture Name
                    $lectureTitles = $DB->get_record('page', ['id' => $record->instance_id, 'course' => $record->course_id], 'name');
                    $lectureTitle = $lectureTitles->name;
                }
                $fetchedData[] = array(
                    'id' => $record->id,
                    'userid' => $record->user_id,
                    'userFullName' => $fullname,
                    'instanceid' => $record->instance_id,
                    'lectureTitle' => $lectureTitle,
                    'courseid' => $record->course_id,
                    'coursename' => $course['courseName'],
                    'resourceid' => $record->resource_id,
                    'totalduration' => $record->total_duration,
                    'activity_start_time' => $record->activity_start_date,
                    'activity_finish_time' => $record->activity_finish_date,
                    'reset_activity' => $record->reset_activity,
                    'record_analyze' => $record->record_analyze,
                );
            }
        }
    }
    else
    {
        $userid = $USER->id;
        foreach ($enrolledcourses as $course) {
            $courseid = $course['courseId'];
            $courseData = $DB->get_records_select(
                'block_ea_user_records_holder',
                "course_id = :courseid AND user_id = :userid",
                ['courseid' => $courseid, 'userid' => $userid]
            );

            foreach ($courseData as $key => $data) {
                // Fetch module data based on Course Id, Instance Id, and Resource Id
                $moduleData = $DB->get_record('course_modules', ['course' => $data->course_id, 'instance' => $data->instance_id, 'id' => $data->resource_id]);

                // Update the 'Module' field in the $courseData array
                $courseData[$key]->module = isset($moduleData->module) ? $moduleData->module : '';
            }

            //Creating Final Array
            foreach ($courseData as $record) {
                // Fetching Username also
                $user = $DB->get_record('user', ['id' => $record->user_id], 'firstname, lastname');
                $fullname = $user->firstname . ' ' . $user->lastname;
                if($record->module == 19)
                {
                    //Fetching Lecture Name
                    $lectureTitles = $DB->get_record('resource', ['id' => $record->instance_id], 'name');
                    $lectureTitle = $lectureTitles->name;
                } else if($record->module == 17)
                {
                    //Fetching Lecture Name
                    $lectureTitles = $DB->get_record('page', ['id' => $record->instance_id, 'course' => $record->course_id], 'name');
                    $lectureTitle = $lectureTitles->name;
                }
                $fetchedData[] = array(
                    'id' => $record->id,
                    'userid' => $record->user_id,
                    'userFullName' => $fullname,
                    'instanceid' => $record->instance_id,
                    'lectureTitle' => $lectureTitle,
                    'courseid' => $record->course_id,
                    'coursename' => $course['courseName'],
                    'resourceid' => $record->resource_id,
                    'totalduration' => $record->total_duration,
                    'activity_start_time' => $record->activity_start_date,
                    'activity_finish_time' => $record->activity_finish_date,
                    'reset_activity' => $record->reset_activity,
                    'record_analyze' => $record->record_analyze,
                );
            }
        }
    }
    return $fetchedData;
}
function mapGenderToNumber($gender) {
    // Convert gender to lowercase for case-insensitive comparison
    $lowercasedGender = strtolower($gender);

    // Check if gender is 'male' and return 0
    if ($lowercasedGender === 'male') {
        return 0;
    }

    // Check if gender is 'female' and return 1
    if ($lowercasedGender === 'female') {
        return 1;
    }

    // If gender is not available or not recognized, return 2
    return 2;
}
function mapAgeToGroup($age) {
    // Convert the age to an integer
    $age = intval($age);

    // Define the age group mappings
    $ageGroups = [
        1 => ['min' => 18, 'max' => 24],
        2 => ['min' => 25, 'max' => 34],
        3 => ['min' => 35, 'max' => 44],
    ];

    // Check the age against each group and return the corresponding group number
    foreach ($ageGroups as $groupNumber => $range) {
        if ($age >= $range['min'] && $age <= $range['max']) {
            return $groupNumber;
        }
    }
    // If the age doesn't match any group, return 0 or any other appropriate value
    return 0;
}
//Function to check the data existence in cache
function checkDataExistence($data, $values) {
    foreach ($data as $row) {
        if ($row->id == $values['resourceId'] && $row->course == $values['courseId'] && $row->instance == $values['instanceId']) {
            return true;
        }
    }
    return false;
}
function check_existing_record($loggedInUser, $values)
{
    require_login();
    global $DB;
    $existingRecord = $DB->get_record('block_ea_user_records_holder', [
        'user_id' => $loggedInUser,
        'instance_id' => $values['instanceId'],
        'resource_id' => $values['resourceId'],
        'course_id' => $values['courseId'],
    ]);
    return $existingRecord;
}
function check_enrolled_courses($userId)
{

    global $DB;
    $teacherFlag = false;
    // Geting all course IDs
    $courseids = $DB->get_records_sql("SELECT DISTINCT(course_id) FROM {block_ea_user_records_holder}");

    // Loop through the course IDs to check the enrolled courses
    $enrolledcourses = array();
    foreach ($courseids as $courseid => $courserecord) {
        // Check if the user is a teacher in this course
        if (has_capability('moodle/course:manageactivities', context_course::instance($courseid), $userId)) {
            $enrolledcourses[] = array(
                'courseId' => $courseid,
                'courseName' => get_course($courseid)->fullname,
            );
        }
    }
    if(!empty($enrolledcourses))
    {
        $teacherFlag = true;
        return array(
            'teacher' => $teacherFlag,
            'enrolledcourses' => $enrolledcourses,
        );
    }
    else{
        $sql = "SELECT DISTINCT(course_id) FROM {block_ea_user_records_holder} WHERE user_id = :userid";
        // Bind the user ID parameter to the SQL query
        $params = array('userid' => $userId);

        // Execute the query
        $courseids = $DB->get_records_sql($sql, $params);
        foreach($courseids as $courseid => $courserecord)
        {
            $enrolledcourses[] = array(
                'courseId' => $courseid,
                'courseName' => get_course($courseid)->fullname,
            );
        }
        return array(
            'teacher' => $teacherFlag,
            'enrolledcourses' => $enrolledcourses,
        );
    }
}
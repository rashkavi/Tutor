<?php
$status = new stdClass();
require_once("../../includes/UserClass.php");
require_once("../../includes/connection.php");
require_once("../../includes/UserPermissionsClass.php");
require_once("../../includes/Project.php");
if (SessionUser::hasValidSession()) {
    $sessionUserObject = SessionUser::getCurrentUserObject('Tutor');

    if ($_SERVER['REQUEST_METHOD'] == 'GET') {
        if (isset($_GET['class']) && !empty($_GET['class'])) {
            $class = $_GET['class'];
            $date = new DateTime("now", new DateTimeZone("Asia/Colombo"));

            UserPermissions::setUserPermissions($sessionUserObject['user_permissions']);

            $totalLecSchedule = "
        SELECT 
           `class`.`class_name`,
           `class`.`class_cover`,
           `et`.`exam_type`,
           `y`.`year`,
           `m`.`medium`,
           `subj`.`subject`,
            `tt`.`time_table_id`,
            `tt`.`date`,
            `tt`.`start_time`,
            `tt`.`end_time`,
            `tt`.`lecture`,
            `tt`.`lecture_title`,
            `tts`.`time_table_status`,
            `tts`.`time_table_status_id`,
            `lt`.`lecture_type`,
            `u`.`user_fname`,
            `u`.`user_lname`,
            `u`.`profile_img`,
            `t`.`title`
        FROM                    
            `time_table` AS `tt`
        INNER JOIN 
            `classes` AS `class` ON `tt`.`classes_class_id` = `class`.`class_id`
        INNER JOIN 
            `subjects` AS `subj` ON `class`.`subjects_subject_id` = `subj`.`subject_id`
        INNER JOIN 
            `exam_type` AS `et` ON `class`.`exam_type_exam_type_id` = `et`.`exam_type_id`
        INNER JOIN
            `year` AS `y` ON `class`.`year_year_id` = `y`.`year_id`
        INNER JOIN 
            `medium` AS `m` ON `class`.`medium_medium_id` = `m`.`medium_id`
        INNER JOIN
            `time_table_status` AS `tts` ON `tt`.`time_table_status_time_table_status_id` = `tts`.`time_table_status_id`
        INNER JOIN
            `lecture_type` AS `lt` ON `tt`.`lecture_type_lecture_type_id` = `lt`.`lecture_type_id`        
        
        INNER JOIN 
            `users` AS `u` ON `class`.`tutor_id` = `u`.`user_id`
        INNER JOIN 
            `title` AS `t` ON `u`.`title_title_id` = `t`.`title_id`
            WHERE CONCAT(tt.date, ' ', tt.start_time) < '" . $date->format('Y-m-d H:i:s') . "'
             AND u.user_id = '" . $sessionUserObject["user_id"] . "'
             AND class.class_id = '" . $class . "' AND lt.lecture_type_id != 3
            ORDER BY tt.date, tt.start_time
    ";

            $totalLecSchedule = Database::search($totalLecSchedule);
            $lectures = [];

            if ($totalLecSchedule->num_rows > 0) {
                $date = new DateTime("now", new DateTimeZone("Asia/Colombo"));
                while ($row = $totalLecSchedule->fetch_assoc()) {

                    $lectureDate = $row['date'];

                    // Only assign if that date is not already set
                    if (!isset($lectures[$lectureDate])) {

                        $lectures[$lectureDate] = [
                            'id' => $row['time_table_id'],
                            'name' => $row['lecture_title'],
                            'time' => date('h:i A', strtotime($row['start_time'])) .
                                ' - ' .
                                date('h:i A', strtotime($row['end_time']))
                        ];
                    }
                }
            }

            $status->lectures = $lectures;
            $status->done = true;
        }
    } else {
        http_response_code(400);
        $status->error = 'Invalid JSON';
    }
} else {
    $status->error = 'Authentication Failed!';
}

echo (json_encode($status));

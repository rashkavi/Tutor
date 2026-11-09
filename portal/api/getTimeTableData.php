<?php
$status = new stdClass();
require_once("../../includes/UserClass.php");
require_once("../../includes/connection.php");
require_once("../../includes/UserPermissionsClass.php");
require_once("../../includes/Project.php");
if (SessionUser::ifUserIsStudent()) {
    $sessionUserObject = SessionUser::getCurrentUserObject('Student');
    if ($_SERVER['REQUEST_METHOD'] == 'GET') {
        $date = new DateTime("now", new DateTimeZone("Asia/Colombo"));

        $totalLecSchedule = "
        SELECT 
           `class`.`class_name`,
           `class`.`class_cover`,
           `et`.`exam_type`,
           `et`.`exam_type_id`,
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
        INNER JOIN `students_has_classes` AS `shc` ON `class`.`class_id` = `shc`.`classes_class_id`
        WHERE 
            `shc`.`students_student_id` = '" . $sessionUserObject["student_id"] . "'
            AND `shc`.`status_status_id` = 1
    ";

        $totalLecSchedule .= "  AND lt.lecture_type_id != 3
                ORDER BY tt.date, tt.start_time";

        $totalLecSchedule = Database::search($totalLecSchedule);
        $lectures = [];

        if ($totalLecSchedule->num_rows > 0) {
            $date = new DateTime("now", new DateTimeZone("Asia/Colombo"));
            while ($row = $totalLecSchedule->fetch_assoc()) {

                $subjectImage = Project::getAssetsPath() . "/imgs/classes-covers/" . $row['class_cover'];
                $defaultImage = Project::getAssetsPath() . "/imgs/common/empty-thumbnail.webp";

                $hasSubjectImage = true;
                if (empty($row['class_cover'])) {
                    $hasSubjectImage = false;
                } else if (!file_exists(Project::getApiAssetsPath() . "/imgs/classes-covers/" . $row['class_cover'])) {
                    $hasSubjectImage = false;
                }

                $lectureStatus = $row['time_table_status'];

                if ($row['time_table_status_id'] == 1) {
                    if ($row['date'] == $date->format('Y-m-d') && $row['start_time'] < $date->format('H:i:s') && $row['end_time'] > $date->format('H:i:s')) {
                        $lectureStatus = "Ongoing";
                    } else if (($row['date'] == $date->format('Y-m-d') && $row['start_time'] > $date->format('H:i:s')) || $row['date'] > $date->format('Y-m-d')) {
                        $lectureStatus =  "Upcoming";
                    } else if (($row['date'] == $date->format('Y-m-d') && $row['end_time'] < $date->format('H:i:s')) || $row['date'] < $date->format('Y-m-d')) {
                        $lectureStatus = 'Completed';
                    }
                }

                $lecture = [
                    'lectureId' => $row['time_table_id'],
                    'date' => date_format(new DateTime($row['date']), 'D M Y'),
                    'subject' => htmlspecialchars($row['subject']),
                    'year' => htmlspecialchars($row['year']),
                    'exam_type' => htmlspecialchars($row['exam_type']),
                    'exam_type_id' => $row['exam_type_id'],
                    'class' => htmlspecialchars($row['class_name']),
                    'subjectImage' => $hasSubjectImage ? $subjectImage : $defaultImage,
                    'lecturerName' => $row['title'] . '.' . $row['user_fname'] . ' ' . $row['user_lname'],
                    'joinLink' => $row['lecture'],
                    'status' => $lectureStatus,
                    'startTime' => date('h:i A', strtotime($row['start_time'])),
                    'endTime' => date('h:i A', strtotime($row['end_time'])),
                    'lectureType' => $row['lecture_type'],
                    'title' => $row['lecture_title'],
                ];

                $lectureDate = $row['date'];

                if (!isset($lectures[$lectureDate])) {
                    $lectures[$lectureDate] = [];
                }
                $lectures[$lectureDate][] = $lecture;
            }
        }

        $status->lectures = $lectures;
        $status->done = true;
    } else {
        http_response_code(400);
        $status->error = 'Invalid JSON';
    }
} else {
    $status->error = 'Authentication Failed!';
}

echo (json_encode($status));

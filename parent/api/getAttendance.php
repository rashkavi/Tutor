<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
$status = new stdClass();
require_once('../../includes/UserClass.php');
require_once('../../includes/connection.php');
require_once('../../includes/Project.php');
if (SessionUser::ifUserIsParent()) {
    $sessionUserObject = SessionUser::getCurrentUserObject('Parent');
    if ($_SERVER['REQUEST_METHOD'] == 'GET' && isset($_GET['classId'])) {
        $classId = $_GET["classId"];

        $date = new DateTime("now", new DateTimeZone("Asia/Colombo"));

        $totalAttendanceQuery = "
                SELECT 
                   `class`.`class_name`,
           `class`.`class_cover`,
           `et`.`exam_type`,
           `et`.`exam_type_id`,
           `y`.`year`,
         
           `subj`.`subject`,
            `tt`.`time_table_id`,
            `tt`.`date`,
            `tt`.`start_time`,
            `tt`.`end_time`,
            `tt`.`lecture`,
            `tt`.`lecture_title`,
                         
            a.attendance_id,
            a.attendance_status_attendance_status_id,
            login_details.first_name,
            login_details.last_name

                FROM 
                    classes AS class
                INNER JOIN 
                    time_table AS tt ON class.class_id = tt.classes_class_id
                INNER JOIN 
                    students_has_classes AS shc ON class.class_id = shc.classes_class_id
                INNER JOIN parents_has_students ON shc.students_student_id = parents_has_students.students_student_id
                INNER JOIN students ON parents_has_students.students_student_id = students.student_id
                INNER JOIN login_details ON students.login_details_login_details_id = login_details.login_details_id
                INNER JOIN
                    subjects AS subj ON class.subjects_subject_id = subj.subject_id
                INNER JOIN 
                    exam_type AS et ON class.exam_type_exam_type_id = et.exam_type_id
                INNER JOIN
                    year AS y ON class.year_year_id = y.year_id         
                LEFT JOIN 
                    attendance AS a ON tt.time_table_id = a.time_table_time_table_id
                        AND a.students_student_id = students.student_id
                WHERE 
                    parents_has_students.parents_parent_id = '" . $sessionUserObject['user_id'] . "'
                    AND shc.status_status_id = 1
                    AND TIMESTAMP(tt.date, tt.start_time) <= '" . $date->format('Y-m-d H:i:s') . "'
                    AND tt.time_table_status_time_table_status_id = 1 AND lecture_type_lecture_type_id != 3
               
            ";

        if ($classId != 0) {
            $totalAttendanceQuery .= " AND  class.class_id = '" . $classId . "'";
        }
        $totalAttendanceQuery .= " ORDER BY tt.date, tt.start_time";

        $totalAttendances = Database::search($totalAttendanceQuery);
        $lectures = []; // This will hold our calendar data structure

        if ($totalAttendances->num_rows > 0) {
            $date = new DateTime("now", new DateTimeZone("Asia/Colombo"));
            while ($row = $totalAttendances->fetch_assoc()) {

                // Format the date as YYYY-MM-DD for the key
                $date = (new DateTime($row['date'], new DateTimeZone("Asia/Colombo")))->format('Y-m-d');
                $lectureDateTime = new DateTime($row['date'] . ' ' . $row['start_time'], new DateTimeZone("Asia/Colombo"));


                if ($row['attendance_status_attendance_status_id'] === '1') {
                    $attendanceStatus = 'Absent';
                    $row['attendance_status_attendance_status_id'] = 2;
                } elseif ($row['attendance_status_attendance_status_id'] === '2') {
                    $attendanceStatus = 'Present';
                } elseif ($row['attendance_status_attendance_status_id'] === '3') {
                    $attendanceStatus = 'Absent';
                } else if ($row['attendance_status_attendance_status_id'] === '4') {
                    $attendanceStatus = 'Watched';
                } else {
                    $attendanceStatus = 'Absent';
                }

                $subjectImage = Project::getAssetsPath() . "/imgs/classes-covers/" . $row['class_cover'];
                $defaultImage = Project::getAssetsPath() . "/imgs/common/empty-thumbnail.webp";

                $hasSubjectImage = true;
                if (empty($row['class_cover'])) {
                    $hasSubjectImage = false;
                } else if (!file_exists(Project::getApiAssetsPath() . "/imgs/classes-covers/" . $row['class_cover'])) {
                    $hasSubjectImage = false;
                }

                $lecture = [
                    'subject' => htmlspecialchars($row['subject']),
                    'year' => htmlspecialchars($row['year']),
                    'exam_type' => htmlspecialchars($row['exam_type']),
                    'exam_type_id' => $row['exam_type_id'],
                    'class' => htmlspecialchars($row['class_name']),
                    'subjectImage' => $hasSubjectImage ? $subjectImage : $defaultImage,
                    'status' => $attendanceStatus,
                    'time' => date('h:i A', strtotime($row['start_time'])),
                    'lectureId' => $row['time_table_id'],
                    'date' => date('D M Y', strtotime($row['date'])),
                    'startTime' => date('h:i A', strtotime($row['start_time'])),
                    'endTime' => date('h:i A', strtotime($row['end_time'])),
                    'title' => $row['lecture_title'],
                    'student' => $row['first_name'] . ' ' . $row['last_name'],
                    'isAttendnace' => true,
                ];

                if (!isset($lectures[$date])) {
                    $lectures[$date] = [];
                }
                $lectures[$date][] = $lecture;
            }
        }

        $status->lectures = $lectures;

        $status->done = 'Dashboard Chart Data Fetched Successfully!';
    } else {
        http_response_code(400);
        $status->error = 'Invalid request method';
    }
} else {
    $status->error = 'Authentication Failed1!';
}

echo (json_encode($status));

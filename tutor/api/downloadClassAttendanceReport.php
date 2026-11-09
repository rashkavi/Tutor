<?php
// header('Content-Type: application/json');

$status = new stdClass();
require_once("../../includes/UserClass.php");
require_once("../../includes/connection.php");
require_once("../../includes/UserPermissionsClass.php");
if (!(SessionUser::ifUserIsTutor())) {
    $status->error = "You are not authorized to this action.";
} else {
    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        if ($_SERVER['CONTENT_TYPE'] == 'application/json') {
            $content = trim(file_get_contents('php://input'));
            $decoded = json_decode($content, true);

            $sessionUserObject = SessionUser::getCurrentUserObject('Tutor');

            if (json_last_error() != JSON_ERROR_NONE) {
                $status->error = 'Invalid JSON';
                http_response_code(400);
                echo json_encode($status);
                exit();
            }

            $time_table_id = isset($decoded['time_table_id']) ? $decoded['time_table_id'] : '';

            $query = " SELECT 
            *                                
           FROM classes
         INNER JOIN `subjects` ON `classes`.`subjects_subject_id` = `subjects`.`subject_id`
         INNER JOIN `year` ON `classes`.`year_year_id` = `year`.`year_id`
         INNER JOIN `medium` ON `classes`.`medium_medium_id` = `medium`.`medium_id`
         INNER JOIN `exam_type` ON `classes`.`exam_type_exam_type_id` = `exam_type`.`exam_type_id`
           INNER JOIN `time_table` ON `classes`.`class_id` = `time_table`.`classes_class_id`
           INNER JOIN `lecture_type` ON `time_table`.`lecture_type_lecture_type_id` = `lecture_type`.`lecture_type_id`
           WHERE `time_table`.`lecture_type_lecture_type_id` != 3
           AND `time_table`.`time_table_status_time_table_status_id` = 1
           AND `time_table`.`time_table_id` = '" . $time_table_id . "'
           AND `tutor_id`='" . $sessionUserObject['user_id'] . "'
           ";

            $lecture = Database::search($query);
            if ($lecture->num_rows == 0) {
                $status->error = 'Invalid Lecture Id';
            } else {
                $lectureData = $lecture->fetch_assoc();

                $dateTimeString = $lectureData['date'] . ' ' . $lectureData['start_time'];

                $dateObj = new DateTime($dateTimeString, new DateTimeZone("Asia/Colombo"));
                $formattedDate = $dateObj->format('Y-m-d H:i:s');

                header('Content-Type: text/csv');
                header('Content-Disposition: attachment; filename="Registration_Requests_Students.csv"');

                $output = fopen('php://output', 'w');
                fputcsv($output, ['Class', $lectureData['class_name']]);
                fputcsv($output, ['Exam Type', $lectureData['exam_type']]);
                fputcsv($output, ['Year', $lectureData['year']]);
                fputcsv($output, ['Subject', $lectureData['subject']]);
                fputcsv($output, ['Medium', $lectureData['medium']]);

                fputcsv($output, []);
                fputcsv($output, ['Class Title', $lectureData['lecture_title']]);
                fputcsv($output, ['Class Date', $lectureData['date']]);
                fputcsv($output, ['Class Start Time', date('h:i a', strtotime($lectureData['start_time']))]);
                fputcsv($output, ['Class End Time', date('h:i a', strtotime($lectureData['end_time']))]);
                fputcsv($output, []);

                $query = "SELECT 
                    students_has_classes.student_class_id AS sId,
                    students.student_id AS studentId,
                    login_details.first_name,
                    login_details.last_name,
                    login_details.email,
                    login_details.whatsapp,
                    students.profile_img,
                    status.status,
                    att.attendance_status_attendance_status_id AS attendanceStatus,
                    att.updated_at
                  
                  FROM students
                  
                  INNER JOIN login_details 
                    ON students.login_details_login_details_id = login_details.login_details_id
                  
                  INNER JOIN status 
                    ON login_details.status_status_id = status.status_id
                  
                  INNER JOIN students_has_classes 
                    ON students.student_id = students_has_classes.students_student_id
                  
                  INNER JOIN classes 
                    ON students_has_classes.classes_class_id = classes.class_id
                  
                  LEFT JOIN attendance AS att
                    ON att.students_student_id = students.student_id
                    AND att.time_table_time_table_id = '" . $time_table_id . "'
                  
                  WHERE students_has_classes.status_status_id = '1'
                  AND classes.tutor_id = '" . $sessionUserObject['user_id'] . "'
                  AND students.date_added <= '" . $formattedDate . "'
                  
                  GROUP BY students.student_id
                  ORDER BY students.student_id ASC
        ";

                try {



                    $students = Database::search($query);

                    if ($students->num_rows > 0) {
                        $dateTime = new DateTime("now", new DateTimeZone("Asia/Colombo"));
                        if ($lectureData['lecture_type_id'] != 3) {
                            $lectureDateTime = new DateTime($lectureData['date'] . " " . $lectureData['start_time'], new DateTimeZone("Asia/Colombo"));
                            $lectureEndDateTime = new DateTime($lectureData['date'] . " " . $lectureData['end_time'], new DateTimeZone("Asia/Colombo"));
                        }

                        $absentCount = 0;
                        $joinedCount = 0;
                        $presentCount = 0;
                        $watchCount = 0;
                        $totalCount = 0;
                        $notWatchCount = 0;
                        $recordingWatched = 0;

                        $studentsData = array();

                        while ($student = $students->fetch_assoc()) {

                            $studentName = $student['first_name'] . " " . $student['last_name'];

                            $attendStatus = "Pending";
                            $attendanceId = 0;
                            $totalCount++;
                            $alreadyMarkAttendance = Database::search("SELECT `attendance_status_attendance_status_id`,`attendance_id` FROM `attendance` WHERE `students_student_id`='" . $student["sId"] . "' AND `time_table_time_table_id`='" . $lectureData["time_table_id"] . "'");
                            if ($alreadyMarkAttendance->num_rows > 0) {
                                $alreadyMarkAttendanceData = $alreadyMarkAttendance->fetch_assoc();
                                $attendanceId = $alreadyMarkAttendanceData["attendance_id"];
                                if ($alreadyMarkAttendanceData["attendance_status_attendance_status_id"] == 2) {
                                    $attendStatus = "Present";
                                    $presentCount++;
                                } else if ($alreadyMarkAttendanceData["attendance_status_attendance_status_id"] == 3) {
                                    $attendStatus = "Absent";
                                    $absentCount++;
                                } else if ($alreadyMarkAttendanceData["attendance_status_attendance_status_id"] == 1) {
                                    $attendStatus = "Joined";
                                    $joinedCount++;
                                } else if ($alreadyMarkAttendanceData["attendance_status_attendance_status_id"] == 4) {
                                    $attendStatus = "Recording Watched";
                                    $recordingWatched++;
                                    if ($lectureData['lecture_type_id'] == 3) {
                                        $attendStatus = "Watch";
                                        $watchCount++;
                                    }
                                }
                            }

                            if ($lectureData['lecture_type_id'] != 3 && $attendanceId == 0 && $dateTime > $lectureEndDateTime) {
                                $attendStatus = "Absent";
                                $absentCount++;
                            } else if ($lectureData['lecture_type_id'] == 3 && $attendanceId == 0) {
                                $attendStatus = "Not Watch";
                                $notWatchCount++;
                            }

                            $studentRow = [
                                $student['sId'],
                                $studentName,
                                $student['whatsapp'],
                                $attendStatus
                            ];

                            $studentsData[] = $studentRow;
                        }

                        fputcsv($output, ['Attendance Summary']);
                        fputcsv($output, ['Total Students', $totalCount]);
                        if ($lectureData['lecture_type_id'] != 3) {
                            if ($lectureData['lecture_type_id'] == 2) {
                                fputcsv($output, ['Joined', $joinedCount]);
                            }

                            fputcsv($output, ['Present', $presentCount]);
                            fputcsv($output, ['Absent', $absentCount]);
                            fputcsv($output, ['Recording Watched', $recordingWatched]);
                        } else {
                            fputcsv($output, ['Watch', $watchCount]);
                            fputcsv($output, ['Not Watch', $notWatchCount]);
                        }

                        fputcsv($output, []);
                        fputcsv($output, ['Students Details']);
                        fputcsv($output, []);

                        fputcsv($output, ['Class Student Id', 'Student', 'Whatsapp', 'Attend Status']);
                        foreach ($studentsData as $student) {
                            fputcsv($output, $student);
                        }
                        fclose($output);
                        exit();
                    } else {
                        $status->error = 'No students found! Please refresh filters and try again.';
                    }
                } catch (Exception $e) {
                    $status->error = $e->getMessage();
                }
            }
        } else {
            $status->error = 'Invalid Content-Type';
        }
    } else {
        $status->error = 'Invalid request method';
    }
}
echo (json_encode($status));

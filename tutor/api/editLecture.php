<?php
$status = new stdClass();
require_once("../../includes/UserClass.php");
require_once("../../includes/connection.php");
require_once("../../includes/UserPermissionsClass.php");
require_once("../../includes/Project.php");
if (SessionUser::ifUserIsTutor()) {
    $sessionUserObject = SessionUser::getCurrentUserObject('Tutor');

    if ($_SERVER['REQUEST_METHOD'] == 'POST') {

        $lecture_id = isset($_POST['lecture_id']) ? trim($_POST['lecture_id']) : 0;
        $title = isset($_POST['title']) ? trim($_POST['title']) : '';
        $lecture_date = isset($_POST['lecture_date']) ? trim($_POST['lecture_date']) : '';
        $start_time = isset($_POST['start_time']) ? trim($_POST['start_time']) : '';
        $end_time = isset($_POST['end_time']) ? trim($_POST['end_time']) : '';
        $time_table_status = isset($_POST['status']) ? trim($_POST['status']) : 0;
        $physical = isset($_POST['physical']) ? trim($_POST['physical']) : false;
        $online = isset($_POST['online']) ? trim($_POST['online']) : false;
        $recording = isset($_POST['recording']) ? trim($_POST['recording']) : false;
        $link = isset($_POST['link']) ? trim($_POST['link']) : '';

        if (empty($lecture_id) || $lecture_id == 0) {
            $status->error = "Invalid lecture Id";
        } else {
            $query = "SELECT * FROM `time_table`
                INNER JOIN `classes` ON `time_table`.`classes_class_id` = `classes`.`class_id`
                INNER JOIN `subjects` ON `classes`.`subjects_subject_id` = `subjects`.`subject_id`
             
                WHERE `time_table_id`='" . $lecture_id . "'";

            if (SessionUser::ifUserIsTutor()) {
                $query .= " AND `classes`.`tutor_id`='" . $sessionUserObject['user_id'] . "'";
            }

            $isValidLecture = Database::search($query);

            if ($isValidLecture->num_rows == 0) {
                $status->error = "Invalid lecture Id";
            } else {
                $lectureData = $isValidLecture->fetch_assoc();
                $subject_id = $lectureData['subjects_subject_id'];

                UserPermissions::setUserPermissions($sessionUserObject['user_permissions']);

                if (empty($title)) {
                    $status->title = 'Please enter a title';
                } else if (strlen($title) > 50) {
                    $status->title = 'Title should be less than 50 characters';
                }

                if (empty($lecture_date)) {
                    $status->date = 'Please select a lecture date';
                }
                if (empty($start_time)) {
                    $status->start_time = 'Please select a start time';
                }
                if (empty($end_time)) {
                    $status->end_time = 'Please select an end time';
                }

                if (($physical === false) && ($online === false) && ($recording === false)) {
                    $status->error = "Please select lecture type";
                }

                if ((($online === true) || ($recording === true)) && empty($link)) {
                    $status->link = 'Please enter a link1';
                }

                if ($time_table_status == 0) {
                    $status->time_table_status = 'Please select a time table status';
                }

                $tutes = array();

                if (isset($_FILES["tutes"])) {
                    $allowed_tute_extensions = array(
                        "application/pdf",
                    );

                    foreach ($_FILES["tutes"]["name"] as $key => $tute_name) {
                        $tute_ex = $_FILES["tutes"]["type"][$key];

                        if (!in_array($tute_ex, $allowed_tute_extensions)) {
                            $status->tute[] = "File '$tute_name' is not a valid type. Only PDF, DOC, and DOCX files are allowed.";
                        } else {
                            $new_tute_extension = '';
                            switch ($tute_ex) {
                                case "application/pdf":
                                    $new_tute_extension = ".pdf";
                                    break;
                                case "application/msword":
                                    $new_tute_extension = ".doc";
                                    break;
                                case "application/vnd.openxmlformats-officedocument.wordprocessingml.document":
                                    $new_tute_extension = ".docx";
                                    break;
                            }

                            if ($_FILES["tutes"]["error"][$key] === 0) {
                                if ($_FILES["tutes"]["size"][$key] <= 10 * 1024 * 1024) {
                                    $tute = uniqid() . $new_tute_extension;
                                    array_push($tutes, $tute);
                                } else {
                                    $status->tute[] = "File '$tute_name' exceeds the maximum allowed size of 10MB.";
                                }
                            } else {
                                $status->tute[] = "There was an error uploading the file '$tute_name'.";
                            }
                        }
                    }
                }

                $requiredProperties = [
                    'error',
                    'subject',
                    'title',
                    'lecturer',
                    'lecture_date',
                    'start_time',
                    'end_time',
                    'link',
                    'tute',
                    'date',
                    'time_table_status'
                ];
                $allFieldsPresent = true;
                foreach ($requiredProperties as $property) {
                    if (property_exists($status, $property)) {
                        $allFieldsPresent = false;
                        break;
                    }
                }

                if ($allFieldsPresent) {
                    $timezone = new DateTimeZone("Asia/Colombo");
                    $dateTime = new DateTime("now", $timezone);
                    $addTime = $dateTime->format('Y-m-d H:i:s');

                    $start = new DateTime($start_time);
                    $end = new DateTime($end_time);

                    $interval = $start->diff($end);

                    $duration = ($interval->h * 60) + $interval->i;

                    $time_table_update_query = "`time_table_status_time_table_status_id`='" . $time_table_status . "'
                            ,`date`='" . $lecture_date . "',`start_time`='" . $start_time . "',`end_time`='" . $end_time . "',`duration`='" . $duration . "',`lecture_title`='" . $title . "'";

                    if (filter_var($physical, FILTER_VALIDATE_BOOLEAN)) {
                        $time_table_update_query .= ",`lecture_type_lecture_type_id`='1',`lecture`=''";
                    }

                    if (filter_var($online, FILTER_VALIDATE_BOOLEAN)) {
                        $time_table_update_query .= ",`lecture_type_lecture_type_id`='2',`lecture`='" . $link . "'";
                    }

                    if (filter_var($recording, FILTER_VALIDATE_BOOLEAN)) {
                        $time_table_update_query .= ",`lecture_type_lecture_type_id`='3',`lecture`='" . $link . "'";
                    }

                    try {
                        Database::iud("UPDATE `time_table` SET " . $time_table_update_query . " WHERE `time_table_id` = '" . $lecture_id . "'");

                        if (isset($_FILES["tutes"])) {
                            for ($i = 0; $i < count($_FILES["tutes"]["name"]); $i++) {

                                $originalFileName = $_FILES["tutes"]["name"][$i];

                                $attachment_insert_columns = "`attachment`,`attachment_name`,`time_table_time_table_id`,`users_user_id`,`date_added`,`attachments_order`";
                                $attachment_insert_values = "'" . $tutes[$i] . "','" . $originalFileName . "','" . $lecture_id . "','" . $sessionUserObject["user_id"] . "','" . $addTime . "','0'";

                                Database::iud("INSERT INTO `attachments` (" . $attachment_insert_columns . ") VALUES (" . $attachment_insert_values . ")");

                                $tute_path = Project::getApiAssetsPath() . "/uploads/lectures-attachments/" . $tutes[$i];
                                move_uploaded_file($_FILES["tutes"]["tmp_name"][$i], $tute_path);
                            }
                        }

                        $status->done = "Lecture has been successfully updated";
                    } catch (Exception $e) {
                        $status->error = $e->getMessage();
                    }
                }
            }
        }
    } else {
        http_response_code(400);
        $status->error = 'Invalid request method';
    }
} else {
    $status->error = 'Authentication Failed!';
}

echo (json_encode($status));


function checkIfFutureDateTime($lecture_date, $start_time)
{
    if ($lecture_date && $start_time) {
        $startDateTime = new DateTime("$lecture_date $start_time");
        $now = new DateTime();
        $isToday = $startDateTime->format('Y-m-d') === $now->format('Y-m-d');
        if (($isToday && $startDateTime > $now) || $startDateTime > $now) {
            return true;
        } else {
            return false;
        }
    } else {
        return false;
    }
}

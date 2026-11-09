<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
$status = new stdClass();
require_once("../../includes/UserClass.php");
require_once("../../includes/connection.php");
require_once("../../includes/UserPermissionsClass.php");
require_once("../../includes/DateTimeClass.php");
require_once("../../includes/Project.php");
require_once("../includes/ZoomService.php");

if (SessionUser::ifUserIsTutor()) {
    $sessionUserObject = SessionUser::getCurrentUserObject('Tutor');

    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        UserPermissions::setUserPermissions($sessionUserObject['user_permissions']);
        $class_id = isset($_POST['class']) ? trim($_POST['class']) : 0;

        $query = "SELECT * FROM `classes` WHERE `class_id` = '" . $class_id . "'";
        if (SessionUser::ifUserIsTutor()) {
            $query .= " AND `tutor_id`='" . $sessionUserObject['user_id'] . "'";
        }

        $isValidClassId = Database::search($query);
        if ($isValidClassId->num_rows == 0) {
            $status->class = 'Please select a valid class';
        } else {

            $lecture_date = isset($_POST['lecture-date']) ? trim($_POST['lecture-date']) : '';
            $start_time = isset($_POST['start_time']) ? trim($_POST['start_time']) : '';
            $end_time = isset($_POST['end_time']) ? trim($_POST['end_time']) : '';
            $title = isset($_POST['title']) ? trim($_POST['title']) : '';
            $physical = isset($_POST['physical']) && $_POST['physical'] === 'true' ? true : false;
            $online = isset($_POST['online']) && $_POST['online'] === 'true' ? true : false;
            $link = isset($_POST['link']) ? trim($_POST['link']) : '';
            $one_time = isset($_POST['one_time']) && $_POST['one_time'] === 'true' ? true : false;
            $recurring = isset($_POST['recurring']) && $_POST['recurring'] === 'true' ? true : false;
            $start_date = isset($_POST['start_date']) ? trim($_POST['start_date']) : '';
            $end_date = isset($_POST['end_date']) ? trim($_POST['end_date']) : '';
            $days = isset($_POST['days']) ? trim($_POST['days']) : [];
            $skip_poya_days = isset($_POST['skip_poya_days']) && $_POST['skip_poya_days'] === 'true' ? true : false;
            $skip_holidays = isset($_POST['skip_holidays']) && $_POST['skip_holidays'] === 'true' ? true : false;

            if ($one_time && empty($lecture_date)) {
                $status->date = 'Please select a lecture date';
            }
            if (empty($start_time)) {
                $status->start_time = 'Please select a start time';
            }
            if (empty($end_time)) {
                $status->end_time = 'Please select an end time';
            }

            $availableZoomIntergration = Database::search("SELECT * FROM `integrations` 
            INNER JOIN `integration_connections` ON `integration_connections`.`integration_id` = `integrations`.`integration_id`
            WHERE `users_user_id`='" . $sessionUserObject["user_id"] . "' AND `integration_type_integration_type_id`='1'");

            if ($availableZoomIntergration->num_rows == 0) {
                if ($online && empty($link)) {
                    $status->link = 'Please enter a link';
                }
            }

            if ($one_time && empty($title)) {
                $status->title = 'Please enter a title';
            } else if (strlen($title) > 50) {
                $status->title = 'Title must be less than 50 characters';
            }

            $tutes = array();

            if (isset($_FILES["tutes"])) {
                $allowed_tute_extensions = array(
                    "application/pdf",
                    "application/msword",
                    "application/vnd.openxmlformats-officedocument.wordprocessingml.document"
                );

                foreach ($_FILES["tutes"]["name"] as $key => $tute_name) {
                    $tute_ex = $_FILES["tutes"]["type"][$key];

                    if (!in_array($tute_ex, $allowed_tute_extensions)) {
                        $status->tute[] = "File '$tute_name' is not a valid type. Only PDF, DOC, and DOCX files are allowed.";
                    } else if (strlen($tute_name) > 45) {
                        $status->tute[] = "File '$tute_name' exceeds the maximum allowed character limit of 45.";
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

            if ($one_time) {
                if (!empty($lecture_date) && !empty($start_time) && !empty($end_time)) {

                    $isAvailableLecturer = Database::search("SELECT COUNT(*) as 'count' FROM `time_table`
                                                                        INNER JOIN `classes` ON `time_table`.`classes_class_id` = `classes`.`class_id`
                                                                        WHERE `time_table`.`date` = '" . $lecture_date . "'
                                                                        AND ((`time_table`.`start_time` BETWEEN '" . $start_time . "' AND '" . $end_time . "')
                                                                        OR (`time_table`.`end_time` BETWEEN '" . $start_time . "' AND '" . $end_time . "'))");

                    $row = $isAvailableLecturer->fetch_assoc();
                    if ($row['count'] > 0) {
                        $status->lecturer = 'The selected time is unavailable for this lecturer.';
                    }
                }

                if (!empty($lecture_date) && !empty($start_time) && !empty($end_time)) {
                    $isAvailableSelectTimeInIntake = Database::search("SELECT COUNT(*) as 'count' FROM `time_table`
                                        INNER JOIN `classes` ON `time_table`.`classes_class_id` = `classes`.`class_id`
                                                                                  WHERE `classes_class_id` IN (SELECT `class_id` FROM `classes` WHERE `class_id` = '" . $class_id . "') 
                                                                                  AND `date` = '" . $lecture_date . "'
                                                                                  AND ((`start_time` BETWEEN '" . $start_time . "' AND '" . $end_time . "')
                                                                                  OR (`end_time` BETWEEN '" . $start_time . "' AND '" . $end_time . "'))
                                                                                                                                                                 ");
                    $row = $isAvailableSelectTimeInIntake->fetch_assoc();
                    if ($row['count'] > 0) {
                        $status->date = 'The selected time has already been scheduled for a lecture in this Intake.';
                    }
                }
            }

            if ($recurring) {
                if (empty($start_date)) {
                    $status->start_date = 'Please select a recurring lecture start date';
                }

                if (empty($end_date)) {
                    $status->end_date = 'Please select a recurring lecture end date';
                }

                if (empty($days)) {
                    $status->days = 'Please select at least one day';
                }
            }


            if (empty(get_object_vars($status))) {
                $timezone = new DateTimeZone("Asia/Colombo");
                $dateTime = new DateTime("now", $timezone);
                $addTime = $dateTime->format('Y-m-d H:i:s');

                $start = new DateTime($start_time);
                $end = new DateTime($end_time);

                $interval = $start->diff($end);

                $duration = ($interval->h * 60) + $interval->i;

                $availableZoomIntergration = Database::search("SELECT * FROM `integrations` 
                INNER JOIN `integration_connections` ON `integration_connections`.`integration_id` = `integrations`.`integration_id`
                WHERE `users_user_id`='" . $sessionUserObject["user_id"] . "' AND `integration_type_integration_type_id`='1'");

                if ($online && $availableZoomIntergration->num_rows > 0) {
                    try {
                        $zoomConnection = $availableZoomIntergration->fetch_assoc();
                        $startDateTime = $lecture_date . " " . $start_time;

                        $meeting = ZoomService::createMeeting(
                            $zoomConnection,
                            $title,
                            $startDateTime,
                            $duration
                        );

                        $link = $meeting["join_url"];
                    } catch (Exception $e) {
                        print_r($e->getMessage());
                        exit();
                    }
                }

                if ($one_time) {
                    $time_table_insert_columns = "`date`,`start_time`,`end_time`,`duration`,`classes_class_id`,`date_added`,`users_user_id`,`time_table_status_time_table_status_id`,`lecture_title`";
                    $time_table_insert_values = "'" . $lecture_date . "','" . $start_time . "','" . $end_time . "','" . $duration . "','" . $class_id . "','" . $addTime . "','" . $sessionUserObject["user_id"] . "','1','" . $title . "'";

                    if ($physical) {
                        $time_table_insert_columns .= ",`lecture_type_lecture_type_id`,`lecture`";
                        $time_table_insert_values .= ",'1',''";
                    }

                    if ($online) {

                        $time_table_insert_columns .= ",`lecture_type_lecture_type_id`,`lecture`";
                        $time_table_insert_values .= ",'2','" . Database::escape($link) . "'";
                    }

                    try {
                        Database::iud("INSERT INTO `time_table` (" . $time_table_insert_columns . ") VALUES (" . $time_table_insert_values . ")");

                        $time_table_id = Database::$connection->insert_id;

                        if (isset($_FILES["tutes"])) {
                            for ($i = 0; $i < count($_FILES["tutes"]["name"]); $i++) {
                                $originalFileName = $_FILES["tutes"]["name"][$i];
                                $attachment_insert_columns = "`attachment`,`attachment_name`,`time_table_time_table_id`,`users_user_id`,`date_added`,`attachments_order`";
                                $attachment_insert_values = "'" . $tutes[$i] . "','" . $originalFileName . "','" . $time_table_id . "','" . $sessionUserObject["user_id"] . "','" . $addTime . "','0'";

                                Database::iud("INSERT INTO `attachments` (" . $attachment_insert_columns . ") VALUES (" . $attachment_insert_values . ")");

                                $tute_path = Project::getApiAssetsPath() . "/uploads/lectures-attachments/" . $tutes[$i];
                                move_uploaded_file($_FILES["tutes"]["tmp_name"][$i], $tute_path);
                            }
                        }

                        $status->done = "Class has been scheduled successfully";
                    } catch (Exception $e) {
                        echo $e->getMessage();
                    }
                }

                if ($recurring) {
                    $poyaDays = [];
                    $specialHolidays = [];

                    $holidays = getYearHolidays();

                    foreach ($holidays as $holiday) {
                        if (!isset($holiday['categories'])) {
                            continue;
                        }
                        $categories = $holiday['categories'];

                        if (!is_array($categories)) {
                            $categories = explode(',', $categories);
                        }
                        $categories = array_map('strtolower', $categories);

                        if (in_array('poya', $categories)) {
                            $poyaDays[] = $holiday['start'];
                        } else {
                            $specialHolidays[] = $holiday['start'];
                        }
                    }

                    $lectureDays = generateLectureDates($start_date, $end_date, $days, $skip_poya_days, $skip_holidays, $poyaDays, $specialHolidays);
                    if (count($lectureDays) == 0) {
                        $status->error = "No Lecture Dates Found";
                    } else {

                        $addedSubjectLectures = Database::search("SELECT COUNT(*) as count,`class_name` FROM `time_table`
                            INNER JOIN `classes` ON `time_table`.`classes_class_id` = `classes`.`class_id`
                            INNER JOIN `subjects` ON `classes`.`subjects_subject_id` = `subjects`.`subject_id`
                            WHERE `classes_class_id` = '" . $class_id . "' AND `lecture_type_lecture_type_id` != 3");

                        $addedSubjectLecturesData = $addedSubjectLectures->fetch_assoc();

                        for ($i = 0; $i < count($lectureDays); $i++) {
                            $lecture_date = $lectureDays[$i];
                            $lecture_title = $addedSubjectLecturesData['class_name'] . " - Class Day " . ($addedSubjectLecturesData['count'] + $i + 1);
                            $time_table_insert_columns = "`date`,`start_time`,`end_time`,`duration`,`classes_class_id`,`date_added`,`users_user_id`,`time_table_status_time_table_status_id`,`lecture_title`";
                            $time_table_insert_values = "'" . $lecture_date . "','" . $start_time . "','" . $end_time . "','" . $duration . "','" . $class_id . "','" . $addTime . "','" . $sessionUserObject["user_id"] . "','1','" . Database::escape($lecture_title) . "'";

                            if ($physical) {
                                $time_table_insert_columns .= ",`lecture_type_lecture_type_id`,`lecture`";
                                $time_table_insert_values .= ",'1',''";
                            }

                            if ($online) {
                                $time_table_insert_columns .= ",`lecture_type_lecture_type_id`,`lecture`";
                                $time_table_insert_values .= ",'2','" . $link . "'";
                            }

                            Database::iud("INSERT INTO `time_table` (" . $time_table_insert_columns . ") VALUES (" . $time_table_insert_values . ")");
                        }
                        $status->done = "Classes has been scheduled successfully";
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

function getYearHolidays()
{
    $year = date('Y');
    $jsonUrl = "https://raw.githubusercontent.com/Dilshan-H/srilanka-holidays/main/json/{$year}.json";

    $jsonData = file_get_contents($jsonUrl);
    if ($jsonData === false) {
        return [];
    }

    $decoded = json_decode($jsonData, true);

    // ✅ Ensure we always return an array of holidays
    if (isset($decoded['holidays']) && is_array($decoded['holidays'])) {
        return $decoded['holidays'];
    }

    // Some years are already flat arrays
    if (is_array($decoded)) {
        return $decoded;
    }

    return [];
}


function generateLectureDates(
    $startDate,
    $endDate,
    $selectedDays,
    $skipPoya,
    $skipHolidays,
    $poyaDays,
    $holidays
) {
    $dates = [];

    $start = new DateTime($startDate);
    $end = new DateTime($endDate);
    $end->modify('+1 day');

    $period = new DatePeriod($start, new DateInterval('P1D'), $end);
    if (!is_array($selectedDays)) {
        $selectedDays = array_map('intval', explode(',', $selectedDays));
    }

    foreach ($period as $date) {
        $currentDate = $date->format('Y-m-d');
        $dayOfWeek = (int)$date->format('N');


        if (!in_array($dayOfWeek, $selectedDays)) {
            continue;
        }

        if ($skipPoya && in_array($currentDate, $poyaDays)) {
            continue;
        }

        if ($skipHolidays && in_array($currentDate, $holidays)) {
            continue;
        }

        $dates[] = $currentDate;
    }

    return $dates;
}

<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
$status = new stdClass();
require_once("../../includes/UserClass.php");
require_once("../../includes/connection.php");
require_once("../../includes/UserPermissionsClass.php");
require_once("../../includes/DateTimeClass.php");
require_once("../../includes/Project.php");
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

            $title = isset($_POST['title']) ? trim($_POST['title']) : '';
            $description = isset($_POST['description']) ? trim($_POST['description']) : '';
            $title = isset($_POST['title']) ? trim($_POST['title']) : '';
            $enabled_payment_checked = isset($_POST['enabled_payment_checked']) && $_POST['enabled_payment_checked'] === 'true' ? true : false;
            $payment_month = isset($_POST['payment_month']) ? trim($_POST['payment_month']) : '';
            $online = isset($_POST['online']) && $_POST['online'] === 'true' ? true : false;
            $courier = isset($_POST['courier']) && $_POST['courier'] === 'true' ? true : false;
            $tute_titles = [];

            if (isset($_POST['tute_titles'])) {

                if (is_array($_POST['tute_titles'])) {
                    $tute_titles = $_POST['tute_titles'];
                } else {
                    $decoded = json_decode($_POST['tute_titles'], true);
                    if (is_array($decoded)) {
                        $tute_titles = $decoded;
                    }
                }
            }

            if (empty($title)) {
                $status->title = 'Please enter a title';
            } else if (strlen($title) > 100) {
                $status->title = 'Title must be less than 100 characters';
            }

            if (!empty($description) && strlen($description) > 200) {
                $status->description = 'Description must be less than 1000 characters';
            }

            if ($enabled_payment_checked) {
                if (empty($payment_month) || $payment_month == 0) {
                    $status->payment_month = 'Please select a payment month';
                }
            }

            if (!($online || $courier)) {
                $status->type = 'Please select a valid type';
            }

            if ($online) {
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
                        } else if (strlen($tute_name) > 100) {
                            $status->tute[] = "File '$tute_name' exceeds the maximum allowed character limit of 100.";
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
                } else {
                    $status->tute[] = "Please select tutes documents";
                }
            }

            if ($courier) {
                $tute_titles = array_filter($tute_titles, function ($value) {
                    return trim($value) !== '';
                });

                if (count($tute_titles) == 0) {
                    $status->tute_title = 'Please add at least one courier tute title';
                } else {
                    foreach ($tute_titles as $tute_title) {
                        $tute_title = trim($tute_title);

                        if (strlen($tute_title) > 100) {
                            $status->tute_title = 'Courier tute title must be less than 100 characters';
                            break;
                        }
                    }
                }
            }


            $requiredProperties = [
                'title',
                'description',
                'type',
                'class',
                'payment_month',
                'tute',
                'tute_title'
            ];
            $allGood = true;
            foreach ($requiredProperties as $property) {
                if (property_exists($status, $property)) {
                    $allGood = false;
                    break;
                }
            }

            if ($allGood) {
                $timezone = new DateTimeZone("Asia/Colombo");
                $dateTime = new DateTime("now", $timezone);
                $addTime = $dateTime->format('Y-m-d H:i:s');

                $tute_type = $online ? 1 : 2;
                $checked_payment = $enabled_payment_checked ? 1 : 0;

                $insert_col = "`classes_class_id`,`tute_title`,`tute_description`,`tute_types_tute_type_id`,`date_added`";
                $insert_val = "'" . $class_id . "','" . $title . "','" . $description . "','" . $tute_type . "','" . $addTime . "'";

                if ($enabled_payment_checked) {
                    $insert_col .= ",`payment_month`";
                    $insert_val .= ",'" . $payment_month . "'";
                }

                Database::iud("INSERT INTO `class_tutes` (" . $insert_col . ") VALUES (" . $insert_val . ")");
                $tute_id = Database::$connection->insert_id;

                if ($online) {
                    if (isset($_FILES["tutes"])) {
                        for ($i = 0; $i < count($_FILES["tutes"]["name"]); $i++) {
                            $originalFileName = $_FILES["tutes"]["name"][$i];
                            $attachment_insert_columns = "`attachment`,`attachment_name`,`class_tutes_class_tute_id`,`date_added`";
                            $attachment_insert_values = "'" . $tutes[$i] . "','" . $originalFileName . "','" . $tute_id . "','" . $addTime . "'";

                            Database::iud("INSERT INTO `class_tutes_has_attachments` (" . $attachment_insert_columns . ") VALUES (" . $attachment_insert_values . ")");

                            $tute_path = Project::getApiAssetsPath() . "/uploads/tutes-attachments/" . $tutes[$i];
                            move_uploaded_file($_FILES["tutes"]["tmp_name"][$i], $tute_path);
                        }
                    }
                }

                if ($courier) {
                    foreach ($tute_titles as $tute_title) {
                        $insert_col = "`class_tutes_class_tute_id`,`attachment_name`,`date_added`";
                        $insert_val = "'" . $tute_id . "','" . $tute_title . "','" . $addTime . "'";
                        Database::iud("INSERT INTO `class_tutes_has_attachments` (" . $insert_col . ") VALUES (" . $insert_val . ")");
                    }
                }

                http_response_code(200);
                $status->done = 'Tute has been added successfully';
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

<?php
$status = new stdClass();
require_once("../../includes/UserClass.php");
require_once("../../includes/connection.php");
require_once("../../includes/UserPermissionsClass.php");
require_once("../../includes/Project.php");
if (SessionUser::ifUserIsTutor()) {
    $sessionUserObject = SessionUser::getCurrentUserObject('Tutor');
    if ($_SERVER['REQUEST_METHOD'] == 'POST') {

        $subject = isset($_POST['subject']) ? $_POST['subject'] : '';
        $exam_type = isset($_POST['exam_type']) ? $_POST['exam_type'] : '';
        $year = isset($_POST['year']) ? $_POST['year'] : '';
        $medium = isset($_POST['medium']) ? $_POST['medium'] : '';
        $name = isset($_POST['name']) ? $_POST['name'] : '';
        $class_cover;

        if ($subject == 0) {
            $status->subject = "Please select subject";
        }

        if ($exam_type == 0) {
            $status->exam_type = "Please select exam type";
        }

        if ($year == 0) {
            $status->year = "Please select year";
        }

        if ($medium == 0) {
            $status->medium = "Please select medium";
        }

        if (!empty($name)) {
            if (strlen($name) > 100) {
                $status->name =  "Class name should be less than 100 characters";
            } else {
                $isValidSubject  = Database::search("SELECT COUNT(*) as 'count' FROM `classes` 
                WHERE `class_name`='" . $name . "' AND `subjects_subject_id` = '" . $subject . "' 
                AND `exam_type_exam_type_id` = '" . $exam_type . "' 
                AND `year_year_id` = '" . $year . "' 
                AND `medium_medium_id` = '" . $medium . "' AND `tutor_id` = '" . $sessionUserObject['user_id'] . "'");

                $row = $isValidSubject->fetch_array();
                if ($row['count'] > 0) {
                    $status->name =  "Class name already exists";
                }
            }
        }

        if (isset($_FILES["class_cover"])) {
            $allowed_image_extentions = array("image/jpg", "image/jpeg", "image/png", "image/svg+xml");
            $file_ex = $_FILES["class_cover"]["type"];
            if (!in_array($file_ex, $allowed_image_extentions)) {
                $status->cover = "Only jpg,jpeg,png and svg allowed";
            } else {
                $new_file_extention;
                if ($file_ex == "image/jpg") {
                    $new_file_extention = ".jpg";
                } else if ($file_ex == "image/jpeg") {
                    $new_file_extention = ".jpeg";
                } else if ($file_ex == "image/png") {
                    $new_file_extention = ".png";
                } else if ($file_ex == "image/svg+xml") {
                    $new_file_extention = ".svg";
                }
                if ($_FILES["class_cover"]["error"] === 0) {
                    if ($_FILES["class_cover"]["size"] < 2 * 1024 * 1024) {
                        if (getimagesize($_FILES["class_cover"]["tmp_name"])) {
                            $class_cover = uniqid() . $new_file_extention;
                        } else {
                            $status->cover = "Invalid image file!";
                        }
                    } else {
                        $status->cover = "Maximum allowed file size is 2MB";
                    }
                } else {
                    $status->cover = "There is an error with the selected image";
                }
            }
        }
        $requiredProperties = [
            'name',
            'hours',
            'type',
            'cover',
            'group',
            'subject',
            'exam_type',
            'year',
            'medium'
        ];
        $allPropertiesExist = true;
        foreach ($requiredProperties as $property) {
            if (property_exists($status, $property)) {
                $allPropertiesExist = false;
                break;
            }
        }
        if ($allPropertiesExist) {
            $timezone = new DateTimeZone("Asia/Colombo");
            $dateTime = new DateTime("now", $timezone);
            $date = $dateTime->format('Y-m-d H:i:s');

            if (empty($name)) {
                $nsubject = Database::search("SELECT `subject` FROM `subjects` WHERE `subject_id` = '" . $subject . "'")->fetch_assoc()['subject'];
                $nyear = Database::search("SELECT `year` FROM `year` WHERE `year_id` = '" . $year . "'")->fetch_assoc()['year'];
                $nmedium = Database::search("SELECT `medium` FROM `medium` WHERE `medium_id` = '" . $medium . "'")->fetch_assoc()['medium'];
                $nexam_type = Database::search("SELECT `exam_type` FROM `exam_type` WHERE `exam_type_id` = '" . $exam_type . "'")->fetch_assoc()['exam_type'];

                $name = $nyear . " " . $nexam_type . " " . $nsubject . "-" . $nmedium;
            }

            $columns = "`class_name`,`medium_medium_id`,`year_year_id`,`exam_type_exam_type_id`,`subjects_subject_id`,`tutor_id`,`added_by`,`added_at`,`status_status_id`";
            $values = "'" . $name . "','" . $medium . "','" . $year . "','" . $exam_type . "','" . $subject . "','" . $sessionUserObject['user_id'] . "','" . $sessionUserObject['user_id'] . "','" . $date . "','1'";

            if (!empty($class_cover)) {
                $columns .= ", `class_cover`";
                $values .= ",'" . $class_cover . "'";
            }

            Database::iud("INSERT INTO `classes` (" . $columns . ") VALUES (" . $values . ")");

            if (isset($_FILES["class_cover"])) {
                $qimg_path = Project::getApiAssetsPath() . "/imgs/classes-covers/" . $class_cover;
                move_uploaded_file($_FILES["class_cover"]["tmp_name"], $qimg_path);
            }
            $status->done =  "The class has been successfully added";
        }
    } else {
        http_response_code(400);
        $status->error = 'Invalid request method';
    }
} else {
    $status->error = 'Authentication Failed!';
}

echo (json_encode($status));

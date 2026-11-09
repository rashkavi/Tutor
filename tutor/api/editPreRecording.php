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
        $recording_id = isset($_POST['recording_id']) ? trim($_POST['recording_id']) : 0;

        $query = "SELECT * FROM `pre_recordings` 
        INNER JOIN `classes` ON `pre_recordings`.`classes_class_id` = `classes`.`class_id`
        WHERE `pre_recording_id` = '" . $recording_id . "' AND `classes`.`tutor_id`='" . $sessionUserObject['user_id'] . "'";

        $isValidRecording = Database::search($query);
        if ($isValidRecording->num_rows == 0) {
            $status->class = 'Please select a valid class';
        } else {

            $recordingData = $isValidRecording->fetch_assoc();

            $title        = isset($_POST['title']) ? trim($_POST['title']) : '';
            $description  = isset($_POST['description']) ? trim($_POST['description']) : '';
            $duration     = isset($_POST['duration']) ? trim($_POST['duration']) : '';
            $link         = isset($_POST['link']) ? trim($_POST['link']) : '';
            $isYoutube    = isset($_POST['youtube']) && $_POST['youtube'] === 'true' ? 1 : 0;
            $isVideo      = isset($_POST['video']) && $_POST['video'] === 'true' ? 1 : 0;
            $rec_cover;


            if (empty($title)) {
                $status->title = 'Please enter a title';
            } else if (strlen($title) > 100) {
                $status->title = 'Title must be less than 100 characters';
            }

            if (empty($duration)) {
                $status->duration = 'Please enter a duration';
            }

            if (!($isYoutube || $isVideo)) {
                $status->type = "Please select a valid type";
            }

            if ($isYoutube) {
                if (empty($link)) {
                    $status->link = "Please enter a link";
                } elseif (!preg_match('/<iframe.*src="https:\/\/www\.youtube\.com\/embed\/[a-zA-Z0-9_-]+.*".*<\/iframe>/i', $link)) {
                    $status->link = "Recording link must be a valid YouTube iframe embed code";
                }
            }

            if ($isVideo) {
                if (empty($_FILES["video_file"]["tmp_name"])) {
                    $status->video = "Please select a valid video";
                }
            }

            if (isset($_FILES["rec_cover"])) {
                $allowed_image_extentions = array("image/jpg", "image/jpeg", "image/png", "image/svg+xml");
                $file_ex = $_FILES["rec_cover"]["type"];
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
                    if ($_FILES["rec_cover"]["error"] === 0) {
                        if ($_FILES["rec_cover"]["size"] < 2 * 1024 * 1024) {
                            if (getimagesize($_FILES["rec_cover"]["tmp_name"])) {
                                $rec_cover = uniqid() . $new_file_extention;
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


            $requiredProperties = [
                'title',
                'duration',
                'type',
                'class',
                'tutes',
                'link',
                'video',
                'cover',
                'expire',
                'description',
                'tute'
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

                $update_query = "`title`='" . Database::escape($title) . "',
               
                `duration`='" . $duration . "',";

                if (!empty($rec_cover)) {
                    $update_query .= "`recording_cover`='" . $rec_cover . "',";
                }

                if (!empty($description)) {
                    $update_query .= "`description`='" . Database::escape($description) . "',";
                } else {
                    $update_query .= "`description`='',";
                }

                if ($isYoutube) {
                    $update_query .= "`recording_link`='" . $link . "',`integrations_integration_id`= NULL";
                }

                Database::iud("UPDATE `pre_recordings` SET " . $update_query . " WHERE `pre_recording_id` = '" . $recording_id . "'");

                if (isset($_FILES["tutes"])) {
                    for ($i = 0; $i < count($_FILES["tutes"]["name"]); $i++) {
                        $originalFileName = $_FILES["tutes"]["name"][$i];
                        $attachment_insert_columns = "`attachment`,`attachment_name`,`pre_recordings_pre_recording_id`,`users_user_id`,`date_added`,`attachment_order`";
                        $attachment_insert_values = "'" . $tutes[$i] . "','" . Database::escape($originalFileName) . "','" . $recording_id . "','" . $sessionUserObject["user_id"] . "','" . $addTime . "','0'";

                        Database::iud("INSERT INTO `pre_recordings_attachments` (" . $attachment_insert_columns . ") VALUES (" . $attachment_insert_values . ")");

                        $tute_path = Project::getApiAssetsPath() . "/uploads/recordings-attachments/" . $tutes[$i];
                        move_uploaded_file($_FILES["tutes"]["tmp_name"][$i], $tute_path);
                    }
                }

                if (isset($_FILES["rec_cover"])) {
                    $rec_cover_path = Project::getApiAssetsPath() . "/imgs/pre-recording-thumbnail/" . $rec_cover;
                    move_uploaded_file($_FILES["rec_cover"]["tmp_name"], $rec_cover_path);

                    if (!empty($recordingData['recording_cover']) && file_exists(Project::getApiAssetsPath() . '/imgs/pre-recording-thumbnail/' . $recordingData['recording_cover'])) {
                        unlink(Project::getApiAssetsPath() . '/imgs/pre-recording-thumbnail/' . $recordingData['recording_cover']);
                    }
                }

                http_response_code(200);
                $status->done = 'Recording Updated Successfully!';
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

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
        $class = isset($_POST['class']) ? trim($_POST['class']) : 0;

        $query = "SELECT * FROM `classes` WHERE `class_id` = '" . $class . "'
        AND `tutor_id`='" . $sessionUserObject['user_id'] . "'";

        $isValidClassId = Database::search($query);
        if ($isValidClassId->num_rows == 0) {
            $status->class = 'Please select a valid class';
        } else {

            $classData = $isValidClassId->fetch_assoc();

            $time_table_id = isset($_POST['time_table_id']) ? trim($_POST['time_table_id']) : '';
            $title        = isset($_POST['title']) ? trim($_POST['title']) : '';
            $description  = isset($_POST['description']) ? trim($_POST['description']) : '';
            $duration     = isset($_POST['duration']) ? trim($_POST['duration']) : '';
            $expire_date = isset($_POST['expire_date']) ? trim($_POST['expire_date']) : '';
            $link         = isset($_POST['link']) ? trim($_POST['link']) : '';
            $isYoutube    = isset($_POST['youtube']) && $_POST['youtube'] === 'true' ? 1 : 0;
            $isVideo      = isset($_POST['video']) && $_POST['video'] === 'true' ? 1 : 0;
            $rec_cover;

            if (empty($time_table_id)) {
                $status->time_table = 'Please select a recorded class';
            } else {
                $isValidId = Database::search("SELECT * FROM `time_table` WHERE `classes_class_id`='" . $class . "' AND `time_table_id`='" . $time_table_id . "'");
                if ($isValidId->num_rows == 0) {
                    $status->time_table = 'Please select a valid recorded class';
                }
            }

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

            if (!empty($expire_date) && !CheckDateTime::isFutureDate($expire_date)) {
                $status->expire = "Please select a future date";
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
                } else {
                    $intergration = Database::search("SELECT * FROM `integrations`
                INNER JOIN `video_intergration` ON `integrations`.`integration_id` = `video_intergration`.`integrations_integration_id`
                WHERE `users_user_id`='1'");

                    if ($intergration->num_rows == 0) {
                        $status->video = "Please add a bunny intergration and continue";
                    } else {
                        $bunnyData = $intergration->fetch_assoc();
                    }
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

            $requiredProperties = [
                'title',
                'duration',
                'type',
                'class',
                'tutes',
                'link',
                'video',
                'cover',
                'expire'
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

                $col = "`recording_title`,`time_table_time_table_id`,`users_user_id`,`added_at`,`duration`";
                $val = "'" . Database::escape($title) . "','" . $time_table_id . "','" . $sessionUserObject["user_id"] . "','" . $addTime . "','" . $duration . "'";

                if (!empty($rec_cover)) {
                    $col .= ",`recording_cover`";
                    $val .= ",'" . $rec_cover . "'";
                }

                if (!empty($description)) {
                    $col .= ",`recording_description`";
                    $val .= ",'" . Database::escape($description) . "'";
                }

                if (!empty($expire_date)) {
                    $col .= ",`expire_date`";
                    $val .= ",'" . $expire_date . "'";
                }

                if ($isYoutube) {
                    $col .= ",`recording_link`";
                    $val .= ",'" . $link . "'";
                } else if ($isVideo) {

                    $BUNNY_LIBRARY_ID = $bunnyData['lib_id'];
                    $BUNNY_API_KEY   = $bunnyData['api_key'];

                    $videoTmpPath = $_FILES['video_file']['tmp_name'];

                    $createData = json_encode([
                        "title" => $title,
                        "description" => $description,
                        "duration" => $duration,
                    ]);


                    $ch = curl_init("https://video.bunnycdn.com/library/$BUNNY_LIBRARY_ID/videos");
                    curl_setopt($ch, CURLOPT_HTTPHEADER, [
                        "AccessKey: $BUNNY_API_KEY",
                        "Content-Type: application/json"
                    ]);
                    curl_setopt($ch, CURLOPT_POST, true);
                    curl_setopt($ch, CURLOPT_POSTFIELDS, $createData);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

                    $response = curl_exec($ch);
                    curl_close($ch);

                    $result = json_decode($response, true);

                    if (!isset($result['guid'])) {
                        die("Failed to create video on Bunny");
                    }

                    $bunnyVideoId = $result['guid'];

                    // 3️⃣ Upload video file
                    $uploadUrl = "https://video.bunnycdn.com/library/$BUNNY_LIBRARY_ID/videos/$bunnyVideoId";

                    $ch = curl_init($uploadUrl);
                    curl_setopt($ch, CURLOPT_HTTPHEADER, [
                        "AccessKey: $BUNNY_API_KEY",
                        "Content-Type: application/octet-stream"
                    ]);
                    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
                    curl_setopt($ch, CURLOPT_POSTFIELDS, file_get_contents($videoTmpPath));
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

                    $uploadResponse = curl_exec($ch);
                    curl_close($ch);

                    $col .= ",`recording_link`,`integrations_integration_id`";
                    $val .= ",'" . $bunnyVideoId . "','" . $bunnyData['integration_id'] . "'";
                }

                Database::iud("INSERT INTO `lecture_recording` (" . $col . ") VALUES (" . $val . ")");
                $rec_id = Database::$connection->insert_id;

                if (isset($_FILES["rec_cover"])) {
                    $rec_cover_path = Project::getApiAssetsPath() . "/imgs/recording-thumbnail/" . $rec_cover;
                    move_uploaded_file($_FILES["rec_cover"]["tmp_name"], $rec_cover_path);
                }

                http_response_code(200);
                $status->done = 'Recording Added Successfully!';
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

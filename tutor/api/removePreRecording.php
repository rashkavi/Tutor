<?php
$status = new stdClass();
require_once("../../includes/UserClass.php");
require_once("../../includes/connection.php");
require_once("../../includes/UserPermissionsClass.php");
require_once("../../includes/Project.php");
if (SessionUser::ifUserIsTutor()) {
    $sessionUserObject = SessionUser::getCurrentUserObject('Tutor');

    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        if ($_SERVER['CONTENT_TYPE'] == 'application/json') {
            $content = trim(file_get_contents('php://input'));
            $decoded = json_decode($content, true);
            if (json_last_error() == JSON_ERROR_NONE) {

                $recording_id = isset($decoded['recording_id']) ? trim($decoded['recording_id']) : 0;

                if ($recording_id == 0) {
                    http_response_code(400);
                    $status->error = 'Invalid recording id';
                } else {
                    $isValidRecording = Database::search("SELECT * FROM `pre_recordings`
                 
                   INNER JOIN `classes` ON `pre_recordings`.`classes_class_id` = `classes`.`class_id` 
                   WHERE `pre_recording_id`='" . $recording_id . "'
                   AND `classes`.`tutor_id` = '" . $sessionUserObject["user_id"] . "'");

                    if ($isValidRecording->num_rows == 0) {
                        http_response_code(400);
                        $status->error = 'Invalid recording id';
                    } else {

                        $recordingData = $isValidRecording->fetch_assoc();

                        $attachments = Database::search("SELECT * FROM `pre_recordings_attachments` WHERE `pre_recordings_pre_recording_id`='" . $recording_id . "'");
                        if ($attachments->num_rows > 0) {
                            while ($attachmentData = $attachments->fetch_assoc()) {
                                if (!empty($attachmentData["attachment"]) && file_exists(Project::getAssetsPath() . "/uploads/recordings-attachments/" . $attachmentData["attachment"])) {
                                    unlink(Project::getAssetsPath() . "/uploads/recordings-attachments/" . $attachmentData["attachment"]);
                                }
                                Database::iud("DELETE FROM `pre_recordings_attachments` WHERE `pre_recordings_attachment_id`='" . $attachmentData["pre_recordings_attachment_id"] . "'");
                            }
                        }

                        if (!empty($recordingData['recording_cover']) && file_exists(Project::getApiAssetsPath() . '/imgs/pre-recording-thumbnail/' . $recordingData['recording_cover'])) {
                            unlink(Project::getApiAssetsPath() . '/imgs/pre-recording-thumbnail/' . $recordingData['recording_cover']);
                        }

                        Database::iud("DELETE FROM `pre_recordings` WHERE `pre_recording_id`='" . $recording_id . "'");

                        $status->done = 'Pre-Recorded class removed successfully!';
                    }
                }
            } else {
                http_response_code(400);
                $status->error = 'Invalid JSON';
            }
        } else {
            http_response_code(400);
            $status->error = 'Invalid Content-Type';
        }
    } else {
        http_response_code(400);
        $status->error = 'Invalid request method';
    }
} else {
    $status->error = 'Authentication Failed!';
}

echo (json_encode($status));

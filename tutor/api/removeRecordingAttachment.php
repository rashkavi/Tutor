<?php
$status = new stdClass();
require_once("../../includes/UserClass.php");
require_once("../../includes/connection.php");
require_once("../../includes/UserPermissionsClass.php");
if (SessionUser::ifUserIsTutor()) {

    $sessionUserObject = SessionUser::getCurrentUserObject('Tutor');
    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        if ($_SERVER['CONTENT_TYPE'] == 'application/json') {
            $content = trim(file_get_contents('php://input'));
            $decoded = json_decode($content, true);
            if (json_last_error() == JSON_ERROR_NONE) {
                $attachmentId = isset($decoded["id"]) ? $decoded["id"] : 0;

                if (empty($attachmentId) || $attachmentId == 0) {
                    $status->error = 'Invalid attachment id';
                } else {

                    $attachment = Database::search("SELECT * FROM `pre_recordings_attachments` 
                    INNER JOIN `pre_recordings` ON `pre_recordings_attachments`.`pre_recordings_pre_recording_id` = `pre_recordings`.`pre_recording_id`
                    INNER JOIN `classes` ON `pre_recordings`.`classes_class_id` = `classes`.`class_id`
                    WHERE `pre_recordings_attachment_id`='" . $attachmentId . "' AND `classes`.`tutor_id`='" . $sessionUserObject['user_id'] . "'");
                    if ($attachment->num_rows == 0) {
                        $status->error = 'Invalid attachment id';
                    } else {
                        $attachmentData = $attachment->fetch_assoc();

                        Database::iud("DELETE FROM `pre_recordings_attachments` WHERE `pre_recordings_attachment_id`='" . $attachmentId . "'");
                        if (file_exists("../assets/uploads/recordings-attachments/" . $attachmentData["attachment"])) {
                            unlink($attachment_path);
                        }

                        $status->done = 'Recording attachment removed successfully';
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

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

                $classes = isset($decoded['classes']) ? $decoded['classes'] : [];
                $send_students = isset($decoded['send_students']) ? $decoded['send_students'] : false;
                $send_parents = isset($decoded['send_parents']) ? $decoded['send_parents'] : false;
                $message = isset($decoded['message']) ? $decoded['message'] : '';
                $send_sourse = isset($decoded['send_sourse']) ? $decoded['send_sourse'] : '';

                if (empty($classes)) {
                    $status->class = 'Please select at least one class';
                } else {
                    $validClases = Database::search("SELECT * FROM `classes` WHERE 
                    `tutor_id`='" . $sessionUserObject['user_id'] . "' AND
                    `class_id` IN (" . implode(',', $classes) . ")");

                    if ($validClases->num_rows == 0) {
                        $status->class = 'Please select valid classes';
                    }
                }

                if (!($send_students || $send_parents)) {
                    $status->send = 'Please select at least one recipient';
                }

                if (empty($message)) {
                    $status->message = 'Please enter a message';
                }

                if (empty($send_sourse)) {
                    $status->sourse = 'Please select a message sourse';
                }

                $requiredProperties = [
                    'class',
                    'send',
                    'message',
                    'sourse'
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

                    $audience = ($send_students && $send_parents) ? 3 : ($send_students ? 1 : 2);

                    $messagesCount = 0;

                    $classStudents = Database::search("SELECT * FROM `students_has_classes`
                    WHERE `classes_class_id` IN (" . implode(',', $classes) . ")
                    AND `status_status_id` = 1
                    ");

                    if ($send_students) {
                        $messagesCount = $classStudents->num_rows;
                    }

                    if ($send_parents) {
                        $students = [];
                        while ($student = $classStudents->fetch_assoc()) {
                            $students[] = $student['students_student_id'];
                        }

                        if (!empty($students)) {
                            $parents = Database::search("SELECT * FROM `parents_has_students`
                        WHERE `students_student_id` IN (" . implode(',', $students) . ")
                       
                        ");

                            $messagesCount += $parents->num_rows;
                        }
                    }

                    $insert_col = "`message_source_message_source_id`,`message_audience_message_audience_id`,`message`,`date_added`,`send_messages_count`,`users_user_id`";
                    $insert_val = "'" . $send_sourse . "'," . $audience . ",'" . Database::escape($message) . "','" . $addTime . "','" . $messagesCount . "','" . $sessionUserObject['user_id'] . "'";

                    Database::iud("INSERT INTO `message_center` (" . $insert_col . ") VALUES (" . $insert_val . ")");
                    $message_id = Database::$connection->insert_id;

                    foreach ($classes as $class) {
                        $insert_col = "`message_center_message_center_id`,`classes_class_id`";
                        $insert_val = "'" . $message_id . "','" . $class . "'";

                        Database::iud("INSERT INTO `message_send_classes` (" . $insert_col . ") VALUES (" . $insert_val . ")");
                    }

                    $status->done = 'Message sent successfully';
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

echo json_encode($status);

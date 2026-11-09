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

                    $name = isset($decoded['name']) ? trim($decoded['name']) : "";
                    $desc = isset($decoded['desc']) ? trim($decoded['desc']) : "";
                    $type = isset($decoded['type']) ? (int)$decoded['type'] : 0;

                    if (empty($name)) {
                        $status->name = "Please enter integration name";
                    } else if (strlen($name) > 45) {
                        $status->name = "Integration name should be less than 45 characters";
                    }

                    if (!empty($desc) && strlen($desc) > 255) {
                        $status->desc = "Integration description should be less than 255 characters";
                    }

                    if (empty($type) || $type == 0) {
                        $status->type = "Please select integration type";
                    }

                    $requiredProperties = [
                        'name',
                        'desc',
                        'type',
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
                        $date = $dateTime->format('Y-m-d H:i:s');

                        $col = "`name`,`short_description`,`integration_type_integration_type_id`,`created_at`,`status_status_id`,`users_user_id`";
                        $val = "'" . $name . "','" . $desc . "','" . $type . "','" . $date . "','1','" . $sessionUserObject["user_id"] . "'";


                        Database::iud("INSERT INTO `integrations` (" . $col . ") VALUES (" . $val . ")");

                        $status->done = 'The integration has been successfully added';
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

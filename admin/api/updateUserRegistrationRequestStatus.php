<?php
$status = new stdClass();
require_once("../../includes/UserClass.php");
require_once("../../includes/connection.php");
require_once("../../includes/UserPermissionsClass.php");
if (SessionUser::ifUserIsAdmin()) {
    $sessionUserObject = SessionUser::getCurrentUserObject('Admin');
    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        if ($_SERVER['CONTENT_TYPE'] == 'application/json') {
            $content = trim(file_get_contents('php://input'));
            $decoded = json_decode($content, true);
            if (json_last_error() == JSON_ERROR_NONE) {

                $userId = isset($decoded['userId']) ? trim($decoded['userId']) : '';
                $updateStatus = isset($decoded['status']) ? trim($decoded['status']) : '';
                $plan = isset($decoded['plan']) ? trim($decoded['plan']) : '';
                $note = isset($decoded['note']) ? trim($decoded['note']) : '';

                if (empty($userId)) {
                    $status->error = 'Please select a user';
                } else {
                    $validUser = Database::search("SELECT * FROM `requested_users` WHERE `requested_user_id`='" . $userId . "'");
                    if ($validUser->num_rows == 0) {
                        $status->error = 'Please select a valid user';
                    } else {

                        $userData = $validUser->fetch_assoc();

                        if (empty($updateStatus)) {
                            $status->status = 'Please select a status';
                        } else if (empty($plan)) {
                            $status->plan = 'Please select a plan';
                        } else {

                            $timezone = new DateTimeZone("Asia/Colombo");
                            $dateTime = new DateTime("now", $timezone);
                            $dateTime = $dateTime->format('Y-m-d H:i:s');

                            Database::iud("UPDATE `requested_users` 
                                SET `request_status_request_status_id`='" . $updateStatus . "'
                                WHERE `requested_user_id`='" . $userId . "'");

                            $log = "Update User Registration Request Status : " . ($updateStatus == 2 ? "Accepted" : "Declined");

                            $log_col = "`users_user_id`,`date_updated`,`log`,`requested_users_requested_user_id`";
                            $log_val = "'" . $sessionUserObject["user_id"] . "','" . $dateTime . "','" . $log . "','" . $userId . "'";

                            if (!empty($note)) {
                                $log_col .= ",`note`";
                                $log_val .= ",'" . Database::escape($note) . "'";
                            }

                            Database::iud("INSERT INTO `requested_users_logs` (" . $log_col . ") VALUES (" . $log_val . ")");

                            if ($updateStatus == 2 && $userData['request_status_request_status_id'] != 2) {
                                $user_col = "`title_title_id`,`user_fname`,`user_lname`,`user_email`,`user_mobile`,`user_whatsapp`,`user_reg_date`
                                ,`status_status_id`,`user_roles_user_role_id`,`added_by`";

                                $user_val = "'" . $userData['title_title_id'] . "','" . $userData['user_fname'] . "','" . $userData['user_lname'] . "'
                                ,'" . $userData['email'] . "','" . $userData['mobile'] . "','" . $userData['whatsapp'] . "',
                                '" . $dateTime . "','1',
                                '2','" . $sessionUserObject["user_id"] . "'";

                                Database::iud("INSERT INTO `users` (" . $user_col . ") VALUES (" . $user_val . ")");
                                $userId = Database::$connection->insert_id;

                                Database::iud("INSERT INTO `users_has_payment_plans` (`users_user_id`,`payment_plans_payment_plan_id`,`added_by`,`date_added`) 
                                VALUES ('" . $userId . "','" . $plan . "','" . $sessionUserObject["user_id"] . "','" . $dateTime . "')");
                            }

                            $status->done = "User registration request status updated successfully!";
                        }
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

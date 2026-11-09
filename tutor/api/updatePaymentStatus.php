<?php
$status = new stdClass();
require_once("../../includes/UserClass.php");
require_once("../../includes/connection.php");
require_once("../../includes/UserPermissionsClass.php");
if (SessionUser::hasValidSession()) {
    $sessionUserObject = SessionUser::getCurrentUserObject('Tutor');

    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        if ($_SERVER['CONTENT_TYPE'] == 'application/json') {
            $content = trim(file_get_contents('php://input'));
            $decoded = json_decode($content, true);
            if (json_last_error() == JSON_ERROR_NONE) {

                $paymentId = isset($decoded['paymentId']) ? $decoded['paymentId'] : 0;
                $paymentStatus = isset($decoded['paymentStatus']) ? $decoded['paymentStatus'] : 0;
                $note = isset($decoded['note']) ? $decoded['note'] : '';

                if ($paymentId == 0) {
                    $status->error = "Invalid payment id";
                } else {
                    $isValidPaymentId = Database::search("SELECT * FROM `student_has_payments` WHERE `student_has_payment_id` = '" . $paymentId . "'");
                    if ($isValidPaymentId->num_rows == 0) {
                        $status->error = "Invalid payment id";
                    } else {

                        $query = "SELECT * FROM `students_has_classes`
                            INNER JOIN `classes` ON `students_has_classes`.`classes_class_id` = `classes`.`class_id` 
                            WHERE `students_has_class_id` = '" . $isValidPaymentId->fetch_assoc()["students_has_class_students_has_class_id"] . "'";

                        if (SessionUser::ifUserIsTutor()) {
                            $query .= " AND `classes`.`tutor_id`='" . $sessionUserObject['user_id'] . "'";
                        }

                        $validAccess = Database::search($query);

                        if ($validAccess->num_rows == 0) {
                            $status->error = "You don't have access to this class";
                        } else {

                            $coursePaymentData = $isValidPaymentId->fetch_assoc();
                            $isValidPaymentStatus = Database::search("SELECT * FROM `payment_status` WHERE `payment_status_id` = '" . $paymentStatus . "'");
                            if ($isValidPaymentStatus->num_rows == 0) {
                                $status->status = "Invalid payment status";
                            } else {
                                $paymentCurrentStatus = Database::search("SELECT `payment_status_payment_status_id` FROM `student_has_payments` WHERE `student_has_payment_id` = '" . $paymentId . "'");
                                $row = $paymentCurrentStatus->fetch_assoc();
                                if ($row["payment_status_payment_status_id"] == $paymentStatus) {
                                    $status->status = "This payment status already updated";
                                }
                            }

                            if (!empty($note) && strlen($note) > 100) {
                                $status->note = "Note must be less than 100 characters";
                            }

                            if (!property_exists($status, "status") && !property_exists($status, "note")) {
                                $timezone = new DateTimeZone("Asia/Colombo");
                                $dateTime = new DateTime("now", $timezone);
                                $date = $dateTime->format('Y-m-d H:i:s');

                                Database::iud("UPDATE `student_has_payments` SET `payment_status_payment_status_id` = '" . $paymentStatus . "' WHERE `student_has_payment_id` = '" . $paymentId . "'");


                                $log = "Update Payment Status : " . ($paymentStatus == 3 ? "Accepted" : "Declined");
                                $log_col = "`student_has_payments_student_has_payment_id`,`users_user_id`,`date_updated`,`log`";
                                $log_val = "'" . $paymentId . "','" . $sessionUserObject["user_id"] . "','" . $date . "','" . $log . "'";

                                if (!empty($note)) {
                                    $log_col .= ",`note`";
                                    $log_val .= ",'" . Database::escape($note) . "'";
                                }

                                Database::iud("INSERT INTO `student_has_payment_logs` (" . $log_col . ") VALUES (" . $log_val . ")");

                                $status->done = "Payment status updated successfully!";
                            }
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

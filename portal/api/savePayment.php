<?php
$status = new stdClass();
require_once('../../includes/UserClass.php');
require_once('../../includes/connection.php');
require_once('../../includes/Project.php');
if (SessionUser::ifUserIsStudent()) {
    $sessionUserObject = SessionUser::getCurrentUserObject('Student');

    if ($_SERVER['REQUEST_METHOD'] == 'POST') {

        $payment_id = isset($_POST['payment_id']) ? $_POST['payment_id'] : 0;
        $method = isset($_POST['method']) ? $_POST['method'] : 0;
        $payment_slip;

        if (empty($payment_id) || $payment_id == 0) {
            $status->error = "Invalid payment id";
        } else {

            $query = "
            SELECT 
                `cpp`.`student_has_payment_id`
                                                                                  
            FROM `student_has_payments` `cpp`
                      
            INNER JOIN `students_has_classes` `shi` 
                ON `cpp`.`students_has_class_students_has_class_id` = `shi`.`students_has_class_id`
                                                                                                      
            WHERE `shi`.`students_student_id`='" . $sessionUserObject['student_id'] . "'
            AND `cpp`.`student_has_payment_id`='" . $payment_id . "'
            ";

            $isValidPaymentId = Database::search($query);

            if ($isValidPaymentId->num_rows == 0) {
                $status->error = "Invalid payment id";
            } else {
                $paymentData = $isValidPaymentId->fetch_assoc();

                if (empty($method) || $method == 0) {
                    $status->error = "Please select a payment method";
                }

                $paymentSlips = array();

                if (isset($_FILES["slips"])) {
                    $allowed_slip_extensions = array(
                        "application/pdf",
                        "image/jpeg",
                        "image/png",
                        "image/jpg"
                    );

                    foreach ($_FILES["slips"]["name"] as $key => $slip_name) {
                        $slip_ex = $_FILES["slips"]["type"][$key];
                        if (!in_array($slip_ex, $allowed_slip_extensions)) {
                            $status->slip[] = "File '$slip_name' is not a valid type. Only PDF, JPEG, PNG and JPG files are allowed.";
                        } else {
                            $new_slip_extension = '';
                            switch ($slip_ex) {
                                case "application/pdf":
                                    $new_slip_extension = ".pdf";
                                    break;
                                case "image/jpeg":
                                    $new_slip_extension = ".jpg";
                                    break;
                                case "image/png":
                                    $new_slip_extension = ".png";
                                    break;
                                case "image/jpg":
                                    $new_slip_extension = ".jpg";
                                    break;
                            }

                            if ($_FILES["slips"]["error"][$key] === 0) {
                                if ($_FILES["slips"]["size"][$key] <= 10 * 1024 * 1024) {
                                    $slipHash = hash_file('sha256', $_FILES["slips"]["tmp_name"][$key]);
                                    $isValidSlip = Database::search("SELECT * FROM `student_has_payments_slips` WHERE `slip_hash` = '" . $slipHash . "'");
                                    if ($isValidSlip->num_rows > 0) {
                                        $status->slip[] = $slip_name . " - Duplicate receipt detected. This payment slip was already used.";
                                    } else {
                                        $slip = 'R' . $payment_id . '_' . uniqid() . $new_slip_extension;
                                        array_push($paymentSlips, $slip);
                                    }
                                } else {
                                    $status->slip[] = "File '$slip_name' exceeds the maximum allowed size of 10MB.";
                                }
                            } else {
                                $status->slip[] = "There was an error uploading the file '$slip_name'.";
                            }
                        }
                    }
                } else {
                    $status->slip = "Please select payment slip";
                }
                $requiredProperties = [
                    'method',
                    'slip'
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

                    $payment_update_query = "`date_paid`='" . $date . "',`payment_method_payment_method_id`='" . $method . "',`payment_status_payment_status_id`='2'";

                    Database::iud("UPDATE `student_has_payments` SET " . $payment_update_query . " WHERE `student_has_payment_id`='" . $payment_id . "'");

                    $payment_slip_insert_columns = "`student_has_payments_student_has_payment_id`,`slip_name`,`reference`,`slip_hash`";

                    for ($i = 0; $i < count($_FILES["slips"]["name"]); $i++) {
                        $slipHash = hash_file('sha256', $_FILES["slips"]["tmp_name"][$i]);
                        $refference = strtoupper(substr($slipHash, 0, 10));

                        Database::iud("INSERT INTO `student_has_payments_slips` (" . $payment_slip_insert_columns . ") VALUES ('" . $payment_id . "','" . $paymentSlips[$i] . "','" . $refference . "','" . $slipHash . "')");
                        $payment_slip_path = Project::getApiAssetsPath() . "/uploads/payment-slips/" . $paymentSlips[$i];
                        move_uploaded_file($_FILES["slips"]["tmp_name"][$i], $payment_slip_path);
                    }

                    $status->done = "Payment successfully";
                }
            }
        }
    } else {
        http_response_code(400);
        $status->error = 'Invalid request method';
    }
} else {
    $status->error = 'Authentication Failed1!';
}


echo (json_encode($status));

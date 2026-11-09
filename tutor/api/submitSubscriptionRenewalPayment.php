<?php
$status = new stdClass();
require_once("../../includes/UserClass.php");
require_once("../../includes/connection.php");
require_once("../../includes/DateTimeClass.php");
require_once("../../includes/Project.php");
if (SessionUser::ifUserIsTutor()) {
    $sessionUserObject = SessionUser::getCurrentUserObject('Tutor');
    if ($_SERVER['REQUEST_METHOD'] == 'POST') {

        $payment_id = isset($_POST['payment_id']) ? trim($_POST['payment_id']) : 0;
        $method = isset($_POST['method']) ? trim($_POST['method']) : '';

        $payment = Database::search("SELECT * FROM `users_has_payment_plans_payments` 
        INNER JOIN `users_has_payment_plans` ON `users_has_payment_plans_payments`.`users_has_payment_plans_users_has_payment_plan_id` = `users_has_payment_plans`.`users_has_payment_plan_id`
        INNER JOIN `payment_plans` ON `users_has_payment_plans`.`payment_plans_payment_plan_id` = `payment_plans`.`payment_plan_id`
        WHERE `users_has_payment_plans_payment_id`='" . $payment_id . "'
        AND `users_has_payment_plans`.`users_user_id`='" . $sessionUserObject['user_id'] . "'
        AND `payment_status_payment_status_id` = '1'");

        if ($payment->num_rows == 0) {
            http_response_code(400);
            $status->error = 'Invalid renewal payment id';
        } else {
            $paymentData = $payment->fetch_assoc();

            if (empty($method)) {
                http_response_code(400);
                $status->error = 'Payment method is required';
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
                                $isValidSlip = Database::search("SELECT * FROM `users_payments_slips` WHERE `slip_hash` = '" . $slipHash . "'");
                                if ($isValidSlip->num_rows > 0) {
                                    $status->slip[] = $slip_name . " - Duplicate receipt detected. This payment slip was already used.";
                                } else {
                                    $slip = 'EN_' . uniqid() . $new_slip_extension;
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
                http_response_code(400);
                $status->slip = 'Please upload at least one receipt';
            }

            $requiredProperties = [
                'error',
                'slip',
                'method'
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
                $addTime = $dateTime->format('Y-m-d H:i:s');

                Database::iud("UPDATE `users_has_payment_plans_payments` SET 
                `date_paid`='" . $addTime . "',
                `payment_status_payment_status_id`='2',
                `payment_method_payment_method_id`='" . $method . "' 
                WHERE `users_has_payment_plans_payment_id`='" . $payment_id . "'");

                if (isset($_FILES["slips"])) {
                    $payment_slip_insert_columns = "`users_has_payment_plans_payment_id`,`slip`,`refference`,`slip_hash`";

                    for ($i = 0; $i < count($_FILES["slips"]["name"]); $i++) {
                        $slipHash = hash_file('sha256', $_FILES["slips"]["tmp_name"][$i]);
                        $reference = strtoupper(substr($slipHash, 0, 10));

                        Database::iud("INSERT INTO `users_payments_slips` (" . $payment_slip_insert_columns . ") VALUES ('" . $payment_id . "','" . $paymentSlips[$i] . "','" . $reference . "','" . $slipHash . "')");
                        $payment_slip_path = Project::getApiAssetsPath() . "/uploads/users-payment-slips/" . $paymentSlips[$i];
                        move_uploaded_file($_FILES["slips"]["tmp_name"][$i], $payment_slip_path);
                    }
                }

                $status->done = 'Subscription Renewal payment submitted Successfully!';
            } else {
                http_response_code(400);
                $status->error = 'Please upload at least one receipt';
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

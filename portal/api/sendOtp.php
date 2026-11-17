<?php
$status = new stdClass();
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if ($_SERVER['CONTENT_TYPE'] == 'application/json') {
        $content = trim(file_get_contents('php://input'));
        $decoded = json_decode($content, true);
        if (json_last_error() == JSON_ERROR_NONE) {
            $mobile = $decoded['mobile'];

            $normalizedWhatsapp = normalizePhoneNumber($mobile);
            if (empty($mobile)) {
                $status->mobile =  "Please enter your mobile number";
            } else if (!$normalizedWhatsapp) {
                $status->mobile =  "Please enter a valid mobile number";
            }

            if (!property_exists($status, 'mobile')) {
                require_once("../../includes/connection.php");
                $user = Database::search("SELECT * FROM `students`
                INNER JOIN `login_details` ON `login_details`.`login_details_id` = `students`.`login_details_login_details_id`
                WHERE (`whatsapp`='" . Database::escape((string) $normalizedWhatsapp) . "' OR `whatsapp`='" . Database::escape((string) $mobile) . "')");

                if ($user->num_rows > 0) {
                    $data = $user->fetch_assoc();
                    if ($data['status_status_id'] == 1) {
                        // sent on WhatsApp by includes/WhatsAppOtp.php (token from WHATSAPP_TOKEN, not from this file)
                        require_once("../../includes/WhatsAppOtp.php");
                        $otp = whatsapp_otp_for((string) $normalizedWhatsapp);
                        if ($otp === null) {
                            $status->error = "OTP could not be sent. Please try again.";
                        } else {
                            date_default_timezone_set("Asia/Colombo");
                            $date = date('Y-m-d H:i:s');
                            Database::iud("UPDATE `login_details` SET `reset_time`='" . $date . "',`verification`='" . $otp . "' WHERE `login_details_id`='" . $data['login_details_id'] . "'");

                            $status->done = "OTP sent successfully";
                        }
                    } else {
                        $status->mobile =  "Your account is deactivated.";
                    }
                } else {
                    $status->mobile = "Incorrect mobile number";
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

echo (json_encode($status));


function normalizePhoneNumber($number)
{
    $number = preg_replace('/[\s\-()]/', '', $number);
    if (preg_match('/^0([7][0-9]{8})$/', $number)) {
        return '+94' . substr($number, 1);
    }
    if (preg_match('/^94([7][0-9]{8})$/', $number)) {
        return '+94' . substr($number, 2);
    }
    if (preg_match('/^\+?[1-9][0-9]{7,14}$/', $number)) {
        if ($number[0] !== '+') {
            return '+' . $number;
        }
        return $number;
    }

    return false;
}

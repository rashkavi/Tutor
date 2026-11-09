<?php
$status = new stdClass();
require_once('../../includes/UserClass.php');
require_once('../../includes/connection.php');
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if ($_SERVER['CONTENT_TYPE'] == 'application/json') {
        $content = trim(file_get_contents('php://input'));
        $decoded = json_decode($content, true);
        if (json_last_error() == JSON_ERROR_NONE) {
            $mobile = $decoded['mobile'];
            $otp = $decoded['otp'];

            $normalizedWhatsapp = normalizePhoneNumber($mobile);
            if (empty($mobile)) {
                $status->mobile =  "Please enter your whatsApp number";
            } else if (!$normalizedWhatsapp) {
                $status->mobile =  "Please enter a valid whatsApp number";
            }

            if (empty($otp)) {
                $status->otp = "Please enter your otp";
            }

            if (!property_exists($status, 'mobile') && !property_exists($status, 'otp')) {
                $user = Database::search("SELECT * FROM `students` 
                INNER JOIN `login_details` ON `login_details`.`login_details_id` = `students`.`login_details_login_details_id` 
                WHERE (`whatsapp`='" . $normalizedWhatsapp . "' OR `whatsapp`='" . $mobile . "')");
                if ($user->num_rows > 0) {
                    $data = $user->fetch_assoc();
                    if ($data["reset_time"] > strtotime("-5 minutes")) {
                        if ($data["status_status_id"] == 1) {
                            if ($otp == $data["verification"]) {
                                $dateTime = new DateTime("now", new DateTimeZone("Asia/Colombo"));

                                $course = Database::search("SELECT * FROM `students_has_classes` WHERE `students_student_id`='" . $data["student_id"] . "' 
                                AND `status_status_id`='1'");

                                if ($course->num_rows == 0) {
                                    $status->error = 'You have no access to any course';
                                } else {

                                    Database::iud("UPDATE `login_details` SET `verification`='',`reset_time`='' WHERE `login_details_id`='" . $data["login_details_id"] . "'");

                                    $userData = [
                                        "student_id" => $data["student_id"],
                                        "login_details_id" => $data["login_details_id"],
                                        "user_fname" => $data["first_name"],
                                        "user_lname" => $data["last_name"],
                                        "user_image" => $data["profile_img"],
                                        "email" => $data["email"],
                                        "mobile" => $data["mobile"],
                                    ];

                                    SessionUser::startSession('Student', $userData);
                                    setcookie("enviiz_lms_student_login", "login", time() + 3600, "/");

                                    $status->done =  "Sign In Success";
                                }
                            } else {
                                $status->email = "";
                                $status->password = "Incorrect One-Time Password (OTP)";
                            }
                        } else {
                            $status->email = "";
                            $status->password = "Your account has been deactivated";
                        }
                    } else {
                        $status->error = "The One-Time Password (OTP) has timed out. Kindly request a new OTP to continue.";
                    }
                } else {
                    $status->email = "";
                    $status->password = "Incorrect mobile number";
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

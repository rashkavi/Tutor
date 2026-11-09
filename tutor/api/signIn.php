<?php
$status = new stdClass();
require_once("../../includes/connection.php");
require_once("../../includes/Project.php");
require_once("../../tutor/includes/security-helpers.php");
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if ($_SERVER['CONTENT_TYPE'] == 'application/json') {
        $content = trim(file_get_contents('php://input'));
        $decoded = json_decode($content, true);
        if (json_last_error() == JSON_ERROR_NONE) {
            $mobile = isset($decoded['mobile']) ? sanitizeInput($decoded['mobile']) : '';
            $otp = isset($decoded['otp']) ? sanitizeInput($decoded['otp']) : '';

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
                $user = Database::search("SELECT * FROM `users`
                INNER JOIN `user_roles` ON `users`.`user_roles_user_role_id` = `user_roles`.`user_role_id`
                
                WHERE (`user_whatsapp`='" . $mobile . "' OR `user_whatsapp`='" . $normalizedWhatsapp . "')");
                if ($user->num_rows > 0) {
                    $data = $user->fetch_assoc();
                    if ($data["user_reset_time"] > strtotime("-5 minutes")) {
                        if ($data["status_status_id"] == 1) {
                            if ($otp == $data["user_verification"]) {
                                include_once "../../includes/UserClass.php";
                                $data["user_password"] = null;

                                $userPermissions = [];

                                $userData = [
                                    "user_id" => $data["user_id"],
                                    "user_fname" => $data["user_fname"],
                                    "user_lname" => $data["user_lname"],
                                    "user_email" => $data["user_email"],
                                    "user_image" => $data["profile_img"],
                                    "user_roles_user_role_id" => $data["user_roles_user_role_id"],
                                    "user_permissions" => $userPermissions
                                ];

                                SessionUser::startSession($data['user_role'], $userData);
                                setcookie("enviiz_lms_tutor_login", "login", time() + 3600, "/");

                                require '../../vendor/autoload.php';

                                $agent = new Jenssegers\Agent\Agent();
                                $timezone = new DateTimeZone("Asia/Colombo");
                                $dateTime = new DateTime("now", $timezone);

                                $deviceType = $agent->isDesktop() ? 'Desktop' : ($agent->isPhone() ? 'Mobile' : 'Tablet');
                                $browser = sanitizeInput($agent->browser(), 100);
                                $deviceName = sanitizeInput($agent->device(), 100);
                                $os = sanitizeInput($agent->platform(), 100);
                                $ip = sanitizeInput($_SERVER['REMOTE_ADDR'], 45);
                                $loginTime = $dateTime->format('Y-m-d H:i:s');

                                $deviceCheck = Database::search("
                                        SELECT * FROM users_devices 
                                        WHERE users_user_id = '" . $data["user_id"] . "'
                                        AND device_name = '" . $deviceName . "'
                                        AND device_type = '" . $deviceType . "'
                                        AND browser = '" . $browser . "'
                                        AND os = '" . $os . "'
                                    ");

                                if ($deviceCheck->num_rows > 0) {

                                    $deviceData = $deviceCheck->fetch_assoc();

                                    Database::iud("
                                        UPDATE users_devices 
                                        SET 
                                            last_active = '" . $loginTime . "',
                                            ip_address = '" . $ip . "',
                                            is_active = 1
                                        WHERE users_devices_id = '" . $deviceData["users_devices_id"] . "'
                                    ");
                                } else {

                                    $sessionToken = bin2hex(random_bytes(32));

                                    Database::iud("
                                    INSERT INTO users_devices
                                    (
                                        users_user_id,
                                        device_name,
                                        device_type,
                                        browser,
                                        os,
                                        ip_address,
                                        session_token,
                                        login_time,
                                        last_active,
                                        is_active
                                    ) VALUES (
                                        '" . $data["user_id"] . "',
                                        '" . $deviceName . "',
                                        '" . $deviceType . "',
                                        '" . $browser . "',
                                        '" . $os . "',
                                        '" . $ip . "',
                                        '" . $sessionToken . "',
                                        '" . $loginTime . "',
                                        '" . $loginTime . "',
                                        1
                                    )
                                ");
                                }

                                $status->done =  "Sign In Success";
                            } else {
                                $status->error = "Incorrect OTP";
                            }
                        } else {
                            $status->error = "Your account is deactivated.";
                        }
                    } else {
                        $status->error = "The One-Time Password (OTP) has timed out. Kindly request a new OTP to continue.";
                    }
                } else {
                    $status->email = "";
                    $status->password = "Incorrect email or password";
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

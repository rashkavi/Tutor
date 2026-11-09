<?php
$status = new stdClass();
require_once("../../includes/connection.php");
require_once("../../includes/Project.php");
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if ($_SERVER['CONTENT_TYPE'] == 'application/json') {
        $content = trim(file_get_contents('php://input'));
        $decoded = json_decode($content, true);
        if (json_last_error() == JSON_ERROR_NONE) {
            $email = $decoded['email'];
            $password = $decoded['password'];
            $rememberMe = $decoded['rememberMe'];
            if (empty($email)) {
                $status->email =  "Please enter your email";
            }
            if (empty($password)) {
                $status->password =  "Please enter your password";
            }
            if (!property_exists($status, 'email') && !property_exists($status, 'password')) {
                $user = Database::search("SELECT * FROM `users`
                INNER JOIN `user_roles` ON `users`.`user_roles_user_role_id` = `user_roles`.`user_role_id`
                
                WHERE `user_email`='" . $email . "' AND `user_roles`.`user_role_id` = 2");
                if ($user->num_rows > 0) {
                    $data = $user->fetch_assoc();
                    if (password_verify($password, $data["user_password"])) {
                        if ($data["status_status_id"] == 1) {
                            include_once "../../includes/UserClass.php";
                            $data["user_password"] = null;

                            $userData = [
                                "user_id" => $data["user_id"],
                                "user_fname" => $data["user_fname"],
                                "user_lname" => $data["user_lname"],
                                "user_email" => $data["user_email"],
                                "user_image" => $data["profile_img"],
                                "user_roles_user_role_id" => $data["user_roles_user_role_id"],
                                "user_permissions" => []
                            ];

                            SessionUser::startSession($data['user_role'], $userData);
                            setcookie("enviiz_lms_user_login", "login", time() + 3600, "/");
                            if ($rememberMe) {
                                setcookie("enviiz_lms_user_email", $email, time() + (86400 * 30), "/", "", true, true);
                                setcookie("enviiz_lms_user_password", $password, time() + (86400 * 30), "/", "", true, true);
                            }

                            require '../../vendor/autoload.php';

                            $agent = new Jenssegers\Agent\Agent();
                            $timezone = new DateTimeZone("Asia/Colombo");
                            $dateTime = new DateTime("now", $timezone);

                            $deviceType = $agent->isDesktop() ? 'Desktop' : ($agent->isPhone() ? 'Mobile' : 'Tablet');
                            $browser = $agent->browser();
                            $deviceName = $agent->device();
                            $os = $agent->platform();
                            $ip = $_SERVER['REMOTE_ADDR'];
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

                                $sessionToken = bin2hex(random_bytes(32)); // strong token

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
                            $status->email = "";
                            $status->password = "Your account has been deactivated";
                        }
                    } else {
                        $status->email = "";
                        $status->password = "Incorrect email or password";
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

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
                $user = Database::search("SELECT * FROM `users`
                INNER JOIN `user_roles` ON `users`.`user_roles_user_role_id` = `user_roles`.`user_role_id`
                WHERE (`user_whatsapp`='" . $normalizedWhatsapp . "' OR `user_whatsapp`='" . $mobile . "')
                AND `user_roles`.`user_role_id` = 1");

                if ($user->num_rows > 0) {
                    $data = $user->fetch_assoc();
                    if ($data['status_status_id'] == 1) {
                        $otp = random_int(100000, 999999);
                        $curl = curl_init();
                        curl_setopt_array($curl, array(
                            CURLOPT_URL => 'https://graph.facebook.com/v22.0/470586202805184/messages',
                            CURLOPT_RETURNTRANSFER => true,
                            CURLOPT_POST => true,
                            CURLOPT_HTTPHEADER => array(
                                "Authorization: Bearer EAAGrCyhRGJ8BQZCo1i0polWgFjEUboynGwLlavuwj4vFJYtZBL6kdqXItxIT3fBZCOkElPIlGE7A2oLnXVASJDOQKjKItJ3G43MSrc3GHPdrruQgzam7scrzXi8hf6dRzqCmu83kA0Nvn0YLfoqdTmVHuqrvoZB97c1DdEr2Rm7jr3R80yxyDUxIul3d3ZBcMnwZDZD",
                                "Content-Type: application/json"
                            ),
                            CURLOPT_POSTFIELDS => json_encode([
                                "messaging_product" => "whatsapp",
                                "recipient_type" => "individual",
                                "to" => $normalizedWhatsapp,
                                "type" => "template",
                                "template" => [
                                    "name" => "send_otp",
                                    "language" => [
                                        "code" => "en_US"
                                    ],
                                    "components" => [
                                        [
                                            "type" => "body",
                                            "parameters" => [
                                                [
                                                    "type" => "text",
                                                    "text" => $otp
                                                ]
                                            ]
                                        ],
                                        [
                                            "type" => "button",
                                            "sub_type" => "url",
                                            "index" => "0",
                                            "parameters" => [
                                                [
                                                    "type" => "text",
                                                    "text" => $otp
                                                ]
                                            ]
                                        ]
                                    ]
                                ]
                            ])
                        ));
                        $response = curl_exec($curl);
                        curl_close($curl);

                        $curlData = json_decode($response, true);

                        if (isset($curlData['error'])) {
                            $status->error = "OTP could not be sent. Please try again.";
                        } else {
                            date_default_timezone_set("Asia/Colombo");
                            $date = date('Y-m-d H:i:s');
                            Database::iud("UPDATE `users` SET `user_reset_time`='" . $date . "',`user_verification`='" . $otp . "' WHERE `user_id`='" . $data['user_id'] . "'");

                            $status->done = "OTP sent successfully";
                            $status->wa = $normalizedWhatsapp;
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

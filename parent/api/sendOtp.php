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
                $user = Database::search("SELECT * FROM `parents`
                WHERE `whatsapp`='" . $normalizedWhatsapp . "' OR `whatsapp`='" . $mobile . "'");

                if ($user->num_rows > 0) {
                    $data = $user->fetch_assoc();
                    $otp = random_int(100000, 999999);
                    // $curl = curl_init();
                    // curl_setopt_array($curl, array(
                    //     CURLOPT_URL => 'https://graph.facebook.com/v21.0/470586202805184/messages',
                    //     CURLOPT_RETURNTRANSFER => true,
                    //     CURLOPT_POST => true,
                    //     CURLOPT_HTTPHEADER => array(
                    //         "Authorization: Bearer EAAGrCyhRGJ8BO06DlCZCpR6425WPYUrQQoqmUu6HeShIUZCX3nFlRUSXu4IbQW22V4a6wafFQMKY7K1Pngauin60c41ObN9KYrUQ3TfEm2l1BIk5tZCmN1yAnzoqWjN2BL4XW6aoDc4mZB1B3BJfvIBtrwtucmleH2Gr2JdZAkTEmqW6ZCNq4yMiIKwqRD9gaV5ND7DboZCD8KPuxWlII45FgU5RvlnSMqJ5CgpTQ5kcBLWJ0s40y1TYHeN49mzMYNL",
                    //         "Content-Type: application/json"
                    //     ),
                    //     CURLOPT_POSTFIELDS => json_encode([
                    //         "messaging_product" => "whatsapp",
                    //         "recipient_type" => "individual",
                    //         "to" => $normalizedWhatsapp,
                    //         "type" => "template",
                    //         "template" => [
                    //             "name" => "send_otp",
                    //             "language" => [
                    //                 "code" => "en_US"
                    //             ],
                    //             "components" => [
                    //                 [
                    //                     "type" => "body",
                    //                     "parameters" => [
                    //                         [
                    //                             "type" => "text",
                    //                             "text" => $otp
                    //                         ]
                    //                     ]
                    //                 ],
                    //                 [
                    //                     "type" => "button",
                    //                     "sub_type" => "url",
                    //                     "index" => "0",
                    //                     "parameters" => [
                    //                         [
                    //                             "type" => "text",
                    //                             "text" => $otp
                    //                         ]
                    //                     ]
                    //                 ]
                    //             ]
                    //         ]
                    //     ])
                    // ));
                    // $response = curl_exec($curl);
                    // curl_close($curl);

                    // $curlData = json_decode($response, true);

                    // if (isset($curlData['error'])) {
                    //     $status->error = "OTP could not be sent. Please try again.";
                    // } else {
                    //     date_default_timezone_set("Asia/Colombo");
                    //     $date = date('Y-m-d H:i:s');
                    //     Database::iud("UPDATE `users` SET `user_reset_time`='" . $date . "',`user_verification`='" . $otp . "' WHERE `user_id`='" . $data['user_id'] . "'");

                    //     $status->done = "OTP sent successfully";
                    //     $status->otp = $otp;
                    // }

                    date_default_timezone_set("Asia/Colombo");
                    $date = date('Y-m-d H:i:s');
                    Database::iud("UPDATE `parents` SET `reset_time`='" . $date . "',`verification`='" . $otp . "' WHERE `parent_id`='" . $data['parent_id'] . "'");

                    $status->done = "OTP sent successfully";
                    $status->otp = $otp;
                } else {
                    $status->mobile = "Incorrect whatsapp number";
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

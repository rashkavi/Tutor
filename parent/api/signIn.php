<?php
$status = new stdClass();
require_once("../../includes/connection.php");
require_once("../../includes/Project.php");
require_once("../../includes/UserClass.php");
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if ($_SERVER['CONTENT_TYPE'] == 'application/json') {
        $content = trim(file_get_contents('php://input'));
        $decoded = json_decode($content, true);
        if (json_last_error() == JSON_ERROR_NONE) {
            $whatsapp = $decoded['mobile'];

            if (empty($whatsapp)) {
                $status->whatsapp =  "Please enter your whatsapp";
            }

            if (!property_exists($status, 'whatsapp')) {

                $parent = Database::search("SELECT * FROM `parents` WHERE `whatsapp`='" . normalizePhoneNumber($whatsapp) . "'");

                if ($parent->num_rows > 0) {
                    $data = $parent->fetch_assoc();

                    $userData = [
                        "user_id" => $data["parent_id"],
                        "user_fname" => $data["parent_fname"],
                        "user_lname" => $data["parent_lname"],
                        "user_email" => $data["email"],
                        "user_image" => $data["profile_img"],
                    ];

                    SessionUser::startSession('Parent', $userData);
                    setcookie("enviiz_lms_parent_login", "login", time() + 3600, "/");

                    $status->done =  "Sign In Success";
                } else {
                    $status->whatsapp = "Incorrect whatsapp number";
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

function normalizePhoneNumber(string $number, string $defaultCountryCode = '94')
{
    $number = preg_replace('/[^\d+]/', '', $number);

    if (preg_match('/^\+[1-9][0-9]{7,14}$/', $number)) {
        return $number;
    }

    if ($defaultCountryCode === '94' && preg_match('/^0[7][0-9]{8}$/', $number)) {
        return '+94' . substr($number, 1);
    }

    if (preg_match('/^[1-9][0-9]{7,14}$/', $number)) {
        return '+' . $number;
    }
    if (preg_match('/^\+[1-9][0-9]{7,14}$/', $number)) {
        return $number;
    }

    return false;
}

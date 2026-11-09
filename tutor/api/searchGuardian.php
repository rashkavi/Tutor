<?php
$status = new stdClass();
require_once("../../includes/UserClass.php");
require_once("../../includes/connection.php");
require_once("../../includes/UserPermissionsClass.php");
if (SessionUser::ifUserIsTutor()) {
    $sessionUserObject = SessionUser::getCurrentUserObject('Tutor');

    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        if ($_SERVER['CONTENT_TYPE'] == 'application/json') {
            $content = trim(file_get_contents('php://input'));
            $decoded = json_decode($content, true);

            if (json_last_error() == JSON_ERROR_NONE) {

                $whatsapp = $decoded['whatsapp'];

                $normalizedWhatsapp = normalizePhoneNumber($whatsapp);
                if (empty($whatsapp)) {
                    $status->whatsapp =  "Please enter your mobile number";
                } else if (!$normalizedWhatsapp) {
                    $status->whatsapp =  "Please enter a valid mobile number";
                }

                if (!property_exists($status, 'whatsapp')) {

                    $guardian = Database::search("SELECT * FROM `parents` WHERE (`whatsapp`='" . $whatsapp . "' OR `whatsapp`='" . $normalizedWhatsapp . "')");
                    if ($guardian->num_rows > 0) {
                        $status->guardian = $guardian->fetch_assoc();
                    } else {
                        $status->guardian = null;
                    }

                    $status->done = "Search Success";
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

echo json_encode($status);

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

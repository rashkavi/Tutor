<?php
$status = new stdClass();
require_once("../includes/connection.php");
require_once("../includes/Project.php");
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if ($_SERVER['CONTENT_TYPE'] == 'application/json') {
        $content = trim(file_get_contents('php://input'));
        $decoded = json_decode($content, true);
        if (json_last_error() == JSON_ERROR_NONE) {

            $title = isset($decoded['title']) ? trim($decoded['title']) : '';
            $nic = isset($decoded['nic']) ? trim($decoded['nic']) : '';
            $fname = isset($decoded['fname']) ? trim($decoded['fname']) : '';
            $lname = isset($decoded['lname']) ? trim($decoded['lname']) : '';
            $email = isset($decoded['email']) ? trim($decoded['email']) : '';
            $mobile = isset($decoded['mobile']) ? trim($decoded['mobile']) : '';
            $whatsapp = isset($decoded['whatsapp']) ? trim($decoded['whatsapp']) : '';


            $normalizedMobile = normalizePhoneNumber($mobile);
            $normalizedWhatsapp = normalizePhoneNumber($whatsapp);

            if (empty($title)) {
                $status->title = "Please select a title";
            }

            if (empty($nic)) {
                $status->nic = "Please enter your NIC number";
            } else if (!preg_match('/^(?:[0-9]{9}[vV]|[0-9]{12})$/', $nic)) {
                $status->nic =  "Please enter a valid NIC number";
            } else {
                $validNic = Database::search("SELECT * FROM `users` WHERE `nic`='" . $nic . "'");
                if ($validNic->num_rows > 0) {
                    $status->nic =  "NIC number already exists";
                }
            }

            if (empty($fname)) {
                $status->fname = "Please enter your first name";
            } else if (strlen($fname) > 45) {
                $status->fname = "First Name should be less than 45 characters";
            }

            if (empty($lname)) {
                $status->lname = "Please enter your last name";
            } else if (strlen($lname) > 45) {
                $status->lname = "Last Name should be less than 45 characters";
            }

            if (empty($email)) {
                $status->email = "Please enter your email";
            } else if (strlen($email) > 100) {
                $status->email = "Email should be less than 100 characters";
            } else if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $status->email = "Please enter a valid email";
            } else {
                $user = Database::search("SELECT * FROM `users` WHERE `user_email`='" . $email . "'");
                if ($user->num_rows > 0) {
                    $status->email = "Email already exists";
                }
            }

            if (empty($mobile)) {
                $status->mobile = "Please enter your mobile number";
            } else if (!normalizePhoneNumber($mobile)) {
                $status->mobile = "Please enter a valid mobile number";
            } else {
                $user = Database::search("SELECT * FROM `users` WHERE (`user_mobile`='" . $mobile . "' OR `user_mobile`='" . normalizePhoneNumber($mobile) . "')");
                if ($user->num_rows > 0) {
                    $status->mobile = "Mobile number already exists";
                }
            }

            if (empty($whatsapp)) {
                $status->whatsapp = "Please enter your WhatsApp number";
            } else if (!normalizePhoneNumber($whatsapp)) {
                $status->whatsapp = "Please enter a valid WhatsApp number";
            } else {
                $user = Database::search("SELECT * FROM `users` WHERE (`user_whatsapp`='" . $whatsapp . "' OR `user_whatsapp`='" . normalizePhoneNumber($whatsapp) . "')");
                if ($user->num_rows > 0) {
                    $status->whatsapp = "WhatsApp number already exists";
                }
            }


            $requiredProperties = [
                'title',
                'gender',
                'fname',
                'lname',
                'email',
                'mobile',
                'whatsapp',
                'nic',
                'dob',
                'payment_plan'
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
                $registerTime = $dateTime->format('Y-m-d H:i:s');

                $insert_col = "`user_fname`,`user_lname`,`email`,`mobile`,`whatsapp`,`title_title_id`,`nic`,`requested_date_time`,`request_status_request_status_id`";
                $insert_val = "'" . $fname . "','" . $lname . "','" . $email . "','" . $normalizedMobile . "','" . $normalizedWhatsapp . "','" . $title . "','" . $nic . "','" . $registerTime . "','1'";

                Database::iud("INSERT INTO `requested_users` (" . $insert_col . ") VALUES (" . $insert_val . ")");
                $status->done = "Registration request sent successfully";
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

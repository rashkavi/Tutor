<?php
// Parent sign-in, step 2: the WhatsApp number AND the 6-digit code sent by sendOtp.php (valid 5 minutes, used once).
// Before this the number alone signed a parent in, and the code was never checked.
require_once("../../includes/connection.php");
require_once("../../includes/Project.php");
require_once("../../includes/UserClass.php");
require_once("../../includes/WhatsAppOtp.php");
header('Content-Type: application/json');
$status = new stdClass();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || stripos((string) ($_SERVER['CONTENT_TYPE'] ?? ''), 'application/json') !== 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid request']);
    exit;
}
$decoded = json_decode(trim((string) file_get_contents('php://input')), true);
$mobile = trim((string) ($decoded['mobile'] ?? ''));
$otp = trim((string) ($decoded['otp'] ?? ''));
$normalized = parent_phone($mobile);

if ($mobile === '' || !$normalized) {
    $status->mobile = "Please enter a valid WhatsApp number";
}
if (!preg_match('/^[0-9]{6}$/', $otp)) {
    $status->otp = "Please enter the 6-digit code";
}
if (!property_exists($status, 'mobile') && !property_exists($status, 'otp')) {
    $parent = Database::search("SELECT * FROM `parents` WHERE `whatsapp` = '" . Database::escape($normalized) . "' OR `whatsapp` = '" . Database::escape($mobile) . "' LIMIT 1");
    $data = $parent->num_rows > 0 ? $parent->fetch_assoc() : null;
    if (!$data) {
        $status->mobile = "Incorrect WhatsApp number";
    } elseif (!otp_still_valid($data['reset_time'] ?? null) || (string) $data['verification'] === '') {
        $status->error = "The code has expired. Please ask for a new code.";
    } elseif (!hash_equals((string) $data['verification'], $otp)) {
        $status->otp = "Incorrect code. Please check the WhatsApp message.";
    } else {
        // the code works once
        Database::iud("UPDATE `parents` SET `verification` = '', `reset_time` = '' WHERE `parent_id` = '" . (int) $data['parent_id'] . "'");
        SessionUser::startSession('Parent', [
            "user_id" => $data["parent_id"],
            "user_fname" => $data["parent_fname"],
            "user_lname" => $data["parent_lname"],
            "user_email" => $data["email"],
            "user_image" => $data["profile_img"],
        ]);
        setcookie("enviiz_lms_parent_login", "login", time() + 3600, "/");
        $status->done = "Sign In Success";
    }
}
echo json_encode($status);

function parent_phone(string $number)
{
    $number = preg_replace('/[\s\-()]/', '', $number);
    if (preg_match('/^0(7[0-9]{8})$/', $number)) {
        return '+94' . substr($number, 1);
    }
    if (preg_match('/^94(7[0-9]{8})$/', $number)) {
        return '+94' . substr($number, 2);
    }
    if (preg_match('/^\+?[1-9][0-9]{7,14}$/', $number)) {
        return $number[0] === '+' ? $number : '+' . $number;
    }
    return false;
}

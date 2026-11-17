<?php
// Parent sign-in, step 1: send a 6-digit code to the parent's WhatsApp. The code is saved with the time it was sent
// and is NEVER returned in this answer (it used to be, so anyone could sign in with only a phone number).
require_once("../../includes/connection.php");
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
$normalized = parent_phone($mobile);

if ($mobile === '') {
    $status->mobile = "Please enter your WhatsApp number";
} elseif (!$normalized) {
    $status->mobile = "Please enter a valid WhatsApp number, for example 0771234567";
} else {
    $parent = Database::search("SELECT `parent_id` FROM `parents` WHERE `whatsapp` = '" . Database::escape($normalized) . "' OR `whatsapp` = '" . Database::escape($mobile) . "' LIMIT 1");
    if ($parent->num_rows === 0) {
        $status->mobile = "This WhatsApp number is not registered as a parent. Ask the tutor to add it.";
    } else {
        $parentId = (int) $parent->fetch_assoc()['parent_id'];
        $otp = whatsapp_otp_for($normalized);
        if ($otp === null) {
            $status->error = "The code could not be sent to WhatsApp. Please try again in a minute.";
        } else {
            $date = (new DateTime('now', new DateTimeZone('Asia/Colombo')))->format('Y-m-d H:i:s');
            Database::iud("UPDATE `parents` SET `reset_time` = '" . $date . "', `verification` = '" . $otp . "' WHERE `parent_id` = '" . $parentId . "'");
            $status->done = "We sent a 6-digit code to your WhatsApp";
        }
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

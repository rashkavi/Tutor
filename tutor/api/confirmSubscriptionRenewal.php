<?php
$status = new stdClass();
require_once("../../includes/UserClass.php");
require_once("../../includes/connection.php");
require_once("../../includes/DateTimeClass.php");
require_once("../../includes/Project.php");
if (SessionUser::ifUserIsTutor()) {
    $sessionUserObject = SessionUser::getCurrentUserObject('Tutor');
    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        if ($_SERVER['CONTENT_TYPE'] == 'application/json') {
            $content = trim(file_get_contents('php://input'));
            $decoded = json_decode($content, true);
            if (json_last_error() == JSON_ERROR_NONE) {
                $plan_id = isset($decoded['plan_id']) ? trim($decoded['plan_id']) : 0;

                $plan = Database::search("SELECT * FROM `users_has_payment_plans` 
        INNER JOIN `payment_plans` ON `users_has_payment_plans`.`payment_plans_payment_plan_id`=`payment_plans`.`payment_plan_id`
       WHERE `users_user_id`='" . $sessionUserObject['user_id'] . "' AND `users_has_payment_plan_id`='" . $plan_id . "'");

                if ($plan->num_rows == 0) {
                    http_response_code(400);
                    $status->error = 'Invalid payment plan';
                } else {
                    $planData = $plan->fetch_assoc();

                    $period = isset($decoded['period']) ? trim($decoded['period']) : 0;

                    if (empty($period)) {
                        http_response_code(400);
                        $status->error = 'Please select a period';
                    } else {

                        $timezone = new DateTimeZone("Asia/Colombo");
                        $dateTime = new DateTime("now", $timezone);
                        $addTime = $dateTime->format('Y-m-d H:i:s');

                        $paidAmount = $period == 1 ? $planData['per_month_amount'] : ($planData['per_month_amount'] - $planData['annual_discount']) * 12;
                        $discount = $period == 1 ? '' : $planData['annual_discount'] * 12;

                        $payment = Database::search("SELECT * FROM `users_has_payment_plans_payments` 
                        WHERE `users_has_payment_plans_users_has_payment_plan_id`='" . $planData['users_has_payment_plan_id'] . "'
                        AND `payment_status_payment_status_id`= '3'
                        ORDER BY `users_has_payment_plans_payment_id` DESC LIMIT 1");

                        if ($payment->num_rows == 0) {
                            $fromDate = new DateTime($planData['date_added']);
                            $fromDate->modify('+7 day');
                        } else {
                            $paymentData = $payment->fetch_assoc();
                            $fromDate = new DateTime($paymentData['to']);
                        }

                        $toDate = clone $fromDate;

                        if ($period == 1) {
                            $toDate->modify('+31 day');
                        } else {
                            $toDate->modify('+365 day');
                        }

                        $col = "`users_has_payment_plans_users_has_payment_plan_id`,
                        `from`,
                        `to`,
                        `date_schedule`,
                        `paid_amount`,
                        `discount_amount`,
                        `payment_status_payment_status_id`,
                        `refference`";

                        $val = "'" . $plan_id . "',
                        '" . $fromDate->format('Y-m-d') . "',
                        '" . $toDate->format('Y-m-d') . "',
                        '" . $addTime . "',
                        '" . $paidAmount . "',
                        '" . $discount . "',
                        '1',
                        '" . generateSecureUniqueId() . "'";

                        Database::iud("INSERT INTO `users_has_payment_plans_payments` (" . $col . ") VALUES (" . $val . ")");
                        $paymentId = Database::$connection->insert_id;

                        $status->done = 'Subscription Renewal Confirmed Successfully!';
                        $status->renew_id = $paymentId;
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
} else {
    $status->error = 'Authentication Failed!';
}

echo (json_encode($status));
function generateSecureUniqueId()
{
    $characters = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $randomString = '';

    for ($i = 0; $i < 8; $i++) {
        $randomString .= $characters[random_int(0, strlen($characters) - 1)];
    }

    return $randomString;
}

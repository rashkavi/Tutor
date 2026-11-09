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

                $timezone = new DateTimeZone("Asia/Colombo");
                $dateTime = new DateTime("now", $timezone);
                $date = $dateTime->format('Y-m-d');

                $class = isset($decoded['class']) ? trim($decoded['class']) : "";
                $amount              = isset($decoded['amount']) ? $decoded['amount'] : 0;
                $discountType      = isset($decoded['discountType']) ? trim($decoded['discountType']) : "";
                $discountAmount          = isset($decoded['discount']) ? floatval($decoded['discount']) : 0;
                $discountNote       = isset($decoded['discountNote']) ? trim($decoded['discountNote']) : "";
                $dueDate             = isset($decoded['dueDate']) ? $decoded['dueDate'] : null;
                $penaltyEnabled     = isset($decoded['isEnablePanelty']) ? (bool)$decoded['isEnablePanelty'] : false;
                $penaltyOption = isset($decoded['penaltyOption']) ? trim($decoded['penaltyOption']) : "";
                $penalty       = isset($decoded['penalty']) ? $decoded['penalty'] : [];

                if (empty($class)) {
                    http_response_code(400);
                    $status->error = 'Please select a class';
                } else {

                    $query = "SELECT * FROM `classes` WHERE `class_id`='" . $class . "'";

                    if (SessionUser::ifUserIsTutor()) {
                        $query .= " AND `tutor_id`='" . $sessionUserObject['user_id'] . "'";
                    }

                    $isValidClass = Database::search($query);
                    if ($isValidClass->num_rows == 0) {
                        http_response_code(400);
                        $status->error = 'Class does not exist';
                    } else {
                        $classData = $isValidClass->fetch_assoc();

                        $classPaymentPlan = Database::search("SELECT * FROM `class_payment_plans` 
                            INNER JOIN `panelty_payment_option` ON `class_payment_plans`.`panelty_payment_option_panelty_payment_option_id` = `panelty_payment_option`.`panelty_payment_option_id`
                            INNER JOIN `discount_type` ON `class_payment_plans`.`discount_type_discount_type_id` = `discount_type`.`discount_type_id`
                            WHERE `classes_class_id`='" . $class . "'");

                        if ($classPaymentPlan->num_rows > 0) {
                            http_response_code(400);
                            $status->error = 'Class already has a payment plan';
                        } else {

                            if (empty($amount) || $amount <= 0) {
                                $status->amount = 'Please enter a valid amount';
                            }

                            if (empty($dueDate)) {
                                $status->dueDate = 'Please enter a valid due date';
                            } else if ($dueDate <= 0 || $dueDate > 31) {
                                $status->dueDate = 'Please enter a valid due date';
                            }

                            if (empty($discountType)) {
                                $status->discountType = 'Please select a discount type';
                            }

                            if (empty($discountType)) {
                                $status->discountType = 'Discount type is required';
                            } else {
                                $isValidId = Database::search("SELECT COUNT(*) as 'count' FROM `discount_type` WHERE `discount_type_id` = '" . $discountType . "'");
                                $isValidIdData = $isValidId->fetch_assoc();
                                if ($isValidIdData['count'] == 0) {
                                    $status->discountType = 'Invalid discount Type';
                                }
                            }

                            if ($discountType == 2 || $discountType == 3) {
                                if (empty($discountAmount) || $discountAmount == 0 || !is_numeric($discountAmount)) {
                                    $status->discountAmount = 'Invalid discount Amount';
                                }
                            }

                            if ($penaltyEnabled) {
                                if (empty($penalty)) {
                                    $status->penaltyOption = 'Please select panelty option';
                                } else {

                                    $penaltyErrors = [];
                                    $mode          = $penalty['mode'] ?? null;
                                    $slots         = $penalty['slots'] ?? [];
                                    if ($mode == 4) {

                                        if (!is_array($slots) || empty($slots)) {
                                            $penaltyErrors['slots'] = 'At least one penalty slot is required';
                                        } else {
                                            foreach ($slots as $sIndex => $slot) {
                                                if (empty($slot['type'])) {
                                                    $penaltyErrors["slots.$sIndex.type"] = 'Penalty type is required';
                                                }

                                                if (
                                                    empty($slot['amount']) ||
                                                    !is_numeric($slot['amount']) ||
                                                    $slot['amount'] <= 0
                                                ) {
                                                    $penaltyErrors["slots.$sIndex.amount"] = 'Valid penalty amount is required';
                                                }

                                                if ($slot['type'] == 3 && $slot['amount'] > 100) {
                                                    $installmentErrors["slots.$sIndex.amount"] = 'Panelty amount cannot exceed 100%';
                                                }

                                                if (
                                                    empty($slot['days']) ||
                                                    !is_numeric($slot['days']) ||
                                                    $slot['days'] <= 0
                                                ) {
                                                    $penaltyErrors["slots.$sIndex.days"] = 'Valid penalty days is required';
                                                }
                                            }
                                        }
                                    } else {
                                        if (empty($penalty['type'])) {
                                            $penaltyErrors["slots.0.type"] = 'Penalty type is required';
                                        }
                                        if (
                                            empty($penalty['amount']) ||
                                            !is_numeric($penalty['amount']) ||
                                            $penalty['amount'] <= 0
                                        ) {
                                            $penaltyErrors["slots.0.amount"] = 'Valid penalty amount is required';
                                        }

                                        if ($penalty['type'] == 3 && $penalty['type'] > 100) {
                                            $penaltyErrors["slots.0.amount"] = 'Panelty amount cannot exceed 100%';
                                        }
                                    }
                                    if (!empty($penaltyErrors)) {
                                        $status->penalty = $penaltyErrors;
                                    }
                                }
                            }

                            $requiredProperties = [
                                'error',
                                'from',
                                'to',
                                'dueDate',
                                'discountType',
                                'discountAmount',
                                'discountNote',
                                'penaltyOption',
                                'amount'
                            ];
                            $allGood = true;
                            foreach ($requiredProperties as $property) {
                                if (property_exists($status, $property)) {
                                    $allGood = false;
                                    break;
                                }
                            }

                            if ($allGood) {
                                $timezone = new DateTimeZone("Asia/Colombo");
                                $dateTime = new DateTime("now", $timezone);
                                $date = $dateTime->format('Y-m-d H:i:s');

                                if (!$penaltyEnabled) {
                                    $penaltyOption = 1;
                                }

                                $columns = "`classes_class_id`,`amount`,`users_user_id`,
                                `date_added`,`due_date`,`discount_type_discount_type_id`,`discount`,`discount_note`,`panelty_payment_option_panelty_payment_option_id`";

                                $values = "'" . $class . "','" . $amount . "','" . $sessionUserObject['user_id'] . "',
                                '" . $date . "','" . $dueDate . "','" . $discountType . "','" . $discountAmount . "','" . $discountNote . "','" . $penaltyOption . "'";

                                Database::iud("INSERT INTO `class_payment_plans` (" . $columns . ") VALUES (" . $values . ")");

                                $plan_id = Database::$connection->insert_id;

                                if ($penaltyEnabled) {

                                    $mode   = $penalty['mode'] ?? null;
                                    $type   = isset($penalty['type']) && $penalty['type'] !== '' ? (int)$penalty['type'] : null;
                                    $amount = isset($penalty['amount']) && $penalty['amount'] !== '' ? (float)$penalty['amount'] : null;
                                    $slots = isset($penalty['slots']) && is_array($penalty['slots']) ? $penalty['slots'] : [];

                                    if ($mode == 4) {

                                        if (!empty($slots)) {
                                            foreach ($slots as $sIndex => $slot) {

                                                $slotType   = $slot['type'] ?? null;
                                                $slotAmount = $slot['amount'] ?? null;
                                                $slotDays   = $slot['days'] ?? null;

                                                $columns = "`class_payment_plans_class_payment_plan_id`,
                                                            `panelty_payment_type_panelty_payment_type_id`,
                                                            `amount`,
                                                            `days`";

                                                $values  = "'" . $plan_id . "',
                                                            '" . $slotType . "',
                                                            '" . $slotAmount . "',
                                                            '" . $slotDays . "'";

                                                Database::iud("
                                                    INSERT INTO `class_payment_plans_penalties` 
                                                    (" . $columns . ") 
                                                    VALUES 
                                                    (" . $values . ")
                                                ");
                                            }
                                        }
                                    } else {

                                        $columns = "`class_payment_plans_class_payment_plan_id`,
                                                    `panelty_payment_type_panelty_payment_type_id`,
                                                    `amount`";

                                        $values  = "'" . $plan_id . "',
                                                    '" . $type . "',
                                                    '" . $amount . "'";

                                        Database::iud("
                                            INSERT INTO `class_payment_plans_penalties` 
                                            (" . $columns . ") 
                                            VALUES 
                                            (" . $values . ")
                                        ");
                                    }
                                }

                                $log_cols = "`class_payment_plans_class_payment_plan_id`,`users_user_id`,`date_updated`,`log`";
                                $log_vals = "'" . $plan_id . "','" . $sessionUserObject['user_id'] . "',
                                '" . $date . "','Add Payment Plan'";
                                Database::iud("INSERT INTO `class_payment_plan_logs` (" . $log_cols . ") VALUES (" . $log_vals . ")");

                                $status->done = 'The payment plan has been successfully added';
                                require_once '../api/automatedClassFee.php';
                            }
                        }
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

echo json_encode($status);

function isValidDueDate($fromDateStr, $toDateStr, $dueDay)
{
    // Convert to DateTime
    $fromDate = new DateTime($fromDateStr);
    $toDate   = new DateTime($toDateStr);

    $fromDay = (int)$fromDate->format('d');
    $toDay   = (int)$toDate->format('d');
    $dueDay  = (int)$dueDay;

    // Basic validation
    if ($dueDay < 1 || $dueDay > 31) {
        return false;
    }

    // Case 1: Same year, same month, same day
    if (
        $fromDate->format('Y-m') === $toDate->format('Y-m') &&
        $fromDay === $toDay
    ) {
        // Due date must be <= that day
        return $dueDay <= $fromDay;
    }

    // Case 2: Same year & month, different days
    if ($fromDate->format('Y-m') === $toDate->format('Y-m')) {
        return $dueDay >= $fromDay && $dueDay <= $toDay;
    }

    // Case 3: Different months (allow 1–31 safely)
    return $dueDay >= 1 && $dueDay <= 31;
}

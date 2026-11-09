<?php

if (SessionUser::hasValidSession()) {
    date_default_timezone_set('Asia/Colombo');
    $now = new DateTime('now', new DateTimeZone('Asia/Colombo'));
    $datetime = $now->format('Y-m-d H:i:s');

    $paymentPlan_rs = Database::search("
    SELECT *
    FROM `class_payment_plans`
    
");

    if ($paymentPlan_rs->num_rows > 0) {

        while ($paymentPlan = $paymentPlan_rs->fetch_assoc()) {

            $dueDay = (int)$paymentPlan['due_date'];

            $year  = $now->format('Y');
            $month = $now->format('m');

            $lastDayOfMonth = (int)$now->format('t');
            if ($dueDay > $lastDayOfMonth) {
                $dueDay = $lastDayOfMonth;
            }

            $dueDate = "{$year}-{$month}-" . str_pad($dueDay, 2, '0', STR_PAD_LEFT);


            $classHasStudents = Database::search("SELECT * FROM `students_has_classes` 
    WHERE `classes_class_id` = '" . $paymentPlan['classes_class_id'] . "'
    AND `status_status_id` = 1");

            if ($classHasStudents->num_rows > 0) {
                while ($classHasStudent = $classHasStudents->fetch_assoc()) {

                    $paymentExists = Database::search("
            SELECT 1
            FROM `student_has_payments`
            WHERE `students_has_class_students_has_class_id` = '" . $classHasStudent['students_has_class_id'] . "'
            AND YEAR(`date_added`) = YEAR(CURDATE())
            AND MONTH(`date_added`) = MONTH(CURDATE())
            LIMIT 1
        ");

                    if ($paymentExists->num_rows > 0) {
                        continue;
                    }

                    $col = " 
                `students_has_class_students_has_class_id`,
                `class_payment_plans_class_payment_plan_id`,
                `amount`,
                `discount`,
                `discount_type_discount_type_id`,
                `panelty_payment_option_panelty_payment_option_id`,
                `refference`,
                `date_added`,
                `payment_status_payment_status_id`";

                    $val = "
            '" . $classHasStudent['students_has_class_id'] . "',
            '" . $paymentPlan['class_payment_plan_id'] . "',
            '" . $paymentPlan['amount'] . "',
            '" . $paymentPlan['discount'] . "',
            '" . $paymentPlan['discount_type_discount_type_id'] . "',
            '" . $paymentPlan['panelty_payment_option_panelty_payment_option_id'] . "',
            '" . generateSecureUniqueId() . "',
            '" . $datetime . "',
            '1'";

                    if ($paymentPlan['discount_note'] != null) {
                        $col .= ", `discount_note`";
                        $val .= ", '" . $paymentPlan['discount_note'] . "'";
                    }

                    if ($paymentPlan['due_date'] != null) {
                        $col .= ", `due_date`";
                        $val .= ", '" . $dueDate . "'";
                    }

                    Database::iud(
                        "INSERT INTO `student_has_payments` (" . $col . ") VALUES (" . $val . ")"
                    );

                    $studentPaymentID = Database::$connection->insert_id;

                    if ($paymentPlan['panelty_payment_option_panelty_payment_option_id'] != 1) {

                        $penalties = Database::search("SELECT * FROM `class_payment_plans_penalties` WHERE `class_payment_plans_class_payment_plan_id` = '" . $paymentPlan['class_payment_plan_id'] . "'");

                        if ($penalties->num_rows > 0) {
                            while ($penalty = $penalties->fetch_assoc()) {

                                $col = "`student_has_payments_student_has_payment_id`, `amount`,`panelty_payment_type_panelty_payment_type_id`";
                                $val = "'" . $studentPaymentID . "', '" . $penalty['amount'] . "', '" . $penalty['panelty_payment_type_panelty_payment_type_id'] . "'";

                                if ($penalty['days'] != null) {
                                    $col .= ", `days`";
                                    $val .= ", '" . $penalty['days'] . "'";
                                }

                                Database::iud(
                                    "INSERT INTO `student_has_payments_penalties` (" . $col . ") VALUES (" . $val . ")"
                                );
                            }
                        }
                    }
                }
            }
        }
    }
}

function generateSecureUniqueId()
{
    $characters = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $randomString = '';

    for ($i = 0; $i < 8; $i++) {
        $randomString .= $characters[random_int(0, strlen($characters) - 1)];
    }

    return $randomString;
}

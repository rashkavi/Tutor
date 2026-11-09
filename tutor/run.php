<?php
require_once '../includes/UserClass.php';
require_once "../includes/connection.php";
require_once "../includes/UserPermissionsClass.php";
require_once "../includes/Project.php";
if (!SessionUser::hasValidSession()) {
    header("Location: ./index");
} else {
    $sessionUserObject = SessionUser::getCurrentUserObject('Tutor');

    $payments = Database::search("SELECT * FROM `student_has_payments`
   INNER JOIN `students_has_classes` 
   ON `student_has_payments`.`students_has_class_students_has_class_id` = `students_has_classes`.`students_has_class_id`
   WHERE `classes_class_id`='3'
   ORDER BY `due_date` DESC LIMIT 10");

    while ($payment = $payments->fetch_assoc()) {
        $status = rand(2, 4);
        $method = rand(1, 4);

        Database::iud("UPDATE `student_has_payments` SET `payment_status_payment_status_id`='" . $status . "' 
        ,`date_paid`='" . date("Y-m-d h:i:s") . "',`payment_method_payment_method_id`='" . $method . "'
        WHERE `student_has_payment_id`='" . $payment["student_has_payment_id"] . "'");

        if ($method == 1 || $method == 2) {
            $payment_slip = '852478523.png';

            Database::iud("INSERT INTO `student_has_payments_slips` 
            (`student_has_payments_student_has_payment_id`, `slip_name`,`slip_hash`,`reference`) 
            VALUES ('" . $payment["student_has_payment_id"] . "', '" . $payment_slip . "', '" . md5($payment_slip) . "', '" . $payment["refference"] . "')");
        }
    }
}

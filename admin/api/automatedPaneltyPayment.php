<?php

if (SessionUser::hasValidSession()) {
    date_default_timezone_set('Asia/Colombo');

    $query = "
SELECT 
    `cp`.`student_has_payment_id`,
    `cp`.`amount`,
    `cp`.`panelty_payment_option_panelty_payment_option_id`,
    `cp`.`due_date`
FROM `student_has_payments` AS `cp`
WHERE `cp`.`payment_status_payment_status_id` = '1'
AND `cp`.`panelty_payment_option_panelty_payment_option_id` != '1'
AND `cp`.`due_date` < '" . date("Y-m-d") . "'
";

    $results = Database::search($query);
    if ($results->num_rows > 0) {
        while ($data = $results->fetch_assoc()) {
            if ($data['panelty_payment_option_panelty_payment_option_id'] == 2) {
                $ifAddedPanelty = Database::search("SELECT * FROM `student_has_penalty_payments` WHERE 
            `student_has_payments_student_has_payment_id` = '" . $data['student_has_payment_id'] . "' AND (`date_added` = '" . date("Y-m-d") . "' OR `date_added` < '" . date("Y-m-d") . "')");

                if ($ifAddedPanelty->num_rows == 0) {
                    $panelty = Database::search("SELECT * FROM `student_has_payments_penalties` WHERE `student_has_payments_student_has_payment_id` = '" . $data['student_has_payment_id'] . "'");
                    if ($panelty->num_rows > 0) {
                        $paneltyData = $panelty->fetch_assoc();
                        $amount = 0;

                        if ($paneltyData['panelty_payment_type_panelty_payment_type_id'] == 1) {
                            $amount = $paneltyData['amount'];
                        } else if ($paneltyData['panelty_payment_type_panelty_payment_type_id'] == 2) {
                            $amount = $data['amount'] / 100 * $paneltyData['amount'];
                        }

                        $columns = "`amount`,`date_added`,`student_has_payments_student_has_payment_id`";
                        $values  = "'" . $amount . "','" . date("Y-m-d") . "','" . $data['student_has_payment_id'] . "'";

                        Database::iud("INSERT INTO `student_has_penalty_payments` (" . $columns . ") VALUES (" . $values . ")");
                    }
                }
            }

            if ($data['panelty_payment_option_panelty_payment_option_id'] == 3) {
                $ifAddedPanelty = Database::search("SELECT * FROM `student_has_penalty_payments` WHERE 
            `student_has_payments_student_has_payment_id` = '" . $data['student_has_payment_id'] . "' AND `date_added` = '" . date("Y-m-d") . "'");

                if ($ifAddedPanelty->num_rows == 0) {
                    $panelty = Database::search("SELECT * FROM `student_has_payments_penalties` WHERE `student_has_payments_student_has_payment_id` = '" . $data['student_has_payment_id'] . "'");
                    if ($panelty->num_rows > 0) {
                        $paneltyData = $panelty->fetch_assoc();
                        $amount = 0;

                        if ($paneltyData['panelty_payment_type_panelty_payment_type_id'] == 1) {
                            $amount = $paneltyData['amount'];
                        } else if ($paneltyData['panelty_payment_type_panelty_payment_type_id'] == 2) {
                            $amount = $data['amount'] / 100 * $paneltyData['amount'];
                        }

                        $columns = "`amount`,`date_added`,`student_has_payments_student_has_payment_id`";
                        $values  = "'" . $amount . "','" . date("Y-m-d") . "','" . $data['student_has_payment_id'] . "'";

                        Database::iud("INSERT INTO `student_has_penalty_payments` (" . $columns . ") VALUES (" . $values . ")");
                    }
                }
            }

            if ($data['panelty_payment_option_panelty_payment_option_id'] == 4) {
                $ifAddedPanelty = Database::search("SELECT * FROM `student_has_penalty_payments` WHERE 
            `student_has_payments_student_has_payment_id` = '" . $data['student_has_payment_id'] . "' AND `date_added` = '" . date("Y-m-d") . "'");

                if ($ifAddedPanelty->num_rows == 0) {
                    $panelty = Database::search("SELECT * FROM `student_has_payments_penalties` 
                WHERE `student_has_payments_student_has_payment_id` = '" . $data['student_has_payment_id'] . "'");
                    if ($panelty->num_rows > 0) {
                        $amount = 0;

                        while ($paneltyData = $panelty->fetch_assoc()) {
                            if ($paneltyData['panelty_payment_type_panelty_payment_type_id'] == 1) {
                                $amount = $paneltyData['amount'];
                            } else if ($paneltyData['panelty_payment_type_panelty_payment_type_id'] == 2) {
                                $amount = $data['amount'] / 100 * $paneltyData['amount'];
                            }

                            $dueDate = new DateTime($data['due_date']);
                            $modifyDate = $dueDate->modify('+' . $paneltyData['days'] . ' day');

                            if ($modifyDate->format('Y-m-d') == date("Y-m-d")) {
                                $amount = $paneltyData['amount'];

                                $columns = "`amount`,`date_added`,`student_has_payments_student_has_payment_id`";
                                $values  = "'" . $amount . "','" . date("Y-m-d") . "','" . $data['student_has_payment_id'] . "'";

                                Database::iud("INSERT INTO `student_has_penalty_payments` (" . $columns . ") VALUES (" . $values . ")");
                            } else if ($modifyDate->format('Y-m-d') < date("Y-m-d")) {
                                $addedPenalty = Database::search("SELECT * FROM `student_has_penalty_payments` WHERE 
                            `student_has_payments_student_has_payment_id` = '" . $data['student_has_payment_id'] . "' AND `date_added` = '" . $modifyDate->format('Y-m-d') . "'");

                                if ($addedPenalty->num_rows == 0) {
                                    $columns = "`amount`,`date_added`,`student_has_payments_student_has_payment_id`";
                                    $values  = "'" . $amount . "','" . $modifyDate->format('Y-m-d H:i:s') . "','" . $data['student_has_payment_id'] . "'";

                                    Database::iud("INSERT INTO `student_has_penalty_payments` (" . $columns . ") VALUES (" . $values . ")");
                                }
                            }
                        }
                    }
                }
            }
        }
    }
}

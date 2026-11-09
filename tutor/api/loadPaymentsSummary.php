<?php
$status = new stdClass();
require_once("../../includes/UserClass.php");
require_once("../../includes/connection.php");
if (SessionUser::ifUserIsTutor()) {
    if ($_SERVER['REQUEST_METHOD'] == 'GET') {

        $sessionUserObject = SessionUser::getCurrentUserObject('Tutor');

        $payment = isset($_GET['payment']) ? $_GET['payment'] : 1;

        $dateCondition = "";

        if ($payment == 1) {
            $dateCondition = "AND MONTH(shp.date_paid) = MONTH(CURDATE()) 
                      AND YEAR(shp.date_paid) = YEAR(CURDATE())";
        } elseif ($payment == 2) {
            $dateCondition = "AND YEARWEEK(shp.date_paid, 1) = YEARWEEK(CURDATE(), 1)";
        } elseif ($payment == 3) {
            $dateCondition = "AND DATE(shp.date_paid) = CURDATE()";
        }

        $card = Database::search("SELECT 

        IFNULL(
            SUM(
                CASE 
                    WHEN shp.payment_status_payment_status_id = '3' THEN 
                        (shp.amount + IFNULL(spp.amount,0)
                        - CASE 
                            WHEN shp.discount_type_discount_type_id = '2' THEN shp.discount
                            WHEN shp.discount_type_discount_type_id = '3' THEN (shp.amount * shp.discount / 100)
                            ELSE 0 
                        END)
                END
            ),0
        ) AS total_income,
        
       IFNULL(
                SUM(
                    CASE 
                        WHEN shp.payment_status_payment_status_id = '1' THEN 
                            (shp.amount + IFNULL(spp.amount,0)
                            - CASE 
                                WHEN shp.discount_type_discount_type_id = '2' THEN shp.discount
                                WHEN shp.discount_type_discount_type_id = '3' THEN (shp.amount * shp.discount / 100)
                                ELSE 0
                            END)
                    END
                ),0
            ) AS awaiting_total,

            IFNULL(
                SUM(
                    CASE 
                        WHEN shp.payment_status_payment_status_id = '2' THEN 
                            (shp.amount + IFNULL(spp.amount,0)
                            - CASE 
                                WHEN shp.discount_type_discount_type_id = '2' THEN shp.discount
                                WHEN shp.discount_type_discount_type_id = '3' THEN (shp.amount * shp.discount / 100)
                                ELSE 0
                            END)
                    END
                ),0
            ) AS awaiting_approval,

            IFNULL(
                SUM(
                    CASE 
                        WHEN shp.payment_status_payment_status_id = '4' THEN 
                            (shp.amount + IFNULL(spp.amount,0)
                            - CASE 
                                WHEN shp.discount_type_discount_type_id = '2' THEN shp.discount
                                WHEN shp.discount_type_discount_type_id = '3' THEN (shp.amount * shp.discount / 100)
                                ELSE 0
                            END)
                    END
                ),0
            ) AS total_rejected
        
        FROM student_has_payments shp
        
        INNER JOIN students_has_classes shc 
        ON shp.students_has_class_students_has_class_id = shc.students_has_class_id
        
        INNER JOIN classes c 
        ON shc.classes_class_id = c.class_id
        
        LEFT JOIN student_has_penalty_payments spp 
        ON shp.student_has_payment_id = spp.student_has_payments_student_has_payment_id
        
        WHERE c.tutor_id = '" . $sessionUserObject['user_id'] . "' 
        $dateCondition");

        $cardData = $card->fetch_assoc();

        $status->labels = ['Total Income', 'Awaiting Approval', 'Awaiting Payment', 'Rejected'];
        $status->values = [
            (float)$cardData['total_income'],
            (float)$cardData['awaiting_approval'],
            (float)$cardData['awaiting_total'],
            (float)$cardData['total_rejected']
        ];
        $status->done = true;
    } else {
        http_response_code(400);
        $status->error = 'Invalid request method';
    }
} else {
    $status->error = 'Authentication Failed!';
}

echo (json_encode($status));

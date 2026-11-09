<?php
$status = new stdClass();
require_once("../../includes/UserClass.php");
require_once("../../includes/connection.php");
if (SessionUser::ifUserIsTutor()) {
    if ($_SERVER['REQUEST_METHOD'] == 'GET') {

        $sessionUserObject = SessionUser::getCurrentUserObject('Tutor');

        $card = Database::search("SELECT 
        MONTH(shp.date_paid) AS month_number,
    
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
        ) AS total_income
    
    FROM student_has_payments shp
    
    INNER JOIN students_has_classes shc 
    ON shp.students_has_class_students_has_class_id = shc.students_has_class_id
    
    INNER JOIN classes c 
    ON shc.classes_class_id = c.class_id
    
    LEFT JOIN student_has_penalty_payments spp 
    ON shp.student_has_payment_id = spp.student_has_payments_student_has_payment_id
    
    WHERE 
    c.tutor_id = '" . $sessionUserObject['user_id'] . "'
    AND YEAR(shp.date_paid) = YEAR(CURDATE())
    
    GROUP BY MONTH(shp.date_paid)
    ORDER BY MONTH(shp.date_paid)");


        $labels = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
        $values = array_fill(0, 12, 0);

        while ($row = $card->fetch_assoc()) {
            $monthIndex = $row['month_number'] - 1;
            $values[$monthIndex] = $row['total_income'] ?? 0;
        }

        $status->labels = $labels;
        $status->values = $values;
        $status->done = true;
    } else {
        http_response_code(400);
        $status->error = 'Invalid request method';
    }
} else {
    $status->error = 'Authentication Failed!';
}

echo (json_encode($status));

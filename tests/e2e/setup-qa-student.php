<?php
declare(strict_types=1);

/**
 * Makes (or resets) the QA student used by the student end-to-end tests, on a TEST database only:
 *   php tests/e2e/setup-qa-student.php
 * The student joins the two sample classes that have time tables, recordings and fees, gets the fee rows of
 * another student of those classes (awaiting payment, waiting for approval, accepted, rejected) and a fresh
 * sign-in code (OTP) that is valid for 5 minutes. WhatsApp cannot send it here, so the tests type it themselves.
 * Prints the sign-in details as JSON.
 */

use Enlixo\App;

const QA_WHATSAPP = '+94719990002';
const QA_MOBILE_TYPED = '0719990002';
const QA_OTP = '246810';

$config = require dirname(__DIR__, 2) . '/src/bootstrap.php';
$app = new App($config);
$db = $app->db();
$now = date('Y-m-d H:i:s');

// classes with the most learning data (time table, recordings, fees)
$classes = array_map('intval', array_column($db->all(
    'SELECT c.class_id FROM classes c
      ORDER BY (SELECT COUNT(*) FROM time_table t WHERE t.classes_class_id = c.class_id)
             + (SELECT COUNT(*) FROM class_payment_plans p WHERE p.classes_class_id = c.class_id) * 5 DESC, c.class_id
      LIMIT 2',
), 'class_id'));
if (!$classes) {
    fwrite(STDERR, "No classes in this database.\n");
    exit(1);
}

$studentId = (int) ($db->value(
    'SELECT s.student_id FROM students s JOIN login_details ld ON ld.login_details_id = s.login_details_login_details_id WHERE ld.whatsapp = :w',
    ['w' => QA_WHATSAPP],
) ?? 0);

if ($studentId === 0) {
    $hasPassword = (bool) $db->value("SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'login_details' AND COLUMN_NAME = 'password'");
    $loginId = $db->insert(
        'INSERT INTO login_details (first_name, last_name, whatsapp, email' . ($hasPassword ? ', password' : '') . ', date_registered, status_status_id, verification)
         VALUES (\'Qa\', \'Student\', :w, :e' . ($hasPassword ? ', :p' : '') . ', :at, 1, \'\')',
        ['w' => QA_WHATSAPP, 'e' => 'qa.student@enlixo.test', 'at' => $now] + ($hasPassword ? ['p' => password_hash('x', PASSWORD_BCRYPT)] : []),
    );
    $tutorId = (int) $db->value('SELECT tutor_id FROM classes WHERE class_id = :c', ['c' => $classes[0]]);
    $studentId = $db->insert(
        'INSERT INTO students (full_name, name_with_initials, date_of_birth, date_added, gender_gender_id, users_user_id, login_details_login_details_id, mobile, profile_img)
         VALUES (\'Qa Student\', \'Q. Student\', \'2008-05-14\', :at, 2, :t, :ld, :m, NULL)',
        ['at' => $now, 't' => $tutorId, 'ld' => $loginId, 'm' => QA_MOBILE_TYPED],
    );
}

foreach ($classes as $classId) {
    $link = (int) ($db->value('SELECT students_has_class_id FROM students_has_classes WHERE classes_class_id = :c AND students_student_id = :s', ['c' => $classId, 's' => $studentId]) ?? 0);
    if ($link === 0) {
        $link = $db->insert(
            'INSERT INTO students_has_classes (classes_class_id, students_student_id, status_status_id, added_at, added_by)
             SELECT :c, :s, 1, :at, tutor_id FROM classes WHERE class_id = :c2',
            ['c' => $classId, 's' => $studentId, 'at' => $now, 'c2' => $classId],
        );
    }
    $db->execute('UPDATE students_has_classes SET status_status_id = 1 WHERE students_has_class_id = :l', ['l' => $link]);

    // fee rows: copy the rows of another student of the class (once), then make one of each status
    if (!$db->value('SELECT 1 FROM student_has_payments WHERE students_has_class_students_has_class_id = :l', ['l' => $link])) {
        $from = (int) ($db->value(
            'SELECT sp.students_has_class_students_has_class_id FROM student_has_payments sp
               JOIN students_has_classes h ON h.students_has_class_id = sp.students_has_class_students_has_class_id
              WHERE h.classes_class_id = :c AND h.students_has_class_id <> :l
              GROUP BY 1 ORDER BY COUNT(*) DESC LIMIT 1',
            ['c' => $classId, 'l' => $link],
        ) ?? 0);
        if ($from) {
            $db->execute(
                "INSERT INTO student_has_payments (students_has_class_students_has_class_id, class_payment_plans_class_payment_plan_id, amount, discount, discount_type_discount_type_id,
                        discount_note, panelty_payment_option_panelty_payment_option_id, due_date, refference, payment_method_payment_method_id, date_added, date_paid, payment_status_payment_status_id)
                 SELECT :l, class_payment_plans_class_payment_plan_id, amount, discount, discount_type_discount_type_id, discount_note, panelty_payment_option_panelty_payment_option_id,
                        due_date, CONCAT('QA-', student_has_payment_id), payment_method_payment_method_id, date_added, date_paid, payment_status_payment_status_id
                   FROM student_has_payments WHERE students_has_class_students_has_class_id = :f",
                ['l' => $link, 'f' => $from],
            );
        }
    }
    // at least 4 fee rows (one of each status): add older months copied from the oldest row
    $have = (int) $db->value('SELECT COUNT(*) FROM student_has_payments WHERE students_has_class_students_has_class_id = :l', ['l' => $link]);
    for ($n = $have; $n > 0 && $n < 4; $n++) {
        $db->execute(
            "INSERT INTO student_has_payments (students_has_class_students_has_class_id, class_payment_plans_class_payment_plan_id, amount, discount, discount_type_discount_type_id,
                    discount_note, panelty_payment_option_panelty_payment_option_id, due_date, refference, payment_method_payment_method_id, date_added, date_paid, payment_status_payment_status_id)
             SELECT students_has_class_students_has_class_id, class_payment_plans_class_payment_plan_id, amount, discount, discount_type_discount_type_id, discount_note,
                    panelty_payment_option_panelty_payment_option_id, DATE_SUB(due_date, INTERVAL 1 MONTH), CONCAT('QA-OLD-', :n), payment_method_payment_method_id,
                    DATE_SUB(date_added, INTERVAL 1 MONTH), DATE_SUB(due_date, INTERVAL 1 MONTH), 3
               FROM student_has_payments WHERE students_has_class_students_has_class_id = :l ORDER BY due_date LIMIT 1",
            ['l' => $link, 'n' => $n],
        );
    }
    // newest due first: awaiting payment (open), waiting for approval, rejected, the older ones accepted
    $rows = array_column($db->all('SELECT student_has_payment_id id FROM student_has_payments WHERE students_has_class_students_has_class_id = :l ORDER BY due_date DESC', ['l' => $link]), 'id');
    foreach ($rows as $i => $id) {
        $status = [1, 2, 4][$i] ?? 3;
        $db->execute(
            'UPDATE student_has_payments SET payment_status_payment_status_id = :st, date_paid = IF(:st2 = 1, NULL, COALESCE(date_paid, :at)) WHERE student_has_payment_id = :id',
            ['st' => $status, 'st2' => $status, 'at' => $now, 'id' => $id],
        );
    }
}

// a fresh sign-in code, valid for 5 minutes (portal/api/signIn.php compares reset_time with now - 5 minutes)
$db->execute(
    'UPDATE login_details SET verification = :o, reset_time = :t, status_status_id = 1
      WHERE login_details_id = (SELECT login_details_login_details_id FROM students WHERE student_id = :s)',
    ['o' => QA_OTP, 't' => (string) time(), 's' => $studentId],
);

// ids the tests use: a fee of each status, a recording / tute / class day of the student's classes, and things of
// a class the student is NOT in (they must not open)
$in = implode(',', $classes);
$fee = fn (int $st) => (int) ($db->value(
    "SELECT p.student_has_payment_id FROM student_has_payments p JOIN students_has_classes h ON h.students_has_class_id = p.students_has_class_students_has_class_id
      WHERE h.students_student_id = :s AND p.payment_status_payment_status_id = :st ORDER BY p.due_date DESC LIMIT 1",
    ['s' => $studentId, 'st' => $st],
) ?? 0);
$otherClass = (int) ($db->value("SELECT class_id FROM classes WHERE class_id NOT IN ($in) AND class_id NOT IN (SELECT classes_class_id FROM students_has_classes WHERE students_student_id = :s) ORDER BY class_id LIMIT 1", ['s' => $studentId]) ?? 0);
$held = (int) $db->value(
    "SELECT COUNT(*) FROM time_table WHERE classes_class_id IN ($in) AND time_table_status_time_table_status_id = 1 AND lecture_type_lecture_type_id <> 3
        AND TIMESTAMP(date, start_time) <= :now",
    ['now' => (new DateTime('now', new DateTimeZone('Asia/Colombo')))->format('Y-m-d H:i:s')],
);
echo json_encode([
    'student_id' => $studentId, 'whatsapp' => QA_MOBILE_TYPED, 'otp' => QA_OTP, 'classes' => $classes,
    'fee_open' => $fee(1), 'fee_checking' => $fee(2), 'fee_paid' => $fee(3), 'fee_rejected' => $fee(4),
    'recording' => (int) ($db->value("SELECT r.lecture_recording_id FROM lecture_recording r JOIN time_table t ON t.time_table_id = r.time_table_time_table_id WHERE t.classes_class_id IN ($in) ORDER BY r.lecture_recording_id LIMIT 1") ?? 0),
    'tute' => $db->one("SELECT class_tute_id id, classes_class_id class_id FROM class_tutes WHERE classes_class_id IN ($in) ORDER BY class_tute_id LIMIT 1"),
    'day' => (int) ($db->value("SELECT time_table_id FROM time_table WHERE classes_class_id IN ($in) ORDER BY date DESC LIMIT 1") ?? 0),
    'held_lectures' => $held,
    'other_class' => $otherClass,
    'other_day' => (int) ($db->value('SELECT time_table_id FROM time_table WHERE classes_class_id = :c LIMIT 1', ['c' => $otherClass]) ?? 0),
    'other_tute' => (int) ($db->value('SELECT class_tute_id FROM class_tutes WHERE classes_class_id = :c LIMIT 1', ['c' => $otherClass]) ?? 0),
    'other_fee' => (int) ($db->value(
        'SELECT p.student_has_payment_id FROM student_has_payments p JOIN students_has_classes h ON h.students_has_class_id = p.students_has_class_students_has_class_id
          WHERE h.students_student_id <> :s ORDER BY p.student_has_payment_id LIMIT 1',
        ['s' => $studentId],
    ) ?? 0),
], JSON_PRETTY_PRINT), "\n";

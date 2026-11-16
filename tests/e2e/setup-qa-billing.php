<?php
declare(strict_types=1);

/**
 * Billing / lock-out QA data, on a TEST database only:
 *   php tests/e2e/setup-qa-billing.php            student + parent of the QA tutor with fresh sign-in codes
 *   php tests/e2e/setup-qa-billing.php suspend    the QA tutor's subscription is suspended (students locked out)
 *   php tests/e2e/setup-qa-billing.php active     back to active
 * Run tests/e2e/setup-qa-tutor.php first (the QA tutor and a student of theirs made by the tutor journey).
 * Old QA sign-up requests are moved back 2 hours, so the "requests per hour" limit does not stop the test.
 */
use Enlixo\App;

$config = require dirname(__DIR__, 2) . '/src/bootstrap.php';
$db = (new App($config))->db();
$tutor = (int) ($db->value("SELECT user_id FROM users WHERE user_email = 'qa.tutor@enlixo.test'") ?? 0);
if (!$tutor) {
    fwrite(STDERR, "Run tests/e2e/setup-qa-tutor.php first.\n");
    exit(1);
}
$mode = $argv[1] ?? '';
if (in_array($mode, ['suspend', 'active'], true)) {
    $db->execute(
        'UPDATE users_has_payment_plans SET status = :s, status_reason = :r WHERE users_user_id = :t ORDER BY users_has_payment_plan_id DESC LIMIT 1',
        ['s' => $mode === 'suspend' ? 'suspended' : 'active', 'r' => $mode === 'suspend' ? 'QA: not paid' : null, 't' => $tutor],
    );
    echo json_encode(['tutor' => $tutor, 'status' => $mode === 'suspend' ? 'suspended' : 'active']), "\n";
    exit;
}
$db->execute("UPDATE requested_users SET requested_date_time = DATE_SUB(requested_date_time, INTERVAL 2 HOUR) WHERE email LIKE 'qa.signup.%'");

// a student who is ONLY in the QA tutor's classes (made by the tutor journey test)
$student = (int) ($db->value(
    'SELECT h.students_student_id FROM students_has_classes h JOIN classes c ON c.class_id = h.classes_class_id
      WHERE c.tutor_id = :t AND h.status_status_id = 1
        AND NOT EXISTS (SELECT 1 FROM students_has_classes h2 JOIN classes c2 ON c2.class_id = h2.classes_class_id WHERE h2.students_student_id = h.students_student_id AND c2.tutor_id <> :t2 AND h2.status_status_id = 1)
      ORDER BY h.students_student_id LIMIT 1',
    ['t' => $tutor, 't2' => $tutor],
) ?? 0);
if (!$student) {
    fwrite(STDERR, "The QA tutor has no student yet: run tests/e2e/tutor-journey.e2e.js once.\n");
    exit(1);
}
$wa = (string) $db->value('SELECT ld.whatsapp FROM students s JOIN login_details ld ON ld.login_details_id = s.login_details_login_details_id WHERE s.student_id = :s', ['s' => $student]);
$now = date('Y-m-d H:i:s');
$db->execute(
    "UPDATE login_details SET verification = '112233', reset_time = :t, status_status_id = 1 WHERE login_details_id = (SELECT login_details_login_details_id FROM students WHERE student_id = :s)",
    ['t' => $now, 's' => $student],
);
$parent = (int) ($db->value("SELECT parent_id FROM parents WHERE whatsapp = '+94719990004'") ?? 0);
if (!$parent) {
    $parent = $db->insert(
        "INSERT INTO parents (parent_fname, parent_lname, whatsapp, mobile, email, profile_img, guardian_relation_guardian_relation_id, title_title_id, registered_at, verification, reset_time)
         VALUES ('Qa', 'Billing Parent', '+94719990004', '0719990004', 'qa.billing.parent@enlixo.test', NULL, 1, (SELECT title_id FROM title ORDER BY title_id LIMIT 1), :at, '', '')",
        ['at' => $now],
    );
}
$db->execute('DELETE FROM parents_has_students WHERE parents_parent_id = :p', ['p' => $parent]);
$db->execute('INSERT INTO parents_has_students (students_student_id, parents_parent_id, added_at) VALUES (:s, :p, :at)', ['s' => $student, 'p' => $parent, 'at' => $now]);
$db->execute("UPDATE parents SET verification = '445566', reset_time = :t WHERE parent_id = :p", ['t' => $now, 'p' => $parent]);
echo json_encode(['tutor' => $tutor, 'student' => $student, 'student_whatsapp' => '0' . substr(preg_replace('/\D/', '', $wa), -9), 'student_otp' => '112233',
    'parent' => $parent, 'parent_whatsapp' => '0719990004', 'parent_otp' => '445566'], JSON_PRETTY_PRINT), "\n";

<?php
declare(strict_types=1);

/**
 * Makes (or resets) the QA parent used by the parent end-to-end tests, on a TEST database only:
 *   php tests/e2e/setup-qa-student.php     (first: the QA student and their fees)
 *   php tests/e2e/setup-qa-parent.php [expired]
 * The parent has TWO children: the QA student and one sample student of another class (to test the child switcher),
 * and a fresh sign-in code (valid 5 minutes; 'expired' = sent 10 minutes ago). Prints the ids the tests use as JSON.
 */

use Enlixo\App;

const QA_PARENT_WHATSAPP = '+94719990003';
const QA_PARENT_TYPED = '0719990003';
const QA_PARENT_OTP = '135790';

$config = require dirname(__DIR__, 2) . '/src/bootstrap.php';
$app = new App($config);
$db = $app->db();
$now = date('Y-m-d H:i:s');

$child = (int) ($db->value(
    "SELECT s.student_id FROM students s JOIN login_details ld ON ld.login_details_id = s.login_details_login_details_id WHERE ld.whatsapp = '+94719990002'",
) ?? 0);
if ($child === 0) {
    fwrite(STDERR, "Run tests/e2e/setup-qa-student.php first.\n");
    exit(1);
}
// a second child: a sample student in a class the QA student is not in
$second = (int) ($db->value(
    'SELECT h.students_student_id FROM students_has_classes h
      WHERE h.status_status_id = 1 AND h.students_student_id <> :c
        AND h.classes_class_id NOT IN (SELECT classes_class_id FROM students_has_classes WHERE students_student_id = :c2)
      ORDER BY h.students_student_id LIMIT 1',
    ['c' => $child, 'c2' => $child],
) ?? 0);

$parentId = (int) ($db->value('SELECT parent_id FROM parents WHERE whatsapp = :w', ['w' => QA_PARENT_WHATSAPP]) ?? 0);
if ($parentId === 0) {
    $parentId = $db->insert(
        "INSERT INTO parents (parent_fname, parent_lname, whatsapp, mobile, email, profile_img, guardian_relation_guardian_relation_id, title_title_id, registered_at, verification, reset_time)
         VALUES ('Qa', 'Parent', :w, :m, 'qa.parent@enlixo.test', NULL, 1, (SELECT title_id FROM title ORDER BY title_id LIMIT 1), :at, '', '')",
        ['w' => QA_PARENT_WHATSAPP, 'm' => QA_PARENT_TYPED, 'at' => $now],
    );
}
foreach (array_filter([$child, $second]) as $sid) {
    if (!$db->value('SELECT 1 FROM parents_has_students WHERE parents_parent_id = :p AND students_student_id = :s', ['p' => $parentId, 's' => $sid])) {
        $db->execute('INSERT INTO parents_has_students (students_student_id, parents_parent_id, added_at) VALUES (:s, :p, :at)', ['s' => $sid, 'p' => $parentId, 'at' => $now]);
    }
}
$db->execute(
    'UPDATE parents SET verification = :o, reset_time = :t WHERE parent_id = :p',
    ['o' => QA_PARENT_OTP, 't' => date('Y-m-d H:i:s', time() - (($argv[1] ?? '') === 'expired' ? 600 : 0)), 'p' => $parentId],
);

// things that belong to a child of ANOTHER parent (must never open)
$stranger = (int) ($db->value(
    'SELECT s.student_id FROM students s WHERE s.student_id NOT IN (SELECT students_student_id FROM parents_has_students WHERE parents_parent_id = :p)
        AND EXISTS (SELECT 1 FROM students_has_classes h JOIN student_has_payments p ON p.students_has_class_students_has_class_id = h.students_has_class_id WHERE h.students_student_id = s.student_id)
      ORDER BY s.student_id LIMIT 1',
    ['p' => $parentId],
) ?? 0);
$myClasses = array_map('intval', array_column($db->all('SELECT DISTINCT classes_class_id c FROM students_has_classes WHERE students_student_id IN (' . $child . ',' . ($second ?: 0) . ')'), 'c'));
echo json_encode([
    'parent_id' => $parentId, 'whatsapp' => QA_PARENT_TYPED, 'otp' => QA_PARENT_OTP,
    'child' => $child, 'second_child' => $second,
    'child_class' => (int) $db->value('SELECT classes_class_id FROM students_has_classes WHERE students_student_id = :s ORDER BY classes_class_id DESC LIMIT 1', ['s' => $child]),
    'child_fee' => (int) ($db->value('SELECT p.student_has_payment_id FROM student_has_payments p JOIN students_has_classes h ON h.students_has_class_id = p.students_has_class_students_has_class_id WHERE h.students_student_id = :s AND p.payment_status_payment_status_id IN (1, 4) ORDER BY p.due_date DESC LIMIT 1', ['s' => $child]) ?? 0),
    'child_recording' => (int) ($db->value('SELECT r.lecture_recording_id FROM lecture_recording r JOIN time_table t ON t.time_table_id = r.time_table_time_table_id JOIN students_has_classes h ON h.classes_class_id = t.classes_class_id WHERE h.students_student_id = :s ORDER BY r.lecture_recording_id LIMIT 1', ['s' => $child]) ?? 0),
    'stranger' => $stranger,
    'stranger_fee' => (int) ($db->value('SELECT p.student_has_payment_id FROM student_has_payments p JOIN students_has_classes h ON h.students_has_class_id = p.students_has_class_students_has_class_id WHERE h.students_student_id = :s LIMIT 1', ['s' => $stranger]) ?? 0),
    'stranger_class' => (int) ($db->value('SELECT class_id FROM classes WHERE class_id NOT IN (' . implode(',', $myClasses ?: [0]) . ') ORDER BY class_id LIMIT 1') ?? 0),
    'stranger_recording' => (int) ($db->value('SELECT r.lecture_recording_id FROM lecture_recording r JOIN time_table t ON t.time_table_id = r.time_table_time_table_id WHERE t.classes_class_id NOT IN (' . implode(',', $myClasses ?: [0]) . ') LIMIT 1') ?? 0),
], JSON_PRETTY_PRINT), "\n";

<?php
// QA staff member for tests/e2e/staff-journey.e2e.js: removes the QA staff user (qa.staff@enlixo.test) and the QA
// roles of the QA tutor so every run starts clean.   php setup-qa-staff.php [password <token>]
//   password <email>  sets the password QaStaff#2026 for that staff user (instead of opening the e-mail link)
declare(strict_types=1);
require dirname(__DIR__, 2) . '/src/bootstrap.php';
$app = \Enlixo\Legacy\Bridge::app();
$db = $app->db();
$tutor = (int) $db->value("SELECT user_id FROM users WHERE user_email = 'qa.tutor@enlixo.test'");
if (($argv[1] ?? '') === 'password') {
    $db->execute("UPDATE users SET user_password = :p, user_verification = '', user_reset_time = '' WHERE user_email = 'qa.staff@enlixo.test'", ['p' => password_hash('QaStaff#2026', PASSWORD_DEFAULT)]);
    echo json_encode(['ok' => true]), "\n";
    exit;
}
$staff = (int) ($db->value("SELECT user_id FROM users WHERE user_email = 'qa.staff@enlixo.test'") ?? 0);
if ($staff) {
    $db->execute('DELETE FROM users_devices WHERE users_user_id = :u', ['u' => $staff]);
    $db->execute('DELETE FROM tutor_staff WHERE staff_user_id = :u', ['u' => $staff]);
    $db->execute('DELETE FROM users WHERE user_id = :u', ['u' => $staff]);
}
foreach ($db->all("SELECT tutor_role_id FROM tutor_roles WHERE tutor_id = :t AND name LIKE 'QA %'", ['t' => $tutor]) as $r) {
    $db->execute('DELETE FROM tutor_staff WHERE tutor_roles_tutor_role_id = :r', ['r' => $r['tutor_role_id']]);
    $db->execute('DELETE FROM tutor_role_permissions WHERE tutor_roles_tutor_role_id = :r', ['r' => $r['tutor_role_id']]);
    $db->execute('DELETE FROM tutor_roles WHERE tutor_role_id = :r', ['r' => $r['tutor_role_id']]);
}
echo json_encode(['tutor' => $tutor, 'removed_staff' => $staff]), "\n";

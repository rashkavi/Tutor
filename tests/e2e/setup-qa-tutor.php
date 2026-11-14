<?php
declare(strict_types=1);

/**
 * Makes (or resets) the QA tutor used by the end-to-end tests, on a TEST database only:
 *   php tests/e2e/setup-qa-tutor.php
 * The tutor is added the way the admin adds one (PlatformBilling::addTutor, STARTER, paid by bank transfer today),
 * so the subscription is Active, then gets a sign-in password. Prints the sign-in details as JSON.
 * Running it again keeps the same tutor and only resets the password and the plan check.
 */

use Enlixo\App;

const QA_EMAIL = 'qa.tutor@enlixo.test';
const QA_PASSWORD = 'QaTutor#2026';
const QA_MOBILE = '0719990001';

$config = require dirname(__DIR__, 2) . '/src/bootstrap.php';
$app = new App($config);
$db = $app->db();

$adminId = (int) $db->value('SELECT user_id FROM users WHERE user_roles_user_role_id = 1 ORDER BY user_id LIMIT 1');
if ($adminId === 0) {
    fwrite(STDERR, "No admin user in this database.\n");
    exit(1);
}

$id = (int) ($db->value('SELECT user_id FROM users WHERE user_email = :e', ['e' => QA_EMAIL]) ?? 0);
if ($id === 0) {
    $id = $app->platformBilling()->addTutor([
        'title' => (int) $db->value('SELECT title_id FROM title ORDER BY title_id LIMIT 1'),
        'first_name' => 'Qa', 'last_name' => 'Tutor', 'email' => QA_EMAIL, 'mobile' => QA_MOBILE,
        'brand' => 'QA Physics Academy', 'subject' => 'Physics', 'note' => 'End-to-end test tutor',
        'plan' => 'start', 'cycle' => 'monthly', 'start' => 'paid', 'start_date' => date('Y-m-d'), 'paid_now' => 'bank', 'reference' => 'QA-TEST',
    ], $adminId);
}

$db->execute('UPDATE users SET user_password = :p, status_status_id = 1 WHERE user_id = :u', ['p' => password_hash(QA_PASSWORD, PASSWORD_DEFAULT), 'u' => $id]);
$status = $app->platformBilling()->accessStatus($id);

echo json_encode(['id' => $id, 'email' => QA_EMAIL, 'password' => QA_PASSWORD, 'mobile' => QA_MOBILE, 'plan_status' => $status], JSON_PRETTY_PRINT), "\n";

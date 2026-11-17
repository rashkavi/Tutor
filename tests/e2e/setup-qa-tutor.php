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
// every run starts from the same place: STARTER, active, paid for 30 days (billing tests change it)
$sub = (int) ($db->value('SELECT users_has_payment_plan_id FROM users_has_payment_plans WHERE users_user_id = :u ORDER BY users_has_payment_plan_id DESC LIMIT 1', ['u' => $id]) ?? 0);
if ($sub) {
    $extra = (bool) $db->value("SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users_has_payment_plans' AND COLUMN_NAME = 'trial_plan_key'");
    $preview = (bool) $db->value("SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users_has_payment_plans' AND COLUMN_NAME = 'preview_plan_key'");
    // php setup-qa-tutor.php free  -> the QA tutor on the FREE plan (upgrade tests); default STARTER, paid 30 days
    // php setup-qa-tutor.php grow  -> on GROW (staff & roles tests)
    $free = ($argv[1] ?? '') === 'free' && (bool) $db->value("SELECT 1 FROM payment_plans WHERE plan_key = 'free'");
    $key = $free ? 'free' : (($argv[1] ?? '') === 'grow' ? 'grow' : 'start');
    $db->execute(
        "UPDATE users_has_payment_plans SET payment_plans_payment_plan_id = (SELECT payment_plan_id FROM payment_plans WHERE plan_key = :k), status = 'active', status_reason = NULL,
                period_start = CURDATE(), period_end = " . ($free ? 'NULL' : 'DATE_ADD(CURDATE(), INTERVAL 30 DAY)') . ", trial_ends_at = NULL, grace_until = NULL, cancel_at_period_end = 0, next_plan_id = NULL"
            . ($extra ? ', trial_plan_key = NULL, trial_student_cap = NULL' : '') . ($preview ? ', preview_plan_key = NULL, preview_until = NULL, preview_used_at = NULL' : '') . ' WHERE users_has_payment_plan_id = :s',
        ['s' => $sub, 'k' => $key],
    );
    $db->execute("UPDATE platform_invoices SET status = 'void', void_reason = 'QA reset' WHERE users_has_payment_plan_id = :s AND status IN ('open', 'part')", ['s' => $sub]);
    if ($db->value("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tutor_trial_requests'")) {
        $db->execute('DELETE FROM tutor_trial_requests WHERE tutor_id = :u', ['u' => $id]); // trial requests start over (migration 020)
    }
}
$status = $app->platformBilling()->accessStatus($id);

echo json_encode(['id' => $id, 'email' => QA_EMAIL, 'password' => QA_PASSWORD, 'mobile' => QA_MOBILE, 'plan_status' => $status], JSON_PRETTY_PRINT), "\n";

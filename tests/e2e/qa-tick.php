<?php
// Test helper: php tests/e2e/qa-tick.php end-trial  -> the QA tutor's trial ended yesterday, then the daily job runs
//              php tests/e2e/qa-tick.php status     -> plan key and status of the QA tutor
declare(strict_types=1);

$config = require dirname(__DIR__, 2) . '/src/bootstrap.php';
$app = new Enlixo\App($config);
$db = $app->db();
$id = (int) $db->value("SELECT user_id FROM users WHERE user_email = 'qa.tutor@enlixo.test'");
if (($argv[1] ?? '') === 'end-trial') {
    $db->execute("UPDATE users_has_payment_plans SET trial_ends_at = DATE_SUB(CURDATE(), INTERVAL 1 DAY) WHERE users_user_id = :t ORDER BY users_has_payment_plan_id DESC LIMIT 1", ['t' => $id]);
    $app->platformBilling()->tickTutor($id);
}
$row = $db->one('SELECT pp.plan_key, s.status, s.trial_ends_at, s.preview_plan_key FROM users_has_payment_plans s JOIN payment_plans pp ON pp.payment_plan_id = s.payment_plans_payment_plan_id
                  WHERE s.users_user_id = :t ORDER BY s.users_has_payment_plan_id DESC LIMIT 1', ['t' => $id]);
$open = (int) $db->value("SELECT COUNT(*) FROM platform_invoices WHERE users_user_id = :t AND status IN ('open', 'part')", ['t' => $id]);
echo json_encode($row + ['open_invoices' => $open]), "\n";

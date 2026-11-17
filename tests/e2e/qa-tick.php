<?php
// Test helper: php tests/e2e/qa-tick.php end-trial  -> the QA tutor's trial ended yesterday, then the daily job runs
//              php tests/e2e/qa-tick.php status     -> plan key and status of the QA tutor
//              php tests/e2e/qa-tick.php approve-trial -> the ENLIXO admin approves the QA tutor's waiting trial request
declare(strict_types=1);

$config = require dirname(__DIR__, 2) . '/src/bootstrap.php';
$app = new Enlixo\App($config);
$db = $app->db();
$id = (int) $db->value("SELECT user_id FROM users WHERE user_email = 'qa.tutor@enlixo.test'");
if (($argv[1] ?? '') === 'end-trial') {
    $db->execute("UPDATE users_has_payment_plans SET trial_ends_at = DATE_SUB(CURDATE(), INTERVAL 1 DAY) WHERE users_user_id = :t ORDER BY users_has_payment_plan_id DESC LIMIT 1", ['t' => $id]);
    $app->platformBilling()->tickTutor($id);
}
if (($argv[1] ?? '') === 'waiting') {
    // php qa-tick.php waiting: an invoice due now (status Payment Due) and a slip waiting for ENLIXO to check it
    $app->platformBilling()->action($id, 'pay_now', [], $id, true);
    $inv = (int) $db->value("SELECT platform_invoice_id FROM platform_invoices WHERE users_user_id = :t AND status IN ('open', 'part') ORDER BY platform_invoice_id DESC LIMIT 1", ['t' => $id]);
    $png = sys_get_temp_dir() . '/qa-slip-' . bin2hex(random_bytes(4)) . '.png';
    $img = imagecreatetruecolor(40, 20);
    imagestring($img, 2, 2, 2, (string) random_int(1000, 9999), imagecolorallocate($img, 255, 255, 255));
    imagepng($img, $png);
    $app->platformBilling()->uploadSlip($id, ['invoice' => $inv, 'date' => date('Y-m-d'), 'reference' => 'QA-' . random_int(100, 999)],
        ['name' => 'slip.png', 'tmp_name' => $png, 'size' => filesize($png), 'error' => UPLOAD_ERR_OK, 'type' => 'image/png']);
    $app->platformBilling()->tickTutor($id);
}
if (($argv[1] ?? '') === 'approve-trial') {
    $req = (int) $db->value("SELECT request_id FROM tutor_trial_requests WHERE tutor_id = :t AND status = 'pending' ORDER BY request_id DESC LIMIT 1", ['t' => $id]);
    $admin = (int) $db->value("SELECT user_id FROM users WHERE user_email = 'qa.admin@enlixo.test'") ?: $id;
    $app->trialRequests()->decide($req, true, 'QA', 7, $admin, date('Y-m-d H:i:s'));
}
$row = $db->one('SELECT pp.plan_key, s.status, s.trial_ends_at, s.preview_plan_key FROM users_has_payment_plans s JOIN payment_plans pp ON pp.payment_plan_id = s.payment_plans_payment_plan_id
                  WHERE s.users_user_id = :t ORDER BY s.users_has_payment_plan_id DESC LIMIT 1', ['t' => $id]);
$open = (int) $db->value("SELECT COUNT(*) FROM platform_invoices WHERE users_user_id = :t AND status IN ('open', 'part')", ['t' => $id]);
echo json_encode($row + ['open_invoices' => $open]), "\n";

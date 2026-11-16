<?php
declare(strict_types=1);

/**
 * Makes (or resets) the QA admin used by the admin / billing end-to-end tests, on a TEST database only:
 *   php tests/e2e/setup-qa-admin.php     -> {"email": ..., "password": ...}
 */
use Enlixo\App;

const QA_ADMIN_EMAIL = 'qa.admin@enlixo.test';
const QA_ADMIN_PASSWORD = 'QaAdmin#2026';

$config = require dirname(__DIR__, 2) . '/src/bootstrap.php';
$db = (new App($config))->db();
$id = (int) ($db->value('SELECT user_id FROM users WHERE user_email = :e', ['e' => QA_ADMIN_EMAIL]) ?? 0);
if ($id === 0) {
    $id = $db->insert(
        "INSERT INTO users (title_title_id, user_fname, user_lname, user_email, user_mobile, user_whatsapp, user_reg_date, status_status_id, user_roles_user_role_id, gender_gender_id, added_by)
         VALUES ((SELECT title_id FROM title ORDER BY title_id LIMIT 1), 'Qa', 'Admin', :e, '0719990009', '0719990009', NOW(), 1, 1, 1, (SELECT x.user_id FROM (SELECT user_id FROM users WHERE user_roles_user_role_id = 1 ORDER BY user_id LIMIT 1) x))",
        ['e' => QA_ADMIN_EMAIL],
    );
}
$db->execute('UPDATE users SET user_password = :p, status_status_id = 1, user_roles_user_role_id = 1 WHERE user_id = :u', ['p' => password_hash(QA_ADMIN_PASSWORD, PASSWORD_DEFAULT), 'u' => $id]);
echo json_encode(['id' => $id, 'email' => QA_ADMIN_EMAIL, 'password' => QA_ADMIN_PASSWORD]), "\n";

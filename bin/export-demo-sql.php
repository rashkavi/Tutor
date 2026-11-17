<?php
declare(strict_types=1);

/**
 * Writes the demo data (made by bin/seed-demo.php) as ONE SQL file that can be imported with phpMyAdmin or
 *   mysql -u USER -p DATABASE < database/demo/enlixo-demo-data.sql
 * on a server where PHP cannot be run from the command line.
 *
 * The file never writes an id of its own: every row is inserted without its id and LAST_INSERT_ID() is kept in a
 * variable, so the rows link to each other and never clash with the ids already in the target database. Lookup rows
 * (admin user, plans, subjects, cities, permissions) are found by their name / key in the target database.
 * database/demo/remove-demo-data.sql removes it again.
 *
 *   php bin/seed-demo.php reset && php bin/export-demo-sql.php
 */

use Enlixo\App;

$config = require dirname(__DIR__) . '/src/bootstrap.php';
$app = new App($config);
$db = $app->db();
$root = dirname(__DIR__);
$outDir = $root . '/database/demo';
if (!is_dir($outDir)) {
    mkdir($outDir, 0775, true);
}

$q = static function ($v): string {
    if ($v === null) {
        return 'NULL';
    }
    if (is_int($v) || is_float($v)) {
        return (string) $v;
    }
    return "'" . str_replace(["\\", "'", "\0", "\n", "\r", "\x1a"], ["\\\\", "\\'", "\\0", "\\n", "\\r", "\\Z"], (string) $v) . "'";
};
$ids = static fn (array $rows, string $col) => array_values(array_unique(array_map(static fn ($r) => (int) $r[$col], $rows)));
$in = static fn (array $list) => $list ? implode(',', array_map('intval', $list)) : '0';

$demoUsers = $db->all("SELECT * FROM users WHERE user_email LIKE '%@demo.enlixo.lk' ORDER BY user_roles_user_role_id, user_id");
if (!$demoUsers) {
    fwrite(STDERR, "No demo data in this database. Run php bin/seed-demo.php first.\n");
    exit(1);
}
$userIds = $ids($demoUsers, 'user_id');
$tutorIds = array_values(array_map(static fn ($r) => (int) $r['user_id'], array_filter($demoUsers, static fn ($r) => (int) $r['user_roles_user_role_id'] === 2)));
$U = $in($userIds);
$T = $in($tutorIds);

// ---------------------------------------------------------------- the rows, parents before children
$sets = [];
$sets['users'] = $demoUsers;
$sets['users_has_payment_plans'] = $db->all("SELECT * FROM users_has_payment_plans WHERE users_user_id IN ($T) ORDER BY 1");
$S = $in($ids($sets['users_has_payment_plans'], 'users_has_payment_plan_id'));
$sets['platform_invoices'] = $db->all("SELECT * FROM platform_invoices WHERE users_user_id IN ($T) ORDER BY 1");
$I = $in($ids($sets['platform_invoices'], 'platform_invoice_id'));
$sets['platform_invoice_lines'] = $db->all("SELECT * FROM platform_invoice_lines WHERE platform_invoice_id IN ($I) ORDER BY 1");
$sets['users_has_payment_plans_payments'] = $db->all("SELECT * FROM users_has_payment_plans_payments WHERE users_has_payment_plans_users_has_payment_plan_id IN ($S) ORDER BY 1");
$sets['platform_billing_logs'] = $db->all("SELECT * FROM platform_billing_logs WHERE users_user_id IN ($T) ORDER BY 1");
$sets['users_has_payment_plans_logs'] = $db->all("SELECT * FROM users_has_payment_plans_logs WHERE users_user_id IN ($T) ORDER BY 1");
$sets['tutor_roles'] = $db->all("SELECT * FROM tutor_roles WHERE tutor_id IN ($T) ORDER BY 1");
$R = $in($ids($sets['tutor_roles'], 'tutor_role_id'));
$sets['tutor_role_permissions'] = $db->all("SELECT trp.*, p.permission_key FROM tutor_role_permissions trp JOIN permissions p ON p.permission_id = trp.permissions_permission_id WHERE trp.tutor_roles_tutor_role_id IN ($R)");
$sets['tutor_staff'] = $db->all("SELECT * FROM tutor_staff WHERE tutor_id IN ($T) ORDER BY 1");
$sets['classes'] = $db->all("SELECT c.*, s.subject AS _subject FROM classes c LEFT JOIN subjects s ON s.subject_id = c.subjects_subject_id WHERE c.tutor_id IN ($T) ORDER BY 1");
$C = $in($ids($sets['classes'], 'class_id'));
$sets['class_payment_plans'] = $db->all("SELECT * FROM class_payment_plans WHERE classes_class_id IN ($C) ORDER BY 1");
$sets['class_schedules'] = $db->all("SELECT * FROM class_schedules WHERE classes_class_id IN ($C) ORDER BY 1");
$sets['time_table'] = $db->all("SELECT * FROM time_table WHERE classes_class_id IN ($C) ORDER BY 1");
$TT = $in($ids($sets['time_table'], 'time_table_id'));
$sets['lecture_recording'] = $db->all("SELECT * FROM lecture_recording WHERE time_table_time_table_id IN ($TT) ORDER BY 1");
$sets['pre_recordings'] = $db->all("SELECT * FROM pre_recordings WHERE classes_class_id IN ($C) ORDER BY 1");
$sets['login_details'] = $db->all("SELECT ld.* FROM login_details ld JOIN students s ON s.login_details_login_details_id = ld.login_details_id WHERE s.users_user_id IN ($T) ORDER BY 1");
$L = $in($ids($sets['login_details'], 'login_details_id'));
$sets['students'] = $db->all("SELECT * FROM students WHERE login_details_login_details_id IN ($L) ORDER BY 1");
$ST = $in($ids($sets['students'], 'student_id'));
$sets['student_has_address'] = $db->all("SELECT a.*, c.city AS _city FROM student_has_address a LEFT JOIN city c ON c.city_id = a.city_city_id WHERE a.students_student_id IN ($ST) ORDER BY 1");
$sets['parents'] = $db->all("SELECT p.* FROM parents p WHERE p.parent_id IN (SELECT parents_parent_id FROM parents_has_students WHERE students_student_id IN ($ST)) ORDER BY 1");
$sets['parents_has_students'] = $db->all("SELECT * FROM parents_has_students WHERE students_student_id IN ($ST) ORDER BY 1");
$sets['students_has_classes'] = $db->all("SELECT * FROM students_has_classes WHERE classes_class_id IN ($C) ORDER BY 1");
$E = $in($ids($sets['students_has_classes'], 'students_has_class_id'));
$sets['student_has_payments'] = $db->all("SELECT * FROM student_has_payments WHERE students_has_class_students_has_class_id IN ($E) ORDER BY 1");
$P = $in($ids($sets['student_has_payments'], 'student_has_payment_id'));
$sets['student_has_payment_logs'] = $db->all("SELECT * FROM student_has_payment_logs WHERE student_has_payments_student_has_payment_id IN ($P) ORDER BY 1");
$sets['attendance'] = $db->all("SELECT * FROM attendance WHERE time_table_time_table_id IN ($TT) ORDER BY 1");
$sets['class_tutes'] = $db->all("SELECT * FROM class_tutes WHERE classes_class_id IN ($C) ORDER BY 1");
$CT = $in($ids($sets['class_tutes'], 'class_tute_id'));
$sets['class_tutes_has_attachments'] = $db->all("SELECT * FROM class_tutes_has_attachments WHERE class_tutes_class_tute_id IN ($CT) ORDER BY 1");
$sets['tute_deliveries'] = $db->all("SELECT * FROM tute_deliveries WHERE class_tutes_class_tute_id IN ($CT) ORDER BY 1");

// ---------------------------------------------------------------- how each table links: pk, [column => demo table]
// 'user|admin' = a demo user, else the first admin of the target database
$schema = [
    'users' => ['user_id', ['added_by' => 'users|admin']],
    'users_has_payment_plans' => ['users_has_payment_plan_id', ['users_user_id' => 'users', 'added_by' => 'users|admin', 'payment_plans_payment_plan_id' => 'plan', 'next_plan_id' => 'plan']],
    'platform_invoices' => ['platform_invoice_id', ['users_user_id' => 'users', 'users_has_payment_plan_id' => 'users_has_payment_plans', 'created_by' => 'users|admin']],
    'platform_invoice_lines' => ['platform_invoice_line_id', ['platform_invoice_id' => 'platform_invoices']],
    'users_has_payment_plans_payments' => ['users_has_payment_plans_payment_id', ['users_has_payment_plans_users_has_payment_plan_id' => 'users_has_payment_plans', 'platform_invoice_id' => 'platform_invoices',
        'recorded_by' => 'users|admin', 'checked_by' => 'users|admin']],
    'platform_billing_logs' => ['platform_billing_log_id', ['users_user_id' => 'users', 'done_by' => 'users|admin']],
    'users_has_payment_plans_logs' => ['users_has_payment_plans_log_id', ['users_user_id' => 'users']],
    'tutor_roles' => ['tutor_role_id', ['tutor_id' => 'users']],
    'tutor_role_permissions' => [null, ['tutor_roles_tutor_role_id' => 'tutor_roles', 'permissions_permission_id' => 'permission']],
    'tutor_staff' => ['tutor_staff_id', ['tutor_id' => 'users', 'staff_user_id' => 'users', 'tutor_roles_tutor_role_id' => 'tutor_roles']],
    'classes' => ['class_id', ['tutor_id' => 'users', 'added_by' => 'users|admin', 'subjects_subject_id' => 'subject']],
    'class_payment_plans' => ['class_payment_plan_id', ['classes_class_id' => 'classes', 'users_user_id' => 'users']],
    'class_schedules' => ['class_schedule_id', ['classes_class_id' => 'classes', 'created_by' => 'users|admin']],
    'time_table' => ['time_table_id', ['classes_class_id' => 'classes', 'users_user_id' => 'users', 'class_schedules_class_schedule_id' => 'class_schedules']],
    'lecture_recording' => ['lecture_recording_id', ['time_table_time_table_id' => 'time_table', 'users_user_id' => 'users']],
    'pre_recordings' => ['pre_recording_id', ['classes_class_id' => 'classes', 'added_by' => 'users|admin']],
    'login_details' => ['login_details_id', []],
    'students' => ['student_id', ['users_user_id' => 'users', 'login_details_login_details_id' => 'login_details']],
    'student_has_address' => ['student_has_address_id', ['students_student_id' => 'students', 'city_city_id' => 'city']],
    'parents' => ['parent_id', []],
    'parents_has_students' => ['parents_has_student_id', ['students_student_id' => 'students', 'parents_parent_id' => 'parents']],
    'students_has_classes' => ['students_has_class_id', ['students_student_id' => 'students', 'classes_class_id' => 'classes', 'added_by' => 'users|admin']],
    'student_has_payments' => ['student_has_payment_id', ['students_has_class_students_has_class_id' => 'students_has_classes', 'class_payment_plans_class_payment_plan_id' => 'class_payment_plans']],
    'student_has_payment_logs' => ['student_has_payment_log_id', ['student_has_payments_student_has_payment_id' => 'student_has_payments', 'users_user_id' => 'users|admin']],
    'attendance' => ['attendance_id', ['students_student_id' => 'students', 'time_table_time_table_id' => 'time_table', 'users_user_id' => 'users|admin']],
    'class_tutes' => ['class_tute_id', ['classes_class_id' => 'classes', 'added_by' => 'users|admin']],
    'class_tutes_has_attachments' => ['class_tutes_has_attachment_id', ['class_tutes_class_tute_id' => 'class_tutes']],
    'tute_deliveries' => ['tute_delivery_id', ['class_tutes_class_tute_id' => 'class_tutes', 'students_student_id' => 'students', 'updated_by' => 'users|admin']],
];

// lookups by name in the target database
$plans = [];
foreach ($db->all('SELECT payment_plan_id, plan_key FROM payment_plans') as $p) {
    $plans[(int) $p['payment_plan_id']] = (string) $p['plan_key'];
}
$sql = [];
$sql[] = "-- ENLIXO Tutor demo data: one tutor on FREE, STARTER, GROW and CUSTOM with classes, students, parents, fees,";
$sql[] = "-- attendance, recordings, tutes and staff. Made by bin/export-demo-sql.php on " . date('Y-m-d H:i') . ' (dates are fixed at that day).';
$sql[] = "-- Import into a TEST database that already has the ENLIXO tables, migrations and php bin/seed.php (plans, permissions).";
$sql[] = "-- Every password: Demo#2026. Sign-in sheet: docs/DEMO-DATA.md. Remove with database/demo/remove-demo-data.sql.";
$sql[] = "-- Importing twice stops at the first demo e-mail that already exists: remove it first.";
$sql[] = '';
$sql[] = 'SET NAMES utf8mb4;';
$sql[] = 'SET FOREIGN_KEY_CHECKS = 0;';
$sql[] = "SET @admin := (SELECT MIN(user_id) FROM users WHERE user_roles_user_role_id = 1);";
$sql[] = 'START TRANSACTION;';
$lookupVar = [];
$lookup = static function (string $kind, string $name) use (&$lookupVar, &$sql, $q): string {
    $key = $kind . ':' . $name;
    if (!isset($lookupVar[$key])) {
        $var = '@' . $kind . '_' . count($lookupVar);
        $lookupVar[$key] = $var;
        $sql[] = match ($kind) {
            'plan' => "SET $var := (SELECT payment_plan_id FROM payment_plans WHERE plan_key = " . $q($name) . ' LIMIT 1);',
            'subject' => "SET $var := COALESCE((SELECT subject_id FROM subjects WHERE subject = " . $q($name) . ' LIMIT 1), (SELECT MIN(subject_id) FROM subjects));',
            'city' => "SET $var := COALESCE((SELECT city_id FROM city WHERE city = " . $q($name) . ' LIMIT 1), (SELECT MIN(city_id) FROM city));',
            'permission' => "SET $var := (SELECT permission_id FROM permissions WHERE permission_key = " . $q($name) . ' LIMIT 1);',
        };
    }
    return $lookupVar[$key];
};

$vars = []; // table => [old id => @var]
$count = 0;
foreach ($schema as $table => [$pk, $refs]) {
    $rows = $sets[$table] ?? [];
    if (!$rows) {
        continue;
    }
    $sql[] = '';
    $sql[] = "-- $table (" . count($rows) . ')';
    $ti = array_search($table, array_keys($schema), true);
    foreach ($rows as $row) {
        $cols = [];
        $vals = [];
        foreach ($row as $col => $val) {
            if ($col === $pk || str_starts_with($col, '_') || $col === 'permission_key') {
                continue;
            }
            $ref = $refs[$col] ?? null;
            if ($ref !== null && $val !== null) {
                $val = (int) $val;
                if ($ref === 'plan') {
                    $v = $lookup('plan', $plans[$val] ?? 'free');
                } elseif ($ref === 'subject') {
                    $v = $lookup('subject', (string) ($row['_subject'] ?? ''));
                } elseif ($ref === 'city') {
                    $v = $lookup('city', (string) ($row['_city'] ?? ''));
                } elseif ($ref === 'permission') {
                    $v = $lookup('permission', (string) $row['permission_key']);
                } else {
                    [$target, $fallback] = array_pad(explode('|', $ref), 2, null);
                    $v = $vars[$target][$val] ?? ($fallback === 'admin' ? '@admin' : 'NULL');
                }
                $vals[] = $v;
            } else {
                if ($table === 'platform_invoices' && $col === 'invoice_number' && $val !== null) {
                    $val = 'DEMO-' . $val; // invoice numbers are unique: keep them apart from real ones
                }
                $vals[] = $q($val);
            }
            $cols[] = '`' . $col . '`';
        }
        $sql[] = "INSERT INTO `$table` (" . implode(', ', $cols) . ') VALUES (' . implode(', ', $vals) . ');';
        if ($pk !== null) {
            $var = '@r' . $ti . '_' . (int) $row[$pk];
            $vars[$table][(int) $row[$pk]] = $var;
            $sql[] = "SET $var := LAST_INSERT_ID();";
        }
        $count++;
    }
}
$sql[] = '';
$sql[] = 'COMMIT;';
$sql[] = 'SET FOREIGN_KEY_CHECKS = 1;';
file_put_contents($outDir . '/enlixo-demo-data.sql', implode("\n", $sql) . "\n");

// the removal file: same rules as php bin/seed-demo.php remove
$rm = <<<'SQL'
-- Removes the ENLIXO demo data (database/demo/enlixo-demo-data.sql or php bin/seed-demo.php). Demo people are found by
-- their e-mail (...@demo.enlixo.lk) and WhatsApp number (+9470000xxxx); nothing else is touched.
SET FOREIGN_KEY_CHECKS = 0;
CREATE TEMPORARY TABLE demo_t AS SELECT user_id id FROM users WHERE user_email LIKE '%@demo.enlixo.lk' AND user_roles_user_role_id = 2;
CREATE TEMPORARY TABLE demo_c AS SELECT class_id id FROM classes WHERE tutor_id IN (SELECT id FROM demo_t);
CREATE TEMPORARY TABLE demo_tt AS SELECT time_table_id id FROM time_table WHERE classes_class_id IN (SELECT id FROM demo_c);
CREATE TEMPORARY TABLE demo_e AS SELECT students_has_class_id id FROM students_has_classes WHERE classes_class_id IN (SELECT id FROM demo_c);
CREATE TEMPORARY TABLE demo_p AS SELECT student_has_payment_id id FROM student_has_payments WHERE students_has_class_students_has_class_id IN (SELECT id FROM demo_e);
CREATE TEMPORARY TABLE demo_ct AS SELECT class_tute_id id FROM class_tutes WHERE classes_class_id IN (SELECT id FROM demo_c);
CREATE TEMPORARY TABLE demo_s AS SELECT s.student_id id, s.login_details_login_details_id ld FROM students s JOIN login_details l ON l.login_details_id = s.login_details_login_details_id WHERE l.whatsapp LIKE '+9470000%';
CREATE TEMPORARY TABLE demo_sub AS SELECT users_has_payment_plan_id id FROM users_has_payment_plans WHERE users_user_id IN (SELECT id FROM demo_t);
CREATE TEMPORARY TABLE demo_inv AS SELECT platform_invoice_id id FROM platform_invoices WHERE users_user_id IN (SELECT id FROM demo_t);
CREATE TEMPORARY TABLE demo_r AS SELECT tutor_role_id id FROM tutor_roles WHERE tutor_id IN (SELECT id FROM demo_t);
DELETE FROM attendance WHERE time_table_time_table_id IN (SELECT id FROM demo_tt) OR students_student_id IN (SELECT id FROM demo_s);
DELETE FROM lecture_recording WHERE time_table_time_table_id IN (SELECT id FROM demo_tt);
DELETE FROM time_table WHERE time_table_id IN (SELECT id FROM demo_tt);
DELETE FROM class_schedules WHERE classes_class_id IN (SELECT id FROM demo_c);
DELETE FROM pre_recordings WHERE classes_class_id IN (SELECT id FROM demo_c);
DELETE FROM student_has_payment_logs WHERE student_has_payments_student_has_payment_id IN (SELECT id FROM demo_p);
DELETE FROM student_has_payments WHERE student_has_payment_id IN (SELECT id FROM demo_p);
DELETE FROM tute_deliveries WHERE class_tutes_class_tute_id IN (SELECT id FROM demo_ct) OR students_student_id IN (SELECT id FROM demo_s);
DELETE FROM class_tutes_has_attachments WHERE class_tutes_class_tute_id IN (SELECT id FROM demo_ct);
DELETE FROM class_tutes WHERE class_tute_id IN (SELECT id FROM demo_ct);
DELETE FROM students_has_classes WHERE students_has_class_id IN (SELECT id FROM demo_e);
DELETE FROM class_payment_plans WHERE classes_class_id IN (SELECT id FROM demo_c);
DELETE FROM classes WHERE class_id IN (SELECT id FROM demo_c);
DELETE FROM parents_has_students WHERE students_student_id IN (SELECT id FROM demo_s);
DELETE FROM student_has_address WHERE students_student_id IN (SELECT id FROM demo_s);
DELETE FROM students WHERE student_id IN (SELECT id FROM demo_s);
DELETE FROM login_details WHERE login_details_id IN (SELECT ld FROM demo_s);
DELETE FROM parents WHERE whatsapp LIKE '+9470000%';
DELETE FROM tutor_staff WHERE tutor_id IN (SELECT id FROM demo_t);
DELETE FROM tutor_role_permissions WHERE tutor_roles_tutor_role_id IN (SELECT id FROM demo_r);
DELETE FROM tutor_roles WHERE tutor_role_id IN (SELECT id FROM demo_r);
DELETE FROM users_has_payment_plans_payments WHERE users_has_payment_plans_users_has_payment_plan_id IN (SELECT id FROM demo_sub);
DELETE FROM platform_invoice_lines WHERE platform_invoice_id IN (SELECT id FROM demo_inv);
DELETE FROM platform_invoices WHERE platform_invoice_id IN (SELECT id FROM demo_inv);
DELETE FROM platform_billing_logs WHERE users_user_id IN (SELECT id FROM demo_t);
DELETE FROM users_has_payment_plans_logs WHERE users_user_id IN (SELECT id FROM demo_t);
DELETE FROM users_has_payment_plans WHERE users_has_payment_plan_id IN (SELECT id FROM demo_sub);
DELETE FROM users_devices WHERE users_user_id IN (SELECT user_id FROM users WHERE user_email LIKE '%@demo.enlixo.lk');
DELETE FROM users WHERE user_email LIKE '%@demo.enlixo.lk';
SET FOREIGN_KEY_CHECKS = 1;
SQL;
file_put_contents($outDir . '/remove-demo-data.sql', $rm . "\n");

// the tute files the demo tutes point to (upload them to assets/uploads/tutes-attachments/)
$filesDir = $outDir . '/tutes-attachments';
if (!is_dir($filesDir)) {
    mkdir($filesDir, 0775, true);
}
foreach (glob($filesDir . '/*.pdf') ?: [] as $old) {
    unlink($old);
}
$copied = 0;
foreach ($sets['class_tutes_has_attachments'] as $a) {
    $src = $root . '/assets/uploads/tutes-attachments/' . $a['attachment'];
    if (is_file($src) && copy($src, $filesDir . '/' . $a['attachment'])) {
        $copied++;
    }
}
echo sprintf("Wrote database/demo/enlixo-demo-data.sql (%d rows), remove-demo-data.sql and %d tute files in database/demo/tutes-attachments/.\n", $count, $copied);

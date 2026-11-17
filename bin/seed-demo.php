<?php
declare(strict_types=1);

/**
 * Demo database: one tutor on every plan (FREE, STARTER, GROW, CUSTOM), each with classes, weekly class days,
 * students, parents, monthly fees (paid, waiting, unpaid, overdue), attendance, class recordings, tutes (download and,
 * on paid plans, by post with parcels), pre-recorded classes (GROW / CUSTOM) and a staff member (GROW / CUSTOM).
 * Everything is made for "today", so the dashboards, calendars and reports have something to show.
 *
 *   php bin/seed-demo.php          make the demo data (skips a demo tutor that already exists)
 *   php bin/seed-demo.php reset    remove every demo row, then make it again
 *   php bin/seed-demo.php remove   remove every demo row
 *
 * Demo people are found by their e-mail (…@demo.enlixo.lk) and WhatsApp number (+9470000xxxx), never by id.
 * Sign-in details are printed at the end and written to docs/DEMO-DATA.md.
 * Students and parents sign in with a WhatsApp code: on a server without WhatsApp set ENLIXO_DEMO_OTP=1 and the demo
 * numbers take the code 123456 (includes/WhatsAppOtp.php). Never run this on the live database.
 */

use Enlixo\App;

const DEMO_PASSWORD = 'Demo#2026';
const DEMO_DOMAIN = 'demo.enlixo.lk';

$config = require dirname(__DIR__) . '/src/bootstrap.php';
$app = new App($config);
$db = $app->db();
$mode = $argv[1] ?? 'make';
$root = dirname(__DIR__);

// ------------------------------------------------------------------ the four demo tutors
$tutors = [
    1 => ['plan' => 'free', 'name' => 'FREE', 'first' => 'Kasun', 'last' => 'Perera', 'brand' => 'Kasun Maths Classes', 'subject' => 'Mathematics',
        'classes' => 3, 'students' => 18, 'code' => 'KM'],
    2 => ['plan' => 'start', 'name' => 'STARTER', 'first' => 'Nimali', 'last' => 'Fernando', 'brand' => 'Nimali Science Academy', 'subject' => 'Science',
        'classes' => 4, 'students' => 36, 'code' => 'NS'],
    3 => ['plan' => 'grow', 'name' => 'GROW', 'first' => 'Sahan', 'last' => 'Jayasinghe', 'brand' => 'Sahan Physics Institute', 'subject' => 'Physics',
        'classes' => 5, 'students' => 60, 'code' => 'SP'],
    4 => ['plan' => 'pro', 'name' => 'CUSTOM', 'first' => 'Dilani', 'last' => 'Wickramasinghe', 'brand' => 'Dilani English Centre', 'subject' => 'English',
        'classes' => 6, 'students' => 80, 'code' => 'DE'],
];
$email = static fn (int $i, string $plan) => 'demo.' . ($plan === 'start' ? 'starter' : ($plan === 'pro' ? 'custom' : $plan)) . '@' . DEMO_DOMAIN;

// ------------------------------------------------------------------ remove
function demo_remove($db): void
{
    $tutorIds = array_map('intval', array_column($db->all("SELECT user_id FROM users WHERE user_email LIKE :e AND user_roles_user_role_id = 2", ['e' => '%@' . DEMO_DOMAIN]), 'user_id'));
    $db->execute('SET FOREIGN_KEY_CHECKS = 0');
    try {
        if ($tutorIds) {
            $t = implode(',', $tutorIds);
            $classIds = array_map('intval', array_column($db->all("SELECT class_id FROM classes WHERE tutor_id IN ($t)"), 'class_id')) ?: [0];
            $c = implode(',', $classIds);
            $ttIds = implode(',', array_map('intval', array_column($db->all("SELECT time_table_id FROM time_table WHERE classes_class_id IN ($c)"), 'time_table_id')) ?: [0]);
            $enr = implode(',', array_map('intval', array_column($db->all("SELECT students_has_class_id FROM students_has_classes WHERE classes_class_id IN ($c)"), 'students_has_class_id')) ?: [0]);
            $pay = implode(',', array_map('intval', array_column($db->all("SELECT student_has_payment_id FROM student_has_payments WHERE students_has_class_students_has_class_id IN ($enr)"), 'student_has_payment_id')) ?: [0]);
            $tutes = implode(',', array_map('intval', array_column($db->all("SELECT class_tute_id FROM class_tutes WHERE classes_class_id IN ($c)"), 'class_tute_id')) ?: [0]);
            $subs = implode(',', array_map('intval', array_column($db->all("SELECT users_has_payment_plan_id FROM users_has_payment_plans WHERE users_user_id IN ($t)"), 'users_has_payment_plan_id')) ?: [0]);
            $roles = implode(',', array_map('intval', array_column($db->all("SELECT tutor_role_id FROM tutor_roles WHERE tutor_id IN ($t)"), 'tutor_role_id')) ?: [0]);
            foreach ([
                "DELETE FROM attendance WHERE time_table_time_table_id IN ($ttIds)",
                "DELETE FROM lecture_recording WHERE time_table_time_table_id IN ($ttIds)",
                "DELETE FROM recording_watch_sessions WHERE recording_kind = 'lecture' AND recording_id IN (SELECT lecture_recording_id FROM lecture_recording WHERE time_table_time_table_id IN ($ttIds))",
                "DELETE FROM time_table WHERE time_table_id IN ($ttIds)",
                "DELETE FROM class_schedules WHERE classes_class_id IN ($c)",
                "DELETE FROM pre_recordings WHERE classes_class_id IN ($c)",
                "DELETE FROM student_has_payment_logs WHERE student_has_payments_student_has_payment_id IN ($pay)",
                "DELETE FROM student_has_payments_slips WHERE student_has_payments_student_has_payment_id IN ($pay)",
                "DELETE FROM student_has_payments_penalties WHERE student_has_payments_student_has_payment_id IN ($pay)",
                "DELETE FROM student_has_payments WHERE student_has_payment_id IN ($pay)",
                "DELETE FROM tute_deliveries WHERE class_tutes_class_tute_id IN ($tutes)",
                "DELETE FROM class_tutes_has_attachments WHERE class_tutes_class_tute_id IN ($tutes)",
                "DELETE FROM class_tutes WHERE class_tute_id IN ($tutes)",
                "DELETE FROM students_has_classes WHERE students_has_class_id IN ($enr)",
                "DELETE FROM class_payment_plans WHERE classes_class_id IN ($c)",
                "DELETE FROM classes WHERE class_id IN ($c)",
                "DELETE FROM tutor_staff WHERE tutor_id IN ($t)",
                "DELETE FROM tutor_role_permissions WHERE tutor_roles_tutor_role_id IN ($roles)",
                "DELETE FROM tutor_roles WHERE tutor_role_id IN ($roles)",
                "DELETE FROM tutor_fee_settings WHERE tutor_id IN ($t)",
                "DELETE FROM users_has_payment_plans_payments WHERE platform_invoice_id IN (SELECT platform_invoice_id FROM platform_invoices WHERE users_user_id IN ($t))",
                "DELETE FROM platform_invoice_lines WHERE platform_invoice_id IN (SELECT platform_invoice_id FROM platform_invoices WHERE users_user_id IN ($t))",
                "DELETE FROM platform_invoices WHERE users_user_id IN ($t)",
                "DELETE FROM platform_billing_logs WHERE users_user_id IN ($t)",
                "DELETE FROM users_has_payment_plans_logs WHERE users_user_id IN ($t)",
                "DELETE FROM users_has_payment_plans WHERE users_has_payment_plan_id IN ($subs)",
            ] as $sql) {
                try {
                    $db->execute($sql);
                } catch (\Throwable $e) {
                    // a table of an optional migration that this database does not have
                }
            }
        }
        // students, parents and staff of the demo (by their numbers and e-mails)
        $students = implode(',', array_map('intval', array_column($db->all(
            "SELECT s.student_id FROM students s JOIN login_details ld ON ld.login_details_id = s.login_details_login_details_id WHERE ld.whatsapp LIKE '+9470000%' OR ld.email LIKE :e",
            ['e' => '%@' . DEMO_DOMAIN],
        ), 'student_id')) ?: [0]);
        foreach ([
            "DELETE FROM attendance WHERE students_student_id IN ($students)",
            "DELETE FROM tute_deliveries WHERE students_student_id IN ($students)",
            "DELETE FROM parents_has_students WHERE students_student_id IN ($students)",
            "DELETE FROM student_has_address WHERE students_student_id IN ($students)",
            "DELETE ld FROM login_details ld JOIN students s ON s.login_details_login_details_id = ld.login_details_id WHERE s.student_id IN ($students)",
            "DELETE FROM students WHERE student_id IN ($students)",
            "DELETE FROM parents WHERE whatsapp LIKE '+9470000%'",
            "DELETE FROM users_devices WHERE users_user_id IN (SELECT user_id FROM users WHERE user_email LIKE '%@" . DEMO_DOMAIN . "')",
            "DELETE FROM users WHERE user_email LIKE '%@" . DEMO_DOMAIN . "'",
        ] as $sql) {
            try {
                $db->execute($sql);
            } catch (\Throwable $e) {
            }
        }
    } finally {
        $db->execute('SET FOREIGN_KEY_CHECKS = 1');
    }
    foreach (glob(dirname(__DIR__) . '/assets/uploads/tutes-attachments/demo-*.pdf') ?: [] as $f) {
        @unlink($f); // the demo tute files
    }
    echo "Demo data removed.\n";
}

if ($mode === 'remove' || $mode === 'reset') {
    demo_remove($db);
    if ($mode === 'remove') {
        exit(0);
    }
}

// ------------------------------------------------------------------ helpers
$admin = (int) ($db->value('SELECT user_id FROM users WHERE user_roles_user_role_id = 1 ORDER BY user_id LIMIT 1') ?? 0);
if ($admin === 0) {
    fwrite(STDERR, "No admin user in this database (users.user_roles_user_role_id = 1).\n");
    exit(1);
}
$hasPassword = (bool) $db->value("SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'login_details' AND COLUMN_NAME = 'password'");
$now = new DateTimeImmutable('now', new DateTimeZone('Asia/Colombo'));
$today = $now->format('Y-m-d');
$ts = $now->format('Y-m-d H:i:s');
mt_srand(2026); // the same demo every time
$pick = static fn (array $a) => $a[mt_rand(0, count($a) - 1)];
$chance = static fn (int $pct) => mt_rand(1, 100) <= $pct;

$FIRST_M = ['Kavindu', 'Dulaj', 'Yasiru', 'Hasitha', 'Pasindu', 'Ravindu', 'Chamod', 'Oshada', 'Lakshan', 'Vihanga', 'Kalana', 'Sandun', 'Tharindu', 'Isuru', 'Nuwan', 'Chathura'];
$FIRST_F = ['Nethmi', 'Tharushi', 'Imasha', 'Sachini', 'Dinithi', 'Methmi', 'Senuri', 'Hiruni', 'Nadeesha', 'Ishini', 'Anjali', 'Piumi', 'Sewwandi', 'Dilhani', 'Kaveesha', 'Rashmi'];
$LAST = ['Perera', 'Silva', 'Fernando', 'Jayasinghe', 'Bandara', 'Gunawardena', 'Rathnayake', 'Herath', 'Dissanayake', 'Wickramasinghe', 'Kumara', 'Weerasinghe', 'Senanayake', 'Abeysekara'];
$CITIES = [['Maharagama', 'Colombo', '10280'], ['Nugegoda', 'Colombo', '10250'], ['Kandy', 'Kandy', '20000'], ['Gampaha', 'Gampaha', '11000'], ['Kurunegala', 'Kurunegala', '60000'], ['Galle', 'Galle', '80000']];
$CLASS_PLANS = [
    // [name, subject id, exam type id, year id, medium id, fee, weekday, start, end, lecture type (1 physical, 2 online)]
    ['2027 A/L {s} Theory', 3, 3, 2, 1, 3000, 6, '08:00', '11:00', 1],
    ['2026 A/L {s} Revision', 3, 3, 1, 1, 2500, 7, '14:00', '17:00', 2],
    ['2028 A/L {s} Theory', 3, 3, 3, 1, 2800, 3, '16:00', '18:00', 2],
    ['2027 O/L {s}', 4, 2, 2, 1, 2000, 2, '15:30', '17:30', 1],
    ['2026 A/L {s} Paper Class', 3, 3, 1, 2, 1500, 5, '19:00', '21:00', 2],
    ['Grade 5 Scholarship {s}', 1, 1, 1, 1, 1800, 6, '13:00', '15:00', 1],
];
$TOPICS = ['Introduction and syllabus', 'Basics revisited', 'Problem solving', 'Past paper discussion', 'Theory deep dive', 'Model paper', 'Revision', 'Unit test discussion', 'Exam techniques', 'Group work', 'Q&A session', 'Final revision'];
$YT = ['https://www.youtube.com/watch?v=aircAruvnKk', 'https://www.youtube.com/watch?v=WUvTyaaNkzM', 'https://www.youtube.com/watch?v=fNk_zzaMoSs', 'https://www.youtube.com/watch?v=kYB8IZa5AuE'];

// a small real PDF for the tute files
$pdfDir = $root . '/assets/uploads/tutes-attachments';
if (!is_dir($pdfDir)) {
    @mkdir($pdfDir, 0775, true);
}
$makePdf = static function (string $file, string $title) use ($pdfDir): void {
    $text = str_replace(['(', ')', '\\'], ['\\(', '\\)', '\\\\'], $title);
    $stream = "BT /F1 22 Tf 72 720 Td ($text) Tj ET\nBT /F1 12 Tf 72 690 Td (ENLIXO Tutor demo tute. Sample content only.) Tj ET";
    $objs = ['<< /Type /Catalog /Pages 2 0 R >>', '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
        '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Contents 4 0 R /Resources << /Font << /F1 5 0 R >> >> >>',
        '<< /Length ' . strlen($stream) . " >>\nstream\n$stream\nendstream", '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>'];
    $pdf = "%PDF-1.4\n";
    $off = [];
    foreach ($objs as $i => $o) {
        $off[] = strlen($pdf);
        $pdf .= ($i + 1) . " 0 obj\n$o\nendobj\n";
    }
    $xref = strlen($pdf);
    $pdf .= "xref\n0 " . (count($objs) + 1) . "\n0000000000 65535 f \n";
    foreach ($off as $o) {
        $pdf .= sprintf("%010d 00000 n \n", $o);
    }
    $pdf .= "trailer\n<< /Size " . (count($objs) + 1) . " /Root 1 0 R >>\nstartxref\n$xref\n%%EOF";
    file_put_contents($pdfDir . '/' . $file, $pdf);
};

$report = [];
foreach ($tutors as $ti => $T) {
    $tEmail = $email($ti, $T['plan']);
    if ($db->value('SELECT 1 FROM users WHERE user_email = :e', ['e' => $tEmail])) {
        echo "{$T['name']}: demo tutor already there (run with reset to make it again).\n";
        $report[$ti] = null;
        continue;
    }
    // ---------------------------------------------------------------- the tutor and the subscription
    $in = ['title' => (int) $db->value('SELECT title_id FROM title ORDER BY title_id LIMIT 1'), 'first_name' => $T['first'], 'last_name' => $T['last'],
        'email' => $tEmail, 'mobile' => sprintf('070000%d900', $ti), 'brand' => $T['brand'], 'subject' => $T['subject'], 'note' => 'Demo tutor (bin/seed-demo.php)',
        'plan' => $T['plan'], 'cycle' => 'monthly', 'start' => 'paid', 'start_date' => $today, 'paid_now' => 'bank', 'reference' => 'DEMO-' . $T['name']];
    if ($T['plan'] === 'pro') {
        $in['custom_price'] = '15000';
        $in['custom_price_reason'] = 'Demo: a large institute on an agreed price';
    }
    $tutorId = $app->platformBilling()->addTutor($in, $admin);
    $db->execute('UPDATE users SET user_password = :p, status_status_id = 1 WHERE user_id = :u', ['p' => password_hash(DEMO_PASSWORD, PASSWORD_DEFAULT), 'u' => $tutorId]);
    $app->entitlements()->forget($tutorId);

    // ---------------------------------------------------------------- classes, weekly slots, class days
    $classes = [];
    for ($k = 0; $k < $T['classes']; $k++) {
        [$pattern, $subjectId, $examId, $yearId, $mediumId, $fee, $weekday, $start, $end, $lt] = $CLASS_PLANS[$k];
        $name = str_replace('{s}', $T['subject'], $pattern);
        // the subject chip shows the tutor's subject when this database has it (subjects table), else the plan's default
        $subjectId = (int) ($db->value('SELECT subject_id FROM subjects WHERE subject = :s LIMIT 1', ['s' => $T['subject']]) ?? 0) ?: $subjectId;
        $cid = $db->insert('INSERT INTO classes (class_name, medium_medium_id, year_year_id, exam_type_exam_type_id, subjects_subject_id, class_cover, tutor_id, status_status_id, added_by, added_at, is_free)
            VALUES (:n, :m, :y, :e, :s, \'\', :t, 1, :t2, :at, 0)',
            ['n' => $name, 'm' => $mediumId, 'y' => $yearId, 'e' => $examId, 's' => $subjectId, 't' => $tutorId, 't2' => $tutorId, 'at' => $now->modify('-150 days')->format('Y-m-d H:i:s')]);
        $planId = $db->insert('INSERT INTO class_payment_plans (classes_class_id, amount, users_user_id, date_added, due_date, discount_type_discount_type_id, discount, discount_note, panelty_payment_option_panelty_payment_option_id)
            VALUES (:c, :a, :t, :at, 10, 1, 0, \'\', 1)', ['c' => $cid, 'a' => $fee, 't' => $tutorId, 'at' => $now->modify('-150 days')->format('Y-m-d H:i:s')]);
        $slot = $db->insert('INSERT INTO class_schedules (classes_class_id, weekday, start_time, end_time, lecture_type_lecture_type_id, auto_create_zoom, valid_from, status_status_id, created_by)
            VALUES (:c, :w, :s, :e, :lt, 0, :f, 1, :t)', ['c' => $cid, 'w' => $weekday, 's' => $start, 'e' => $end, 'lt' => $lt, 'f' => $now->modify('-120 days')->format('Y-m-d'), 't' => $tutorId]);
        $days = [];
        $first = $now->modify('-10 weeks')->modify(['', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'][$weekday] . ' this week');
        for ($w = 0; $w < 14; $w++) {
            $d = $first->modify("+$w weeks");
            $dur = (int) ((strtotime($end) - strtotime($start)) / 60);
            $status = $w === 4 && $k === 0 ? 2 : 1; // one cancelled day
            $link = $lt === 2 ? 'https://us02web.zoom.us/j/8' . str_pad((string) ($cid * 100 + $w), 9, '0', STR_PAD_LEFT) : '';
            $days[] = ['id' => $db->insert('INSERT INTO time_table (classes_class_id, date, start_time, end_time, duration, lecture_type_lecture_type_id, lecture, users_user_id, date_added, time_table_status_time_table_status_id, lecture_title, class_schedules_class_schedule_id)
                VALUES (:c, :d, :s, :e, :du, :lt, :l, :t, :at, :st, :ti, :sl)',
                ['c' => $cid, 'd' => $d->format('Y-m-d'), 's' => $start, 'e' => $end, 'du' => $dur, 'lt' => $lt, 'l' => $link, 't' => $tutorId, 'at' => $ts,
                    'st' => $status, 'ti' => 'Week ' . ($w + 1) . ': ' . $TOPICS[$w % count($TOPICS)], 'sl' => $slot]), 'date' => $d->format('Y-m-d'), 'over' => $d->format('Y-m-d') . ' ' . $end < $now->format('Y-m-d H:i'), 'status' => $status, 'dur' => $dur];
        }
        $classes[] = ['id' => $cid, 'name' => $name, 'fee' => $fee, 'plan' => $planId, 'lt' => $lt, 'days' => $days, 'students' => []];
    }

    // ---------------------------------------------------------------- recordings (FREE keeps under its 8 a month)
    $recMax = $T['plan'] === 'free' ? 2 : 4;
    foreach ($classes as $ci => $c) {
        $done = array_values(array_filter($c['days'], static fn ($d) => $d['over'] && $d['status'] === 1));
        foreach (array_slice(array_reverse($done), 0, $recMax) as $n => $d) {
            $url = $YT[($ci + $n) % count($YT)];
            $db->insert('INSERT INTO lecture_recording (time_table_time_table_id, recording_link, duration, recording_title, recording_description, users_user_id, added_at, recording_url, expire_date)
                VALUES (:tt, :l, :du, :ti, :de, :t, :at, :u, :ex)',
                ['tt' => $d['id'], 'l' => $url, 'du' => $d['dur'], 'ti' => $c['name'] . ' - ' . date('j M', strtotime($d['date'])), 'de' => 'Recording of the class day.',
                    't' => $tutorId, 'at' => $d['date'] . ' 22:00:00', 'u' => $url, 'ex' => $T['plan'] === 'free' ? null : date('Y-m-d', strtotime($d['date'] . ' +60 days'))]);
        }
        // pre-recorded classes: GROW and CUSTOM only
        if (in_array($T['plan'], ['grow', 'pro'], true)) {
            foreach (['Unit 1 crash course', 'Unit 2 explained', 'Past paper walk-through'] as $n => $title) {
                $url = $YT[($ci + $n + 1) % count($YT)];
                $db->insert('INSERT INTO pre_recordings (title, duration, recording_link, description, classes_class_id, added_by, added_at, recording_status_recording_status_id, recording_url, unlock_at)
                    VALUES (:ti, :du, :l, :de, :c, :t, :at, 1, :u, :un)',
                    ['ti' => $title, 'du' => 45 + 15 * $n, 'l' => $url, 'de' => 'A ready-made lesson to watch any time.', 'c' => $c['id'], 't' => $tutorId,
                        'at' => $now->modify('-' . (20 - $n * 5) . ' days')->format('Y-m-d H:i:s'), 'u' => $url, 'un' => $n === 2 ? $now->modify('+5 days')->format('Y-m-d 08:00:00') : null]);
            }
        }
    }

    // ---------------------------------------------------------------- students, parents, enrolments, fees, attendance
    $paidPlan = $T['plan'] !== 'free';
    $people = [];
    for ($s = 1; $s <= $T['students']; $s++) {
        $female = $s % 2 === 0;
        $first = $pick($female ? $FIRST_F : $FIRST_M);
        $last = $pick($LAST);
        $wa = sprintf('+9470000%d%03d', $ti, $s);
        $mail = strtolower($first . '.' . $last . $s . '.' . $T['code']) . '@' . DEMO_DOMAIN;
        $ld = $db->insert('INSERT INTO login_details (first_name, last_name, whatsapp, email' . ($hasPassword ? ', password' : '') . ', date_registered, status_status_id, verification)
            VALUES (:f, :l, :w, :e' . ($hasPassword ? ', :p' : '') . ', :at, 1, \'\')',
            ['f' => $first, 'l' => $last, 'w' => $wa, 'e' => $mail, 'at' => $now->modify('-' . mt_rand(20, 140) . ' days')->format('Y-m-d H:i:s')] + ($hasPassword ? ['p' => password_hash('x', PASSWORD_BCRYPT)] : []));
        $sid = $db->insert('INSERT INTO students (full_name, name_with_initials, date_of_birth, date_added, gender_gender_id, users_user_id, login_details_login_details_id, mobile, profile_img)
            VALUES (:fn, :ni, :dob, :at, :g, :t, :ld, :m, NULL)',
            ['fn' => "$first $last", 'ni' => $first[0] . ". $last", 'dob' => (2006 + mt_rand(0, 4)) . '-' . sprintf('%02d-%02d', mt_rand(1, 12), mt_rand(1, 28)), 'at' => $now->modify('-' . mt_rand(20, 140) . ' days')->format('Y-m-d H:i:s'),
                'g' => $female ? 2 : 1, 't' => $tutorId, 'ld' => $ld, 'm' => '0' . substr($wa, 3)]);
        $city = $pick($CITIES);
        try {
            $cityId = (int) ($db->value('SELECT city_id FROM city WHERE city = :c LIMIT 1', ['c' => $city[0]]) ?? 0);
            if ($cityId) {
                $db->insert('INSERT INTO student_has_address (address_line_1, address_line_2, postal_code, students_student_id, city_city_id) VALUES (:a, :b, :p, :s, :c)',
                    ['a' => 'No. ' . mt_rand(5, 250) . ', ' . $pick(['Temple Road', 'Main Street', 'Lake Road', 'School Lane', 'Station Road']), 'b' => '', 'p' => $city[2], 's' => $sid, 'c' => $cityId]);
            }
        } catch (\Throwable $e) {
        }
        $people[] = ['id' => $sid, 'name' => "$first $last", 'wa' => $wa, 'city' => $city];
        // parents (paid plans; FREE has no parent login): about one in three
        if ($paidPlan && $s % 3 === 1) {
            $pw = sprintf('+9470000%d%03d', $ti + 4, $s);
            $pid = $db->insert('INSERT INTO parents (parent_fname, parent_lname, whatsapp, mobile, email, profile_img, guardian_relation_guardian_relation_id, title_title_id, registered_at, verification, reset_time)
                VALUES (:f, :l, :w, :m, :e, NULL, :r, :t, :at, \'\', \'\')',
                ['f' => $pick($s % 2 ? ['Sunil', 'Ajith', 'Ruwan', 'Chandana'] : ['Kumari', 'Shiromi', 'Nirosha', 'Dilrukshi']), 'l' => $last, 'w' => $pw, 'm' => '0' . substr($pw, 3),
                    'e' => 'parent.' . strtolower($last) . $s . '.' . strtolower($T['code']) . '@' . DEMO_DOMAIN, 'r' => $s % 2 ? 2 : 1,
                    't' => (int) $db->value('SELECT title_id FROM title ORDER BY title_id LIMIT 1'), 'at' => $ts]);
            $db->insert('INSERT INTO parents_has_students (students_student_id, parents_parent_id, added_at) VALUES (:s, :p, :at)', ['s' => $sid, 'p' => $pid, 'at' => $ts]);
            $people[count($people) - 1]['parent'] = $pw;
        }
    }
    // each student in one or two classes; their months and class days
    foreach ($people as $pi => $p) {
        $mine = [$pi % count($classes)];
        if ($pi % 4 === 0 && count($classes) > 1) {
            $mine[] = ($pi + 1) % count($classes);
        }
        $habit = mt_rand(1, 100); // pays on time / late / owes
        if ($pi === 0) {
            $habit = 10; // student 1 of every tutor pays on time (everything open)
        } elseif ($pi === 1) {
            $habit = 97; // student 2 owes (locked recordings / tutes, overdue fees, a rejected slip)
        }
        foreach ($mine as $ci) {
            $c = &$classes[$ci];
            $joined = $now->modify('-' . mt_rand(75, 140) . ' days');
            $enr = $db->insert('INSERT INTO students_has_classes (student_class_id, students_student_id, classes_class_id, status_status_id, added_at, added_by) VALUES (:code, :s, :c, :st, :at, :t)',
                ['code' => sprintf('%s%04d', $T['code'], $pi + 1), 's' => $p['id'], 'c' => $c['id'], 'st' => $pi === count($people) - 1 ? 2 : 1, 'at' => $joined->format('Y-m-d H:i:s'), 't' => $tutorId]);
            $c['students'][] = ['id' => $p['id'], 'enr' => $enr, 'habit' => $habit];
            // fees: the last 3 months and this month
            for ($back = 3; $back >= 0; $back--) {
                $m = $now->modify("first day of -$back month");
                $due = $m->format('Y-m-10');
                if ($back === 0) {
                    $st = $habit <= 45 ? 3 : ($habit <= 70 ? 2 : 1);
                } elseif ($back === 1) {
                    $st = $habit <= 85 ? 3 : 1;
                } else {
                    $st = $habit <= 93 ? 3 : 1;
                }
                if ($back === 1 && $habit > 95) {
                    $st = 4; // a rejected slip
                }
                $method = $st === 3 ? ($habit % 2 ? 1 : 3) : ($st === 2 || $st === 4 ? 1 : null);
                $payId = $db->insert('INSERT INTO student_has_payments (students_has_class_students_has_class_id, class_payment_plans_class_payment_plan_id, amount, discount, discount_type_discount_type_id, discount_note,
                        panelty_payment_option_panelty_payment_option_id, due_date, refference, payment_method_payment_method_id, date_added, date_paid, payment_status_payment_status_id)
                    VALUES (:e, :pl, :a, 0, 1, \'\', 1, :d, :r, :me, :at, :pd, :st)',
                    ['e' => $enr, 'pl' => $c['plan'], 'a' => $c['fee'], 'd' => $due, 'r' => $st === 1 ? '' : 'DM' . mt_rand(100000, 999999), 'me' => $method,
                        'at' => $m->format('Y-m-01 00:05:00'), 'pd' => $st === 1 ? null : date('Y-m-d H:i:s', min(time(), strtotime($due . ' ' . ($habit <= 45 ? '-3' : '+4') . ' days 18:30'))), 'st' => $st]);
                if ($st === 3) {
                    $db->execute('INSERT INTO student_has_payment_logs (student_has_payments_student_has_payment_id, users_user_id, date_updated, log, note) VALUES (:p, :u, :at, :l, NULL)',
                        ['p' => $payId, 'u' => $tutorId, 'at' => date('Y-m-d H:i:s', min(time(), strtotime($due . ' +5 days'))), 'l' => ($method === 3 ? 'Cash received' : 'Slip approved') . sprintf('. Receipt RC-%05d', $payId)]);
                } elseif ($st === 4) {
                    $db->execute('INSERT INTO student_has_payment_logs (student_has_payments_student_has_payment_id, users_user_id, date_updated, log, note) VALUES (:p, :u, :at, \'Slip rejected\', :n)',
                        ['p' => $payId, 'u' => $tutorId, 'at' => $ts, 'n' => 'The slip is not clear, please upload a clear photo']);
                }
            }
            // attendance of the class days that are over and after joining
            foreach ($c['days'] as $d) {
                if (!$d['over'] || $d['status'] !== 1 || $d['date'] < $joined->format('Y-m-d')) {
                    continue;
                }
                $mark = $chance($habit <= 70 ? 85 : 60) ? 2 : ($chance(40) ? 4 : 3);
                $db->insert('INSERT INTO attendance (students_student_id, time_table_time_table_id, attendance_status_attendance_status_id, updated_at, users_user_id) VALUES (:s, :tt, :st, :at, :u)',
                    ['s' => $p['id'], 'tt' => $d['id'], 'st' => $mark, 'at' => $d['date'] . ' 21:00:00', 'u' => $tutorId]);
            }
            unset($c);
        }
    }

    // ---------------------------------------------------------------- tutes: download for everyone, by post on paid plans
    $thisMonth = $now->format('Y-m');
    $tuteMax = $T['plan'] === 'free' ? 1 : 2;
    foreach ($classes as $ci => $c) {
        for ($n = 0; $n < $tuteMax; $n++) {
            $month = $n === 0 ? $thisMonth : $now->modify('first day of -1 month')->format('Y-m');
            $title = ($n === 0 ? 'This month\'s notes' : 'Past paper pack') . ' - ' . $c['name'];
            $tid = $db->insert('INSERT INTO class_tutes (classes_class_id, tute_title, tute_description, payment_month, tute_types_tute_type_id, date_added, added_by)
                VALUES (:c, :ti, :de, :m, 1, :at, :t)', ['c' => $c['id'], 'ti' => mb_substr($title, 0, 100), 'de' => 'Notes and practice questions for ' . date('F Y', strtotime($month . '-01')) . '.',
                    'm' => $month, 'at' => $now->modify('-' . (3 + $n * 25) . ' days')->format('Y-m-d H:i:s'), 't' => $tutorId]);
            foreach (['Notes', 'Practice questions'] as $f => $label) {
                $file = sprintf('demo-%d-%d-%d.pdf', $tid, $f, $ti);
                $makePdf($file, "$label - {$c['name']}");
                $db->insert('INSERT INTO class_tutes_has_attachments (class_tutes_class_tute_id, attachment_name, attachment, date_added) VALUES (:t, :n, :a, :at)',
                    ['t' => $tid, 'n' => "$label.pdf", 'a' => $file, 'at' => $ts]);
            }
        }
        if ($paidPlan) {
            $tid = $db->insert('INSERT INTO class_tutes (classes_class_id, tute_title, tute_description, payment_month, tute_types_tute_type_id, date_added, courier_name, dispatch_on, student_note, added_by)
                VALUES (:c, :ti, :de, :m, 2, :at, :cn, :dp, :sn, :t)',
                ['c' => $c['id'], 'ti' => mb_substr('Printed tute book - ' . $c['name'], 0, 100), 'de' => 'The printed tute book of this month, posted to your home.', 'm' => $thisMonth,
                    'at' => $now->modify('-6 days')->format('Y-m-d H:i:s'), 'cn' => 'Sri Lanka Post', 'dp' => $now->modify('-2 days')->format('Y-m-d'), 'sn' => 'Check your address in My Profile.', 't' => $tutorId]);
            foreach ($c['students'] as $k => $st) {
                $p = $people[array_search($st['id'], array_column($people, 'id'), true)];
                $step = ['delivered', 'dispatched', 'dispatched', 'packed', 'pending'][$k % 5];
                $db->insert('INSERT INTO tute_deliveries (class_tutes_class_tute_id, students_student_id, recipient_name, phone, address_line_1, city, district, postal_code, status, courier_name, tracking_no, dispatched_on, delivered_on)
                    VALUES (:t, :s, :n, :ph, :a, :c, :d, :pc, :st, :cn, :tr, :dp, :dl)',
                    ['t' => $tid, 's' => $st['id'], 'n' => $p['name'], 'ph' => '0' . substr($p['wa'], 3), 'a' => 'No. ' . mt_rand(5, 250) . ', Temple Road', 'c' => $p['city'][0], 'd' => $p['city'][1], 'pc' => $p['city'][2],
                        'st' => $step, 'cn' => in_array($step, ['dispatched', 'delivered'], true) ? 'Sri Lanka Post' : null,
                        'tr' => in_array($step, ['dispatched', 'delivered'], true) ? 'RR' . mt_rand(100000000, 999999999) . 'LK' : null,
                        'dp' => in_array($step, ['dispatched', 'delivered'], true) ? $now->modify('-2 days')->format('Y-m-d') : null,
                        'dl' => $step === 'delivered' ? $now->modify('-1 day')->format('Y-m-d') : null]);
            }
        }
    }

    // ---------------------------------------------------------------- staff (GROW / CUSTOM)
    $staffEmail = null;
    if (in_array($T['plan'], ['grow', 'pro'], true)) {
        $roleId = (new \Enlixo\Roles\PdoRoleRepository($db))->create($tutorId, 'Class Assistant', 'Marks attendance and checks payment slips',
            ['students.view', 'classes.view', 'attendance.mark', 'payments.view', 'payments.approve', 'recordings.view']);
        $staffEmail = 'assistant.' . strtolower($T['code']) . '@' . DEMO_DOMAIN;
        $uid = $db->insert('INSERT INTO users (title_title_id, user_fname, user_lname, user_email, user_mobile, user_reg_date, user_password, status_status_id, user_roles_user_role_id, added_by)
            VALUES (:ti, :f, :l, :e, NULL, NOW(), :p, 1, 3, :by)',
            ['ti' => (int) $db->value('SELECT title_id FROM title ORDER BY title_id LIMIT 1'), 'f' => $T['plan'] === 'grow' ? 'Ishara' : 'Madhavi', 'l' => 'Assistant', 'e' => $staffEmail,
                'p' => password_hash(DEMO_PASSWORD, PASSWORD_DEFAULT), 'by' => $tutorId]);
        $db->insert('INSERT INTO tutor_staff (tutor_id, staff_user_id, tutor_roles_tutor_role_id, status_status_id, added_at) VALUES (:t, :u, :r, 1, NOW())', ['t' => $tutorId, 'u' => $uid, 'r' => $roleId]);
    }

    $parent = null;
    foreach ($people as $p) {
        if (!empty($p['parent'])) {
            $parent = $p['parent'];
            break;
        }
    }
    $report[$ti] = ['plan' => $T['name'], 'tutor' => $tEmail, 'staff' => $staffEmail, 'student' => $people[0]['wa'], 'student_name' => $people[0]['name'],
        'owing' => $people[1]['wa'], 'owing_name' => $people[1]['name'], 'parent' => $parent,
        'classes' => count($classes), 'students' => count($people)];
    echo sprintf("%s: tutor %s, %d classes, %d students%s%s\n", $T['name'], $tEmail, count($classes), count($people), $staffEmail ? ", staff $staffEmail" : '', $parent ? ', parents' : '');
}

// ------------------------------------------------------------------ sign-in sheet
if (array_filter($report)) {
    $md = "# Demo data (bin/seed-demo.php)\n\nMade on " . $now->format('j F Y') . ". Every password is `" . DEMO_PASSWORD . "`. Students and parents sign in with a WhatsApp code:\n"
        . "on a server without WhatsApp set `ENLIXO_DEMO_OTP=1`, then every demo number takes the code `123456` (only numbers starting +9470000).\n\n"
        . "| Plan | Tutor (tutor/) | Staff (tutor/) | Student who paid (portal/) | Student who owes (portal/) | Parent (parent/) | Classes | Students |\n|---|---|---|---|---|---|---|---|\n";
    foreach ($report as $r) {
        if ($r) {
            $md .= sprintf("| %s | %s | %s | %s (%s) | %s (%s) | %s | %d | %d |\n", $r['plan'], $r['tutor'], $r['staff'] ?? '-', '0' . substr($r['student'], 3), $r['student_name'],
                '0' . substr($r['owing'], 3), $r['owing_name'], $r['parent'] ? '0' . substr($r['parent'], 3) : '- (FREE: no parent login)', $r['classes'], $r['students']);
        }
    }
    $md .= "\nWhat each one shows:\n\n- **FREE:** 3 classes, 18 students, recordings, download tutes; no parents, no tutes by post, no pre-recorded classes, upgrade nudges.\n"
        . "- **STARTER:** 4 classes, parents, tutes by post with parcels, upgrade nudges to GROW; no pre-recorded classes.\n"
        . "- **GROW:** 5 classes, pre-recorded classes (one opens in 5 days), a staff member (Class Assistant role), no upgrade nudges.\n"
        . "- **CUSTOM:** 6 classes on an agreed price, everything GROW has.\n\n"
        . "Every tutor has 14 weekly class days (10 done, 4 coming), attendance, fees of the last 3 months and this month (paid, slip waiting, unpaid, overdue, one rejected slip) and receipts.\n"
        . "Remove it with `php bin/seed-demo.php remove`; make it again with `php bin/seed-demo.php reset`.\n";
    file_put_contents($root . '/docs/DEMO-DATA.md', $md);
    echo "\nSign-in details written to docs/DEMO-DATA.md (password " . DEMO_PASSWORD . ").\n";
}

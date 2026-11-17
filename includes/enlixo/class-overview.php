<?php
/**
 * One class of one student, as the student (portal/class-details.php) and the parent (parent/class-details.php) see it:
 * class card (cover, subject, tutor, monthly fee, next class), stat tiles (attendance, next class, this month's fee,
 * recordings), a card for every part of the class (time table, attendance, recordings, pre-recorded, tutes, payments)
 * and two panels: the coming class days and the latest recordings / tutes. Same page frame as Time Table / Recordings.
 *   $o = co_overview($studentId, $classId);   co_render($o, $links, $opts);
 */
require_once __DIR__ . '/student-learning.php';
require_once __DIR__ . '/stat-card.php';
require_once __DIR__ . '/plan-guard.php';

if (!function_exists('co_overview')) {
    function co_overview(int $studentId, int $classId): ?array
    {
        $c = Database::search("SELECT c.class_id, c.class_name, c.class_cover, c.is_free, c.tutor_id, s.subject, y.year, m.medium, e.exam_type,
                u.user_fname, u.user_lname, shc.students_has_class_id enr, shc.student_class_id, shc.added_at,
                (SELECT p.amount FROM class_payment_plans p WHERE p.classes_class_id = c.class_id ORDER BY p.class_payment_plan_id DESC LIMIT 1) fee,
                (SELECT p.due_date FROM class_payment_plans p WHERE p.classes_class_id = c.class_id ORDER BY p.class_payment_plan_id DESC LIMIT 1) due_day
            FROM students_has_classes shc
            JOIN classes c ON c.class_id = shc.classes_class_id
            LEFT JOIN subjects s ON s.subject_id = c.subjects_subject_id
            LEFT JOIN year y ON y.year_id = c.year_year_id
            LEFT JOIN medium m ON m.medium_id = c.medium_medium_id
            LEFT JOIN exam_type e ON e.exam_type_id = c.exam_type_exam_type_id
            LEFT JOIN users u ON u.user_id = c.tutor_id
            WHERE shc.students_student_id = '" . $studentId . "' AND shc.classes_class_id = '" . $classId . "' AND shc.status_status_id = 1 LIMIT 1")->fetch_assoc();
        if (!$c) {
            return null;
        }
        $now = new DateTime('now', new DateTimeZone('Asia/Colombo'));
        $nowTs = $now->format('Y-m-d H:i:s');
        $enr = (int) $c['enr'];

        $upcoming = [];
        $q = Database::search("SELECT tt.time_table_id, tt.date, tt.start_time, tt.end_time, tt.lecture_title, lt.lecture_type
            FROM time_table tt LEFT JOIN lecture_type lt ON lt.lecture_type_id = tt.lecture_type_lecture_type_id
            WHERE tt.classes_class_id = '" . $classId . "' AND tt.time_table_status_time_table_status_id = 1 AND tt.lecture_type_lecture_type_id != 3
              AND TIMESTAMP(tt.date, tt.end_time) >= '" . $nowTs . "' ORDER BY tt.date, tt.start_time LIMIT 4");
        while ($q && ($r = $q->fetch_assoc())) {
            $upcoming[] = $r;
        }

        $att = Database::search("SELECT COUNT(DISTINCT tt.time_table_id) total,
                COUNT(DISTINCT CASE WHEN a.attendance_status_attendance_status_id IN (2, 4) THEN tt.time_table_id END) came,
                COUNT(DISTINCT CASE WHEN a.attendance_status_attendance_status_id = 2 THEN tt.time_table_id END) present
            FROM time_table tt
            LEFT JOIN attendance a ON a.time_table_time_table_id = tt.time_table_id AND a.students_student_id = '" . $studentId . "'
            WHERE tt.classes_class_id = '" . $classId . "' AND tt.time_table_status_time_table_status_id = 1 AND tt.lecture_type_lecture_type_id != 3
              AND TIMESTAMP(tt.date, tt.start_time) <= '" . $nowTs . "' AND tt.date >= DATE('" . substr((string) $c['added_at'], 0, 10) . "')")->fetch_assoc() ?: [];

        $month = $now->format('Y-m');
        $fee = Database::search("SELECT amount, discount, discount_type_discount_type_id dt, COALESCE(payment_status_payment_status_id, 1) st, due_date
            FROM student_has_payments WHERE students_has_class_students_has_class_id = '" . $enr . "' AND DATE_FORMAT(due_date, '%Y-%m') = '" . $month . "'
            ORDER BY student_has_payment_id DESC LIMIT 1")->fetch_assoc();
        $owed = (int) (Database::search("SELECT COUNT(*) n FROM student_has_payments WHERE students_has_class_students_has_class_id = '" . $enr . "'
            AND COALESCE(payment_status_payment_status_id, 1) IN (1, 4) AND due_date < CURDATE()")->fetch_assoc()['n'] ?? 0);

        $recent = [];
        $q = Database::search("SELECT lr.lecture_recording_id id, lr.recording_title title, tt.date, lr.duration
            FROM lecture_recording lr JOIN time_table tt ON tt.time_table_id = lr.time_table_time_table_id
            WHERE tt.classes_class_id = '" . $classId . "' ORDER BY tt.date DESC, lr.lecture_recording_id DESC LIMIT 3");
        while ($q && ($r = $q->fetch_assoc())) {
            $recent[] = $r;
        }
        $recCount = (int) (Database::search("SELECT COUNT(*) n FROM lecture_recording lr JOIN time_table tt ON tt.time_table_id = lr.time_table_time_table_id WHERE tt.classes_class_id = '" . $classId . "'")->fetch_assoc()['n'] ?? 0);
        $hasPre = enlixo_tutor_has((int) $c['tutor_id'], 'recordings.pre_recorded');
        $preCount = $hasPre ? (int) (Database::search("SELECT COUNT(*) n FROM pre_recordings WHERE classes_class_id = '" . $classId . "'")->fetch_assoc()['n'] ?? 0) : 0;
        $tutes = [];
        $q = Database::search("SELECT class_tute_id id, tute_title title, tute_types_tute_type_id type, date_added FROM class_tutes WHERE classes_class_id = '" . $classId . "' ORDER BY date_added DESC LIMIT 3");
        while ($q && ($r = $q->fetch_assoc())) {
            $tutes[] = $r;
        }
        $tuteCount = (int) (Database::search("SELECT COUNT(*) n FROM class_tutes WHERE classes_class_id = '" . $classId . "'")->fetch_assoc()['n'] ?? 0);

        $feeNet = null;
        if ($fee) {
            $disc = match ((int) $fee['dt']) { 2 => (float) $fee['discount'], 3 => (float) $fee['amount'] * (float) $fee['discount'] / 100, default => 0.0 };
            $feeNet = max(0.0, (float) $fee['amount'] - $disc);
        }
        $cover = !empty($c['class_cover']) && file_exists(Project::getAssetsPath() . '/imgs/classes-covers/' . basename($c['class_cover']))
            ? Project::getAssetsPath() . '/imgs/classes-covers/' . rawurlencode(basename($c['class_cover'])) : '';
        $types = array_unique(array_map(static fn ($u) => (string) $u['lecture_type'], $upcoming));
        return [
            'id' => $classId, 'name' => (string) $c['class_name'], 'cover' => $cover, 'free' => (int) $c['is_free'] === 1,
            'subject' => (string) $c['subject'], 'exam' => trim($c['year'] . ' ' . $c['exam_type']), 'medium' => (string) $c['medium'],
            'tutor' => trim($c['user_fname'] . ' ' . $c['user_lname']), 'code' => (string) ($c['student_class_id'] ?? ''),
            'joined' => date('j M Y', strtotime((string) $c['added_at'])), 'fee' => (float) ($c['fee'] ?? 0), 'due_day' => (int) ($c['due_day'] ?? 10),
            'mode' => $types ? implode(' / ', $types) : '',
            'upcoming' => $upcoming, 'next' => $upcoming[0] ?? null,
            'total' => (int) ($att['total'] ?? 0), 'came' => (int) ($att['came'] ?? 0), 'present' => (int) ($att['present'] ?? 0),
            'month_fee' => $fee ? ['status' => (int) $fee['st'], 'net' => $feeNet, 'due' => (string) $fee['due_date']] : null, 'owed' => $owed,
            'recordings' => $recCount, 'recent' => $recent, 'pre' => $hasPre ? $preCount : null, 'tutes' => $tuteCount, 'tute_list' => $tutes,
        ];
    }

    /**
     * $links: ['timetable' => url, 'attendance' => url, 'recordings' => url, 'pre' => url|null, 'tutes' => url|null, 'payments' => url,
     *          'day' => 'class day page prefix', 'recording' => 'watch page prefix', 'tute' => 'tute page prefix'|null]
     * $opts: ['who' => 'You' | child's first name]
     */
    function co_render(array $o, array $links, array $opts = []): void
    {
        $who = $opts['who'] ?? 'You';
        $lkr = static fn ($n) => 'LKR ' . number_format((float) $n);
        $when = static function (array $d): string {
            $day = date('Y-m-d', strtotime($d['date']));
            $label = $day === date('Y-m-d') ? 'Today' : ($day === date('Y-m-d', strtotime('+1 day')) ? 'Tomorrow' : date('D, j M', strtotime($day)));
            return $label . ' · ' . date('g:i A', strtotime($d['start_time']));
        };
        $rate = $o['total'] ? (int) round($o['came'] / $o['total'] * 100) : null;
        $mf = $o['month_fee'];
        [$feeValue, $feeText] = $o['free'] ? ['Free', 'This class has no fee'] : (!$mf ? ['-', 'No fee made for this month yet']
            : match ($mf['status']) {
                3, 8 => ['Paid', $lkr($mf['net']) . ' for ' . date('F', strtotime($mf['due']))],
                2 => ['Checking', 'Slip sent, the tutor is checking it'],
                4 => ['Rejected', 'Upload the slip again'],
                default => [$lkr($mf['net']), 'Due ' . date('j M', strtotime($mf['due']))],
            });
        ?>
                            <div class="ac-card co-hero">
                                <div class="co-cover">
                                    <span class="co-cover-empty"><?= enlixo_icon('classes', 40) ?></span>
                                    <?php if ($o['cover'] !== '') { ?><img src="<?= sl_e($o['cover']) ?>" alt="" onerror="this.remove()"><?php } ?>
                                </div>
                                <div class="co-hero-body">
                                    <div class="co-chips">
                                        <?php if ($o['subject'] !== '') { ?><span class="ac-badge"><?= sl_e($o['subject']) ?></span><?php } ?>
                                        <?php if ($o['exam'] !== '') { ?><span class="ac-badge is-purple"><?= sl_e($o['exam']) ?></span><?php } ?>
                                        <?php if ($o['medium'] !== '') { ?><span class="ac-badge is-muted"><?= sl_e($o['medium']) ?> medium</span><?php } ?>
                                        <?php if ($o['mode'] !== '') { ?><span class="ac-badge is-good"><?= sl_e($o['mode']) ?></span><?php } ?>
                                    </div>
                                    <h2 class="co-name"><?= sl_e($o['name']) ?></h2>
                                    <div class="co-facts">
                                        <?php if ($o['tutor'] !== '') { ?><span><?= enlixo_icon('teacher', 16, 'sl-ico') ?><?= sl_e($o['tutor']) ?></span><?php } ?>
                                        <span><?= enlixo_icon('wallet', 16, 'sl-ico') ?><?= $o['free'] ? 'Free class' : sl_e($lkr($o['fee']) . ' a month · due on the ' . $o['due_day'] . date('S', mktime(0, 0, 0, 1, max(1, $o['due_day'])))) ?></span>
                                        <?php if ($o['code'] !== '') { ?><span><?= enlixo_icon('id-card', 16, 'sl-ico') ?><?= sl_e($o['code']) ?></span><?php } ?>
                                        <span><?= enlixo_icon('calendar', 16, 'sl-ico') ?>Joined <?= sl_e($o['joined']) ?></span>
                                    </div>
                                </div>
                                <?php if ($o['next']) { ?>
                                    <a class="co-next" href="<?= sl_e($links['day'] . (int) $o['next']['time_table_id']) ?>">
                                        <span class="co-next-label">Next class</span>
                                        <strong><?= sl_e($when($o['next'])) ?></strong>
                                        <span><?= sl_e(($o['next']['lecture_title'] ?: $o['next']['lecture_type']) ?? '') ?></span>
                                    </a>
                                <?php } ?>
                            </div>

                            <div class="ac-tiles">
                                <?php
                                enlixo_stat_card('check', 'Attendance', $rate === null ? '-' : $rate . '%', $o['total'] ? $o['came'] . ' of ' . $o['total'] . ' class days (present or watched)' : 'No class day held yet');
                                enlixo_stat_card('calendar', 'Next Class', $o['next'] ? date('j M', strtotime($o['next']['date'])) : '-', $o['next'] ? date('l', strtotime($o['next']['date'])) . ' at ' . date('g:i A', strtotime($o['next']['start_time'])) : 'No class day planned yet');
                                enlixo_stat_card('wallet', "This Month's Fee", $feeValue, $o['owed'] ? $o['owed'] . ($o['owed'] === 1 ? ' month' : ' months') . ' still not paid' : $feeText);
                                enlixo_stat_card('recordings', 'Recordings', (string) $o['recordings'], $o['pre'] !== null ? $o['pre'] . ' pre-recorded ' . ($o['pre'] === 1 ? 'class' : 'classes') . ' too' : 'Recordings of the class days');
                                ?>
                            </div>

                            <div class="co-tools">
                                <?php
                                $tools = [
                                    ['timetable', 'Time Table', 'Every class day by week or month', $links['timetable'], count($o['upcoming']) ? count($o['upcoming']) . ' coming' : ''],
                                    ['check', 'Attendance', $who === 'You' ? 'The days you came, missed or watched' : "The days $who came, missed or watched", $links['attendance'], $rate === null ? '' : $rate . '%'],
                                    ['recordings', 'Class Recordings', 'Watch the class days again', $links['recordings'], (string) $o['recordings']],
                                    $o['pre'] !== null && !empty($links['pre']) ? ['pre-recorded', 'Pre-Recorded Classes', 'Ready-made lessons to watch any time', $links['pre'], (string) $o['pre']] : null,
                                    !empty($links['tutes']) ? ['tutes', 'Tutes', 'Notes to download and tutes by post', $links['tutes'], (string) $o['tutes']] : null,
                                    ['payments', 'Payments', 'Monthly fees, slips and receipts', $links['payments'], $o['owed'] ? $o['owed'] . ' due' : ''],
                                ];
                                foreach (array_filter($tools) as [$icon, $title, $text, $href, $count]) { ?>
                                    <a class="co-tool" href="<?= sl_e($href) ?>">
                                        <span class="co-tool-icon"><?= enlixo_icon($icon, 22) ?></span>
                                        <span class="co-tool-text"><strong><?= sl_e($title) ?></strong><small><?= sl_e($text) ?></small></span>
                                        <?php if ($count !== '' && $count !== '0') { ?><span class="ac-badge <?= str_ends_with($count, 'due') ? 'is-bad' : '' ?>"><?= sl_e($count) ?></span><?php } ?>
                                        <?= enlixo_icon('chevron-right', 18, 'co-tool-arrow') ?>
                                    </a>
                                <?php } ?>
                            </div>

                            <div class="ac-grid-2">
                                <div class="ac-card">
                                    <div class="ac-card-head"><h2 class="ac-card-title">Coming Class Days</h2><a class="co-more" href="<?= sl_e($links['timetable']) ?>">Time Table</a></div>
                                    <?php if (!$o['upcoming']) { ?>
                                        <div class="empty-state"><div class="empty-state-icon"><?= enlixo_icon('inbox', 28) ?></div><div class="empty-state-title">No class days planned</div><div class="empty-state-text">The tutor's next class days appear here.</div></div>
                                    <?php } ?>
                                    <div class="co-list">
                                        <?php foreach ($o['upcoming'] as $d) { ?>
                                            <a class="co-row" href="<?= sl_e($links['day'] . (int) $d['time_table_id']) ?>">
                                                <span class="co-date"><strong><?= date('j', strtotime($d['date'])) ?></strong><small><?= date('M', strtotime($d['date'])) ?></small></span>
                                                <span class="co-row-text"><strong><?= sl_e($d['lecture_title'] ?: $o['name']) ?></strong><small><?= sl_e(date('l', strtotime($d['date'])) . ' · ' . date('g:i A', strtotime($d['start_time'])) . ' - ' . date('g:i A', strtotime($d['end_time']))) ?></small></span>
                                                <span class="ac-badge <?= strtolower((string) $d['lecture_type']) === 'online' ? 'is-good' : '' ?>"><?= sl_e($d['lecture_type']) ?></span>
                                            </a>
                                        <?php } ?>
                                    </div>
                                </div>
                                <div class="ac-card">
                                    <div class="ac-card-head"><h2 class="ac-card-title">Latest Added</h2><a class="co-more" href="<?= sl_e($links['recordings']) ?>">Recordings</a></div>
                                    <?php if (!$o['recent'] && !$o['tute_list']) { ?>
                                        <div class="empty-state"><div class="empty-state-icon"><?= enlixo_icon('inbox', 28) ?></div><div class="empty-state-title">Nothing added yet</div><div class="empty-state-text">New recordings and tutes of this class appear here.</div></div>
                                    <?php } ?>
                                    <div class="co-list">
                                        <?php foreach ($o['recent'] as $r) { ?>
                                            <a class="co-row" href="<?= sl_e($links['recording'] . (int) $r['id']) ?>">
                                                <span class="co-row-icon"><?= enlixo_icon('play', 18) ?></span>
                                                <span class="co-row-text"><strong><?= sl_e($r['title'] ?: 'Class recording') ?></strong><small>Recording · <?= sl_e(date('j M', strtotime($r['date']))) ?><?= (int) $r['duration'] ? ' · ' . (int) $r['duration'] . ' min' : '' ?></small></span>
                                            </a>
                                        <?php }
                                        foreach ($o['tute_list'] as $t) {
                                            $href = !empty($links['tute']) ? $links['tute'] . (int) $t['id'] : ($links['tutes'] ?? '#'); ?>
                                            <a class="co-row" href="<?= sl_e($href) ?>">
                                                <span class="co-row-icon is-tute"><?= enlixo_icon((int) $t['type'] === 1 ? 'tutes' : 'box', 18) ?></span>
                                                <span class="co-row-text"><strong><?= sl_e($t['title']) ?></strong><small><?= (int) $t['type'] === 1 ? 'Tute to download' : 'Tute by post' ?> · <?= sl_e(date('j M', strtotime($t['date_added']))) ?></small></span>
                                            </a>
                                        <?php } ?>
                                    </div>
                                </div>
                            </div>
        <?php
    }
}

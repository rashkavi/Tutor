<?php
/**
 * Parent portal: which children, classes, fees and recordings a signed-in parent may see (ONE place), and the
 * progress of a child (attendance, class days, recordings watched after a missed class, upcoming classes, fees).
 * Every parent page asks here first; a parent only ever sees their own children (parents_has_students).
 * Read only.
 */
require_once __DIR__ . '/student-fees.php';
require_once __DIR__ . '/student-recordings.php';

if (!function_exists('ps_children')) {
    /** [student_id => ['id', 'name', 'first_name', 'img', 'classes']] of the parent */
    function ps_children(int $parentId): array
    {
        $out = [];
        $q = Database::search(
            "SELECT s.student_id, ld.first_name, ld.last_name, s.profile_img,
                    (SELECT COUNT(*) FROM students_has_classes h WHERE h.students_student_id = s.student_id AND h.status_status_id = 1) classes
               FROM parents_has_students phs
               JOIN students s ON s.student_id = phs.students_student_id
               JOIN login_details ld ON ld.login_details_id = s.login_details_login_details_id
              WHERE phs.parents_parent_id = '" . (int) $parentId . "'
              ORDER BY ld.first_name, s.student_id"
        );
        while ($r = $q->fetch_assoc()) {
            $out[(int) $r['student_id']] = [
                'id' => (int) $r['student_id'],
                'first_name' => (string) $r['first_name'],
                'name' => trim($r['first_name'] . ' ' . $r['last_name']),
                'img' => (string) ($r['profile_img'] ?? ''),
                'classes' => (int) $r['classes'],
            ];
        }
        return $out;
    }

    function ps_child(int $parentId, int $studentId): ?array
    {
        return ps_children($parentId)[$studentId] ?? null;
    }

    /** children of the parent who are in the class (active) */
    function ps_class_children(int $parentId, int $classId): array
    {
        $ids = [];
        $q = Database::search(
            "SELECT h.students_student_id id FROM students_has_classes h
               JOIN parents_has_students phs ON phs.students_student_id = h.students_student_id
              WHERE phs.parents_parent_id = '" . (int) $parentId . "' AND h.classes_class_id = '" . (int) $classId . "' AND h.status_status_id = 1"
        );
        while ($r = $q->fetch_assoc()) {
            $ids[] = (int) $r['id'];
        }
        return $ids;
    }

    /** the child a fee belongs to, 0 when it is not a fee of this parent's children */
    function ps_fee_child(int $parentId, int $paymentId): int
    {
        $q = Database::search(
            "SELECT h.students_student_id id FROM student_has_payments p
               JOIN students_has_classes h ON h.students_has_class_id = p.students_has_class_students_has_class_id
               JOIN parents_has_students phs ON phs.students_student_id = h.students_student_id
              WHERE phs.parents_parent_id = '" . (int) $parentId . "' AND p.student_has_payment_id = '" . (int) $paymentId . "' LIMIT 1"
        );
        return $q->num_rows ? (int) $q->fetch_assoc()['id'] : 0;
    }

    /** the child a recording is for (lecture recording or pre-recorded class), 0 when none of the children is in its class */
    function ps_recording_child(int $parentId, int $recordingId, string $kind = 'lecture'): int
    {
        $classSql = $kind === 'pre'
            ? "SELECT classes_class_id FROM pre_recordings WHERE pre_recording_id = '" . (int) $recordingId . "'"
            : "SELECT t.classes_class_id FROM lecture_recording r JOIN time_table t ON t.time_table_id = r.time_table_time_table_id WHERE r.lecture_recording_id = '" . (int) $recordingId . "'";
        $q = Database::search(
            "SELECT h.students_student_id id FROM students_has_classes h
               JOIN parents_has_students phs ON phs.students_student_id = h.students_student_id
              WHERE phs.parents_parent_id = '" . (int) $parentId . "' AND h.status_status_id = 1 AND h.classes_class_id IN ($classSql) LIMIT 1"
        );
        return $q->num_rows ? (int) $q->fetch_assoc()['id'] : 0;
    }

    /**
     * Progress of one child. Class days count from the day the child joined the class; only days that are over,
     * held (Active) and taught live (not a Recording-only day). Attendance: 1 joined online / 2 present = present,
     * 3 absent, 4 watched the recording, no mark = not marked yet.
     */
    function ps_progress(int $studentId): array
    {
        $now = (new DateTime('now', new DateTimeZone('Asia/Colombo')))->format('Y-m-d H:i:s');
        $today = substr($now, 0, 10);
        $days = [];
        $q = Database::search(
            "SELECT t.time_table_id, t.date, t.start_time, t.end_time, t.lecture_title, c.class_id, c.class_name,
                    (SELECT MAX(a.attendance_status_attendance_status_id) FROM attendance a WHERE a.time_table_time_table_id = t.time_table_id AND a.students_student_id = '" . (int) $studentId . "') att,
                    (SELECT r.lecture_recording_id FROM lecture_recording r WHERE r.time_table_time_table_id = t.time_table_id LIMIT 1) rec
               FROM time_table t
               JOIN classes c ON c.class_id = t.classes_class_id
               JOIN students_has_classes h ON h.classes_class_id = c.class_id AND h.students_student_id = '" . (int) $studentId . "' AND h.status_status_id = 1
              WHERE t.time_table_status_time_table_status_id = 1 AND t.lecture_type_lecture_type_id <> 3
                AND TIMESTAMP(t.date, t.start_time) <= '" . $now . "'
                AND (h.added_at IS NULL OR t.date >= DATE(h.added_at))
              ORDER BY t.date DESC, t.start_time DESC"
        );
        while ($r = $q->fetch_assoc()) {
            $a = (int) $r['att'];
            $days[] = [
                'id' => (int) $r['time_table_id'], 'date' => (string) $r['date'], 'start' => (string) $r['start_time'], 'end' => (string) $r['end_time'],
                'title' => (string) ($r['lecture_title'] ?: 'Class day'), 'class_id' => (int) $r['class_id'], 'class' => (string) $r['class_name'],
                'state' => match ($a) { 1, 2 => 'present', 3 => 'absent', 4 => 'watched', default => 'unmarked' },
                'recording' => (int) $r['rec'],
            ];
        }
        // recordings the child watched (lecture recordings): watch sessions or a "Watched" mark
        $watchedIds = [];
        foreach (sr_lecture_recordings($studentId) as $rec) {
            if ($rec['watch'] === 'watched') {
                $watchedIds[$rec['time_table_id']] = true;
            }
        }
        foreach ($days as &$d) {
            $d['recording_watched'] = isset($watchedIds[$d['id']]) || $d['state'] === 'watched';
        }
        unset($d);

        $count = static function (array $list): array {
            $c = ['present' => 0, 'absent' => 0, 'watched' => 0, 'unmarked' => 0];
            foreach ($list as $d) {
                $c[$d['state']]++;
            }
            $marked = $c['present'] + $c['absent'] + $c['watched'];
            $c['held'] = count($list);
            $c['rate'] = $marked > 0 ? (int) round($c['present'] / $marked * 100) : null;
            return $c;
        };
        $since = date('Y-m-d', strtotime($today . ' -30 days'));
        $recent = array_values(array_filter($days, static fn ($d) => $d['date'] >= $since));
        $byClass = [];
        foreach ($days as $d) {
            $byClass[$d['class_id']]['name'] = $d['class'];
            $byClass[$d['class_id']]['days'][] = $d;
        }
        foreach ($byClass as &$c) {
            $c += $count($c['days']);
            unset($c['days']);
        }
        unset($c);
        $missed = array_values(array_filter($days, static fn ($d) => $d['state'] === 'absent' && $d['recording'] && !$d['recording_watched']));

        // the next 7 days
        $upcoming = [];
        $q = Database::search(
            "SELECT t.time_table_id, t.date, t.start_time, t.end_time, t.lecture_title, t.lecture_type_lecture_type_id type, c.class_name
               FROM time_table t
               JOIN classes c ON c.class_id = t.classes_class_id
               JOIN students_has_classes h ON h.classes_class_id = c.class_id AND h.students_student_id = '" . (int) $studentId . "' AND h.status_status_id = 1
              WHERE t.time_table_status_time_table_status_id = 1 AND t.lecture_type_lecture_type_id <> 3
                AND TIMESTAMP(t.date, t.start_time) > '" . $now . "' AND t.date <= '" . date('Y-m-d', strtotime($today . ' +7 days')) . "'
              ORDER BY t.date, t.start_time LIMIT 8"
        );
        while ($r = $q->fetch_assoc()) {
            $upcoming[] = $r;
        }

        $fees = sf_fees($studentId);
        $toPay = array_filter($fees, static fn ($p) => $p['balance'] > 0.004);
        $late = array_filter($toPay, static fn ($p) => in_array($p['state'], ['overdue', 'rejected'], true));

        // what the parent should know first
        $attention = [];
        if ($late) {
            $attention[] = ['bad', 'wallet', count($late) . (count($late) === 1 ? ' fee is overdue' : ' fees are overdue'), sf_money(array_sum(array_column($late, 'balance'))) . ' to pay', 'payments?child=' . $studentId, 'Pay now'];
        }
        if ($missed) {
            $attention[] = ['warn', 'recordings', count($missed) . (count($missed) === 1 ? ' missed class not caught up' : ' missed classes not caught up'), 'The recording is there, but it has not been watched yet', null, null];
        }
        $last3 = array_slice($days, 0, 3);
        if (count($last3) === 3 && count(array_filter($last3, static fn ($d) => $d['state'] === 'absent')) >= 2) {
            $attention[] = ['bad', 'attendance', 'Absent from 2 of the last 3 class days', 'Talk to your child or the tutor', null, null];
        }
        if ($upcoming && $upcoming[0]['date'] === $today) {
            $attention[] = ['info', 'calendar', 'Class today at ' . date('g:i A', strtotime((string) $upcoming[0]['start_time'])), (string) $upcoming[0]['class_name'], null, null];
        }

        return [
            'all' => $count($days),
            'month' => $count($recent),
            'classes' => $byClass,
            'days' => array_slice($days, 0, 12),
            'missed' => $missed,
            'upcoming' => $upcoming,
            'fees' => $fees,
            'to_pay' => array_sum(array_column($toPay, 'balance')),
            'late' => array_sum(array_column($late, 'balance')),
            'attention' => $attention,
        ];
    }
}

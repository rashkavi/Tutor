<?php
declare(strict_types=1);

namespace Enlixo\Dashboard;

use DateTimeImmutable;
use Enlixo\Core\Database;

/**
 * Everything the tutor dashboard shows (tutor/dashboard.php, GET /api/v1/tutor/dashboard), read only.
 *
 * Rules (the same as the legacy Payments and Attendance pages):
 * - A monthly fee is one student_has_payments row; its month is date_added. Status 1 = awaiting payment,
 *   2 = slip waiting for approval, 3 = approved (paid), 4 = slip rejected (still owed).
 *   Net = amount + penalties - discount (type 2 = fixed, 3 = percent). A row whose net is 0 or less is a free card.
 * - A class day is a time_table row, status 1, not a recording (lecture type 3). It counts for attendance once it ended.
 *   Expected = the students enrolled (active) in the class on that day. Attended = attendance status 1 (joined online)
 *   or 2 (present); 4 = absent but watched the recording; no row = absent.
 * - Unpaid fees count only students still active in the class (old debts of students who left are not chased here).
 */
final class TutorDashboard
{
    private const WATCHED = 4;
    private const NET = '(p.amount + IFNULL(pen.total, 0) - CASE p.discount_type_discount_type_id WHEN 2 THEN IFNULL(p.discount, 0) WHEN 3 THEN p.amount * IFNULL(p.discount, 0) / 100 ELSE 0 END)';
    private const PENALTIES = 'LEFT JOIN (SELECT student_has_payments_student_has_payment_id pid, SUM(amount) total FROM student_has_penalty_payments GROUP BY student_has_payments_student_has_payment_id) pen ON pen.pid = p.student_has_payment_id';

    private array $schema = [];
    private DateTimeImmutable $now;

    public function __construct(private readonly Database $db, ?DateTimeImmutable $now = null)
    {
        $this->now = $now ?? new DateTimeImmutable('now');
    }

    public function overview(int $tutorId, bool $money): array
    {
        $classes = $this->classes($tutorId);
        $sessions = $this->finishedSessions($tutorId, $this->now->modify('-6 weeks')->modify('monday this week'));
        $att = $this->attendance($sessions);
        $fees = $money ? $this->feeMonths($tutorId, 12) : null;

        return [
            'money' => $money,
            'today' => $this->today($tutorId),
            'exams' => $this->examCountdowns($tutorId),
            'waiting' => $this->waiting($tutorId, $money),
            'stats' => $this->stats($tutorId, $classes, $att, $fees, $money),
            'charts' => $this->charts($tutorId, $classes, $att, $fees, $money),
            'upcoming' => $this->upcoming($tutorId),
            'followUp' => $this->followUp($tutorId, $money),
            'payments' => $money ? $this->recentPayments($tutorId) : [],
            'classes' => $this->classRows($tutorId, $classes, $att, $money),
        ];
    }

    /**
     * The small dashboard at the top of a class page (tutor/class-details.php, GET /api/v1/tutor/classes/{id}/summary).
     * Same rules as overview(), for one class. Null when the class is not this tutor's.
     */
    public function classSummary(int $tutorId, int $classId, bool $money): ?array
    {
        $all = $this->classes($tutorId);
        if (!isset($all[$classId])) {
            return null;
        }
        $c = $all[$classId];
        $att = $this->attendance($this->finishedSessions($tutorId, $this->now->modify('-6 weeks')->modify('monday this week'), $classId));
        $row = $this->classRows($tutorId, [$classId => $c], $att, $money)[0];
        $d28 = $this->now->modify('-27 days')->format('Y-m-d');
        $d56 = $this->now->modify('-55 days')->format('Y-m-d');
        $tomorrow = $this->now->modify('+1 day')->format('Y-m-d');

        // attendance per week, oldest first (last 6 weeks)
        $weeks = [];
        $monday = $this->now->modify('monday this week')->setTime(0, 0);
        for ($i = 5; $i >= 0; $i--) {
            $from = $monday->modify("-$i weeks");
            $to = $from->modify('+7 days');
            $w = ['label' => $from->format('j M'), 'attended' => 0, 'watched' => 0, 'absent' => 0];
            foreach ($att as $a) {
                if ($a['date'] >= $from->format('Y-m-d') && $a['date'] < $to->format('Y-m-d')) {
                    $w['attended'] += $a['attended'];
                    $w['watched'] += $a['watched'];
                    $w['absent'] += $a['absent'];
                }
            }
            $weeks[] = $w;
        }

        // next class days of this class
        $next = array_map(fn (array $r) => [
            'id' => (int) $r['time_table_id'],
            'date' => (string) $r['date'],
            'day' => (new DateTimeImmutable((string) $r['date']))->format('D j M'),
            'time' => $this->clock((string) $r['start_time']) . ' - ' . $this->clock((string) $r['end_time']),
            'mode' => (int) $r['lt'] === 2 ? 'Online' : 'Physical',
            'title' => (string) ($r['lecture_title'] ?? ''),
            'isToday' => (string) $r['date'] === $this->now->format('Y-m-d'),
        ], $this->db->all(
            'SELECT tt.time_table_id, tt.`date`, tt.start_time, tt.end_time, tt.lecture_type_lecture_type_id lt, tt.lecture_title
               FROM time_table tt
              WHERE tt.classes_class_id = :c AND tt.time_table_status_time_table_status_id = 1 AND tt.lecture_type_lecture_type_id <> 3
                AND (tt.`date` > :d OR (tt.`date` = :d2 AND tt.end_time > :n))
              ORDER BY tt.`date`, tt.start_time LIMIT 4',
            ['c' => $classId, 'd' => $this->now->format('Y-m-d'), 'd2' => $this->now->format('Y-m-d'), 'n' => $this->now->format('H:i:s')],
        ));

        $count = fn (string $sql, array $p = []) => (int) $this->db->value($sql, ['c' => $classId] + $p);
        $tutes = $count('SELECT COUNT(*) FROM class_tutes WHERE classes_class_id = :c');
        $recordings = $count('SELECT COUNT(*) FROM lecture_recording r JOIN time_table tt ON tt.time_table_id = r.time_table_time_table_id WHERE tt.classes_class_id = :c');
        $pre = $this->hasTable('pre_recordings') ? $count('SELECT COUNT(*) FROM pre_recordings WHERE classes_class_id = :c') : 0;
        $parcels = $this->hasTable('tute_deliveries')
            ? $count("SELECT COUNT(*) FROM tute_deliveries d JOIN class_tutes ct ON ct.class_tute_id = d.class_tutes_class_tute_id WHERE ct.classes_class_id = :c AND d.status IN ('pending', 'packed')")
            : 0;
        $slips = $money ? $count('SELECT COUNT(*) FROM student_has_payments p JOIN students_has_classes x ON x.students_has_class_id = p.students_has_class_students_has_class_id
                                    WHERE x.classes_class_id = :c AND p.payment_status_payment_status_id = 2') : 0;
        $inactive = $count('SELECT COUNT(*) FROM students_has_classes WHERE classes_class_id = :c AND status_status_id <> 1');
        $fee = $this->db->one('SELECT amount, due_date FROM class_payment_plans WHERE classes_class_id = :c ORDER BY class_payment_plan_id DESC LIMIT 1', ['c' => $classId]);
        $times = [];
        if ($this->hasTable('class_schedules')) {
            foreach ($this->db->all(
                'SELECT weekday, start_time, end_time, lecture_type_lecture_type_id lt FROM class_schedules
                  WHERE classes_class_id = :c AND status_status_id = 1 AND (valid_until IS NULL OR valid_until >= CURDATE()) ORDER BY weekday, start_time',
                ['c' => $classId],
            ) as $t) {
                $times[] = (['', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'][(int) $t['weekday']] ?? '') . ' ' . $this->clock((string) $t['start_time']) . ' - ' . $this->clock((string) $t['end_time'])
                    . ((int) $t['lt'] === 2 ? ' (online)' : '');
            }
        }

        $todo = [];
        if ($slips) {
            $todo[] = ['icon' => 'payments', 'tone' => 'orange', 'label' => $slips . ($slips === 1 ? ' payment slip to approve' : ' payment slips to approve'), 'href' => 'class-payments?class-id=' . $classId];
        }
        if ($money && $row['unpaidStudents']) {
            $todo[] = ['icon' => 'danger', 'tone' => 'red', 'label' => $row['unpaidStudents'] . ($row['unpaidStudents'] === 1 ? ' student has not paid' : ' students have not paid'), 'href' => 'class-payments?class-id=' . $classId];
        }
        if ($parcels) {
            $todo[] = ['icon' => 'tutes', 'tone' => 'purple', 'label' => $parcels . ($parcels === 1 ? ' tute parcel to send' : ' tute parcels to send'), 'href' => 'couriered-tute?class-id=' . $classId];
        }

        return [
            'money' => $money,
            'class' => [
                'id' => $classId, 'name' => (string) $c['class_name'], 'active' => (int) $c['status_status_id'] === 1, 'mode' => $row['mode'],
                'location' => (string) ($c['location'] ?? ''), 'medium' => (string) $c['medium'], 'year' => (string) $c['year'],
                'free' => (bool) $c['is_free'], 'fee' => $fee ? (float) $fee['amount'] : null, 'dueDay' => $fee && $fee['due_date'] !== null ? (int) $fee['due_date'] : null,
                'times' => $times,
            ],
            'stats' => [
                'students' => $row['students'], 'newStudents' => $row['newStudents'], 'inactive' => $inactive,
                'attendance' => $row['attendance'], 'attendanceBefore' => $this->rate($att, $d56, $d28, $classId), 'watched' => $row['watched'],
                'paid' => $row['paid'], 'due' => $row['due'], 'paidPct' => $row['paidPct'], 'unpaid' => $row['unpaid'], 'unpaidStudents' => $row['unpaidStudents'],
                'tutes' => $tutes, 'recordings' => $recordings, 'preRecordings' => $pre, 'parcels' => $parcels,
            ],
            'weeks' => $weeks,
            'next' => $next,
            'todo' => $todo,
            'attendanceCheck' => $this->rate($att, $d28, $tomorrow, $classId),
        ];
    }

    // ---------------------------------------------------------------- exam dates

    /** Exam type + year pairs of the tutor's active classes, with the date the tutor set (null = not set). */
    public function examDates(int $tutorId): array
    {
        $saved = [];
        if ($this->hasTable('tutor_exam_dates')) {
            foreach ($this->db->all('SELECT exam_type_exam_type_id e, year_year_id y, exam_date FROM tutor_exam_dates WHERE tutor_id = :t', ['t' => $tutorId]) as $r) {
                $saved[$r['e'] . '-' . $r['y']] = (string) $r['exam_date'];
            }
        }
        $rows = $this->db->all(
            'SELECT DISTINCT c.exam_type_exam_type_id e, c.year_year_id y, e.exam_type, yr.`year`
               FROM classes c JOIN exam_type e ON e.exam_type_id = c.exam_type_exam_type_id JOIN `year` yr ON yr.year_id = c.year_year_id
              WHERE c.tutor_id = :t AND c.status_status_id = 1
              ORDER BY yr.`year`, e.exam_type',
            ['t' => $tutorId],
        );
        return array_map(static fn (array $r) => [
            'exam_type_id' => (int) $r['e'], 'year_id' => (int) $r['y'],
            'name' => trim($r['year'] . ' ' . $r['exam_type']),
            'date' => $saved[$r['e'] . '-' . $r['y']] ?? null,
        ], $rows);
    }

    /** @param list<array{exam_type_id:int, year_id:int, date:?string}> $items */
    public function saveExamDates(int $tutorId, array $items): void
    {
        $allowed = [];
        foreach ($this->examDates($tutorId) as $x) {
            $allowed[$x['exam_type_id'] . '-' . $x['year_id']] = true;
        }
        $this->db->transaction(function () use ($tutorId, $items, $allowed) {
            foreach ($items as $it) {
                $key = (int) $it['exam_type_id'] . '-' . (int) $it['year_id'];
                if (!isset($allowed[$key])) {
                    continue;
                }
                $p = ['t' => $tutorId, 'e' => (int) $it['exam_type_id'], 'y' => (int) $it['year_id']];
                if ($it['date'] === null) {
                    $this->db->execute('DELETE FROM tutor_exam_dates WHERE tutor_id = :t AND exam_type_exam_type_id = :e AND year_year_id = :y', $p);
                } else {
                    $this->db->execute(
                        'INSERT INTO tutor_exam_dates (tutor_id, exam_type_exam_type_id, year_year_id, exam_date, updated_at) VALUES (:t, :e, :y, :d, NOW())
                         ON DUPLICATE KEY UPDATE exam_date = VALUES(exam_date), updated_at = NOW()',
                        $p + ['d' => $it['date']],
                    );
                }
            }
        });
    }

    public function examTableReady(): bool
    {
        return $this->hasTable('tutor_exam_dates');
    }

    private function examCountdowns(int $tutorId): array
    {
        $today = $this->now->setTime(0, 0);
        $out = [];
        foreach ($this->examDates($tutorId) as $x) {
            if ($x['date'] === null) {
                continue;
            }
            $d = new DateTimeImmutable($x['date']);
            if ($d < $today) {
                continue;
            }
            $out[] = ['name' => $x['name'], 'date' => $x['date'], 'days' => (int) $today->diff($d)->days];
        }
        usort($out, static fn ($a, $b) => $a['days'] <=> $b['days']);
        return array_slice($out, 0, 3);
    }

    // ---------------------------------------------------------------- classes and sessions

    /** Every class of the tutor with its active students. */
    private function classes(int $tutorId): array
    {
        $rows = $this->db->all(
            "SELECT c.class_id, c.class_name, c.status_status_id, c.delivery_mode, c.location, c.is_free, yr.`year`, m.medium,
                    (SELECT COUNT(*) FROM students_has_classes x WHERE x.classes_class_id = c.class_id AND x.status_status_id = 1) AS students,
                    (SELECT COUNT(*) FROM students_has_classes x WHERE x.classes_class_id = c.class_id AND x.status_status_id = 1 AND x.added_at >= :m) AS new_students
               FROM classes c
               JOIN `year` yr ON yr.year_id = c.year_year_id
               JOIN medium m ON m.medium_id = c.medium_medium_id
              WHERE c.tutor_id = :t
              ORDER BY c.status_status_id, c.sort_order IS NULL, c.sort_order, c.class_id DESC",
            ['t' => $tutorId, 'm' => $this->monthStart(0)->format('Y-m-d')],
        );
        $out = [];
        foreach ($rows as $r) {
            $out[(int) $r['class_id']] = $r;
        }
        return $out;
    }

    /**
     * Class days that ended between $from and now, with the students enrolled on that day.
     * @return array<int, array{id:int, class:int, date:string, roster:list<int>}>
     */
    private function finishedSessions(int $tutorId, DateTimeImmutable $from, ?int $classId = null): array
    {
        $params = ['t' => $tutorId, 'f' => $from->format('Y-m-d'), 'd' => $this->now->format('Y-m-d'), 'n' => $this->now->format('H:i:s')];
        $rows = $this->db->all(
            'SELECT tt.time_table_id, tt.classes_class_id, tt.`date`
               FROM time_table tt JOIN classes c ON c.class_id = tt.classes_class_id
              WHERE c.tutor_id = :t AND tt.time_table_status_time_table_status_id = 1 AND tt.lecture_type_lecture_type_id <> 3
                AND tt.`date` >= :f AND (tt.`date` < :d OR (tt.`date` = :d2 AND tt.end_time <= :n))'
                . ($classId !== null ? ' AND c.class_id = :c' : '') . '
              ORDER BY tt.`date`, tt.start_time',
            $params + ['d2' => $params['d']] + ($classId !== null ? ['c' => $classId] : []),
        );
        if ($rows === []) {
            return [];
        }
        $enrol = [];
        foreach ($this->db->all(
            'SELECT x.classes_class_id c, x.students_student_id s, DATE(x.added_at) a FROM students_has_classes x JOIN classes c ON c.class_id = x.classes_class_id
              WHERE c.tutor_id = :t AND x.status_status_id = 1',
            ['t' => $tutorId],
        ) as $e) {
            $enrol[(int) $e['c']][] = [(int) $e['s'], (string) ($e['a'] ?? '')];
        }
        $out = [];
        foreach ($rows as $r) {
            $roster = [];
            foreach ($enrol[(int) $r['classes_class_id']] ?? [] as [$s, $added]) {
                if ($added === '' || $added <= $r['date']) {
                    $roster[] = $s;
                }
            }
            $out[(int) $r['time_table_id']] = ['id' => (int) $r['time_table_id'], 'class' => (int) $r['classes_class_id'], 'date' => (string) $r['date'], 'roster' => $roster];
        }
        return $out;
    }

    /**
     * Per session: attended / watched / absent of the roster.
     * @return array<int, array{class:int, date:string, expected:int, attended:int, watched:int, absent:int, missed:list<int>, came:array<int,int>}>
     */
    private function attendance(array $sessions): array
    {
        if ($sessions === []) {
            return [];
        }
        $marks = [];
        foreach (array_chunk(array_keys($sessions), 500) as $chunk) {
            $in = implode(',', array_map('intval', $chunk));
            foreach ($this->db->all("SELECT time_table_time_table_id tt, students_student_id s, attendance_status_attendance_status_id st FROM attendance WHERE time_table_time_table_id IN ($in)") as $a) {
                $marks[(int) $a['tt']][(int) $a['s']] = (int) $a['st'];
            }
        }
        $out = [];
        foreach ($sessions as $id => $s) {
            $attended = $watched = 0;
            $came = [];
            foreach ($s['roster'] as $student) {
                $st = $marks[$id][$student] ?? 0;
                $came[$student] = $st;
                if ($st === 1 || $st === 2) {
                    $attended++;
                } elseif ($st === self::WATCHED) {
                    $watched++;
                }
            }
            $expected = count($s['roster']);
            $out[$id] = ['class' => $s['class'], 'date' => $s['date'], 'expected' => $expected, 'attended' => $attended, 'watched' => $watched, 'absent' => $expected - $attended - $watched, 'came' => $came];
        }
        return $out;
    }

    /** Attendance rate (attended + watched) of the sessions from $from (inclusive) to $to (exclusive), or null when there were none. */
    private function rate(array $att, string $from, string $to, ?int $classId = null): ?int
    {
        $exp = $got = 0;
        foreach ($att as $a) {
            if ($a['date'] >= $from && $a['date'] < $to && ($classId === null || $a['class'] === $classId)) {
                $exp += $a['expected'];
                $got += $a['attended'] + $a['watched'];
            }
        }
        return $exp ? (int) round($got / $exp * 100) : null;
    }

    // ---------------------------------------------------------------- fees

    /** Net fees per month (oldest first) for the last $months months: due, paid, waiting, owed, free (gross of free cards). */
    private function feeMonths(int $tutorId, int $months): array
    {
        $from = $this->monthStart($months - 1);
        $rows = $this->db->all(
            'SELECT DATE_FORMAT(p.date_added, "%Y-%m") ym, p.payment_status_payment_status_id st, ' . self::NET . ' AS net, p.amount
               FROM student_has_payments p
               JOIN students_has_classes x ON x.students_has_class_id = p.students_has_class_students_has_class_id
               JOIN classes c ON c.class_id = x.classes_class_id
               ' . self::PENALTIES . '
              WHERE c.tutor_id = :t AND p.date_added >= :f AND p.date_added < :e',
            ['t' => $tutorId, 'f' => $from->format('Y-m-d'), 'e' => $this->monthStart(-1)->format('Y-m-d')],
        );
        $out = [];
        for ($i = $months - 1; $i >= 0; $i--) {
            $m = $this->monthStart($i);
            $out[$m->format('Y-m')] = ['label' => $m->format('M'), 'due' => 0.0, 'paid' => 0.0, 'waiting' => 0.0, 'owed' => 0.0, 'free' => 0.0];
        }
        foreach ($rows as $r) {
            if (!isset($out[$r['ym']])) {
                continue;
            }
            $net = (float) $r['net'];
            if ($net <= 0) {
                $out[$r['ym']]['free'] += (float) $r['amount'];
                continue;
            }
            $out[$r['ym']]['due'] += $net;
            $key = match ((int) $r['st']) { 3 => 'paid', 2 => 'waiting', default => 'owed' };
            $out[$r['ym']][$key] += $net;
        }
        return $out;
    }

    /**
     * Fees still owed by students who are active in the class: one row per enrolment with the months and amount.
     * @return list<array{enrolment:int, student:int, class:int, months:int, amount:float}>
     */
    private function owed(int $tutorId): array
    {
        return array_map(static fn (array $r) => [
            'enrolment' => (int) $r['eid'], 'student' => (int) $r['sid'], 'class' => (int) $r['cid'], 'months' => (int) $r['months'], 'amount' => (float) $r['amount'],
        ], $this->db->all(
            'SELECT x.students_has_class_id eid, x.students_student_id sid, x.classes_class_id cid, COUNT(*) months, SUM(' . self::NET . ') amount
               FROM student_has_payments p
               JOIN students_has_classes x ON x.students_has_class_id = p.students_has_class_students_has_class_id AND x.status_status_id = 1
               JOIN classes c ON c.class_id = x.classes_class_id
               ' . self::PENALTIES . '
              WHERE c.tutor_id = :t AND p.payment_status_payment_status_id IN (1, 4) AND p.date_added < :e AND ' . self::NET . ' > 0
              GROUP BY x.students_has_class_id, x.students_student_id, x.classes_class_id',
            ['t' => $tutorId, 'e' => $this->monthStart(-1)->format('Y-m-d')],
        ));
    }

    // ---------------------------------------------------------------- sections

    private function today(int $tutorId): array
    {
        return array_map(fn (array $s) => [
            'name' => $s['name'], 'time' => $s['time_from'], 'mode' => $s['mode'], 'done' => $s['done'], 'href' => $s['href'], 'external' => $s['external'],
        ], $this->sessionsBetween($tutorId, $this->now, $this->now));
    }

    private function upcoming(int $tutorId): array
    {
        $rows = $this->sessionsBetween($tutorId, $this->now, $this->now->modify('+6 days'));
        $rows = array_values(array_filter($rows, static fn (array $s) => !$s['done']));
        return array_map(static fn (array $s) => [
            'name' => $s['name'], 'subtitle' => $s['subtitle'], 'day' => $s['day'], 'time' => $s['time'], 'mode' => $s['mode'],
            'students' => $s['students'], 'isToday' => $s['is_today'], 'href' => $s['href'], 'external' => $s['external'],
        ], array_slice($rows, 0, 8));
    }

    /** Class days from $from to $to (dates, inclusive), not cancelled, not recordings. */
    private function sessionsBetween(int $tutorId, DateTimeImmutable $from, DateTimeImmutable $to): array
    {
        $rows = $this->db->all(
            'SELECT tt.time_table_id, tt.`date`, tt.start_time, tt.end_time, tt.lecture_type_lecture_type_id lt, tt.lecture,
                    c.class_id, c.class_name, c.location, m.medium,
                    (SELECT COUNT(*) FROM students_has_classes x WHERE x.classes_class_id = c.class_id AND x.status_status_id = 1) AS students
               FROM time_table tt
               JOIN classes c ON c.class_id = tt.classes_class_id
               JOIN medium m ON m.medium_id = c.medium_medium_id
              WHERE c.tutor_id = :t AND tt.time_table_status_time_table_status_id = 1 AND tt.lecture_type_lecture_type_id <> 3
                AND tt.`date` BETWEEN :f AND :e
              ORDER BY tt.`date`, tt.start_time',
            ['t' => $tutorId, 'f' => $from->format('Y-m-d'), 'e' => $to->format('Y-m-d')],
        );
        $today = $this->now->format('Y-m-d');
        $nowTime = $this->now->format('H:i:s');
        return array_map(function (array $r) use ($today, $nowTime) {
            $online = (int) $r['lt'] === 2;
            $isToday = $r['date'] === $today;
            $link = trim((string) ($r['lecture'] ?? ''));
            $external = $online && $isToday && preg_match('#^https://#i', $link) === 1;
            return [
                'name' => (string) $r['class_name'],
                'subtitle' => trim($r['medium'] . ' medium · ' . ($online ? 'Zoom' : ((string) $r['location'] ?: 'Physical class'))),
                'day' => $isToday ? 'Today' : (new DateTimeImmutable($r['date']))->format('D j M'),
                'time' => $this->clock($r['start_time']) . ' - ' . $this->clock($r['end_time']),
                'time_from' => $this->clock($r['start_time']),
                'mode' => $online ? 'Online' : 'Physical',
                'students' => (int) $r['students'],
                'is_today' => $isToday,
                'done' => $isToday && $r['end_time'] <= $nowTime,
                'href' => $external ? $link : 'time-table-attendance?time-table-id=' . (int) $r['time_table_id'],
                'external' => $external,
            ];
        }, $rows);
    }

    private function waiting(int $tutorId, bool $money): array
    {
        $items = [];
        if ($money) {
            $slips = (int) $this->db->value(
                'SELECT COUNT(*) FROM student_has_payments p JOIN students_has_classes x ON x.students_has_class_id = p.students_has_class_students_has_class_id
                   JOIN classes c ON c.class_id = x.classes_class_id WHERE c.tutor_id = :t AND p.payment_status_payment_status_id = 2',
                ['t' => $tutorId],
            );
            if ($slips) {
                $items[] = ['label' => 'Payment slips to approve', 'short' => $slips === 1 ? 'slip' : 'slips', 'help' => 'Students uploaded bank slips for their fees', 'count' => $slips, 'icon' => 'payments', 'tone' => 'orange', 'href' => 'payments'];
            }
        }
        if ($this->hasTable('student_applications')) {
            $apps = (int) $this->db->value("SELECT COUNT(*) FROM student_applications WHERE tutor_id = :t AND status IN ('pending', 'in_review')", ['t' => $tutorId]);
            if ($apps) {
                $items[] = ['label' => 'New applications', 'short' => $apps === 1 ? 'application' : 'applications', 'help' => 'Students asked to join your classes', 'count' => $apps, 'icon' => 'application', 'tone' => '', 'href' => 'requested-students'];
            }
        }
        $noRec = (int) $this->db->value(
            'SELECT COUNT(*) FROM time_table tt JOIN classes c ON c.class_id = tt.classes_class_id
              WHERE c.tutor_id = :t AND tt.time_table_status_time_table_status_id = 1 AND tt.lecture_type_lecture_type_id = 2
                AND tt.`date` >= :f AND (tt.`date` < :d OR (tt.`date` = :d2 AND tt.end_time <= :n))
                AND NOT EXISTS (SELECT 1 FROM lecture_recording r WHERE r.time_table_time_table_id = tt.time_table_id)',
            ['t' => $tutorId, 'f' => $this->now->modify('-7 days')->format('Y-m-d'), 'd' => $this->now->format('Y-m-d'), 'd2' => $this->now->format('Y-m-d'), 'n' => $this->now->format('H:i:s')],
        );
        if ($noRec) {
            $items[] = ['label' => 'Recordings to upload', 'short' => $noRec === 1 ? 'upload' : 'uploads', 'help' => 'Online classes of the last 7 days have no recording yet', 'count' => $noRec, 'icon' => 'upload', 'tone' => 'green', 'href' => 'time-table'];
        }
        return ['total' => array_sum(array_column($items, 'count')), 'items' => $items];
    }

    private function stats(int $tutorId, array $classes, array $att, ?array $fees, bool $money): array
    {
        $students = (int) $this->db->value(
            'SELECT COUNT(DISTINCT x.students_student_id) FROM students_has_classes x JOIN classes c ON c.class_id = x.classes_class_id WHERE c.tutor_id = :t AND x.status_status_id = 1',
            ['t' => $tutorId],
        );
        $growth = $this->growth($tutorId, 6);
        $last = end($growth['joined']) ?: 0;

        $wk = $this->now->modify('monday this week')->setTime(0, 0);
        $weeks = [];
        for ($i = 5; $i >= 0; $i--) {
            $from = $wk->modify("-$i weeks");
            $weeks[] = ['label' => $from->format('j M'), 'value' => $this->rate($att, $from->format('Y-m-d'), $from->modify('+1 week')->format('Y-m-d')) ?? 0];
        }
        $d28 = $this->now->modify('-27 days')->format('Y-m-d');
        $d56 = $this->now->modify('-55 days')->format('Y-m-d');
        $tomorrow = $this->now->modify('+1 day')->format('Y-m-d');

        $week = $this->sessionsBetween($tutorId, $wk, $wk->modify('+6 days'));
        $hours = 0.0;
        $online = 0;
        $byDay = array_fill(0, 7, 0);
        foreach ($this->db->all(
            'SELECT tt.`date`, TIME_TO_SEC(TIMEDIFF(tt.end_time, tt.start_time)) secs, tt.lecture_type_lecture_type_id lt, tt.class_schedules_class_schedule_id slot
               FROM time_table tt JOIN classes c ON c.class_id = tt.classes_class_id
              WHERE c.tutor_id = :t AND tt.time_table_status_time_table_status_id = 1 AND tt.lecture_type_lecture_type_id <> 3 AND tt.`date` BETWEEN :f AND :e',
            ['t' => $tutorId, 'f' => $wk->format('Y-m-d'), 'e' => $wk->modify('+6 days')->format('Y-m-d')],
        ) as $r) {
            $hours += max(0, (int) $r['secs']) / 3600;
            $online += (int) $r['lt'] === 2 ? 1 : 0;
            $byDay[(int) (new DateTimeImmutable($r['date']))->format('N') - 1]++;
        }
        $days = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];

        $out = [
            'students' => ['value' => $students, 'now' => $last, 'left' => $growth['hasLeft'] ? (end($growth['left']) ?: 0) : null, 'bars' => array_map(static fn ($l, $v) => ['label' => $l, 'value' => $v], $growth['labels'], $growth['active'])],
            'attendance' => ['value' => $this->rate($att, $d28, $tomorrow), 'before' => $this->rate($att, $d56, $d28), 'bars' => $weeks],
            'week' => ['value' => count($week), 'hours' => round($hours, 1), 'online' => $online, 'physical' => count($week) - $online, 'bars' => array_map(static fn ($l, $v) => ['label' => $l, 'value' => $v], $days, $byDay)],
        ];
        if ($money && $fees !== null) {
            $months = array_values($fees);
            $cur = $months[count($months) - 1];
            $prev = $months[count($months) - 2];
            $owed = $this->owed($tutorId);
            $byStudent = [];
            foreach ($owed as $o) {
                $byStudent[$o['student']] = max($byStudent[$o['student']] ?? 0, $o['months']);
            }
            $out['collected'] = ['value' => round($cur['paid']), 'before' => round($prev['paid']), 'expected' => round($cur['due']),
                'bars' => array_map(static fn ($m) => ['label' => $m['label'], 'value' => round($m['paid'])], array_slice($months, -6))];
            $out['unpaid'] = ['value' => round(array_sum(array_column($owed, 'amount'))), 'students' => count($byStudent),
                'overdue' => count(array_filter($byStudent, static fn ($n) => $n >= 2)),
                'bars' => array_map(static fn ($m) => ['label' => $m['label'], 'value' => round($m['owed'])], array_slice($months, -6))];
        }
        return $out;
    }

    /** Joined / left per month and active students at the end of each month (oldest first). */
    private function growth(int $tutorId, int $months): array
    {
        $hasLeft = $this->hasColumn('students_has_classes', 'status_changed_at');
        $rows = $this->db->all(
            'SELECT x.added_at, x.status_status_id st' . ($hasLeft ? ', x.status_changed_at ch' : '') . '
               FROM students_has_classes x JOIN classes c ON c.class_id = x.classes_class_id WHERE c.tutor_id = :t',
            ['t' => $tutorId],
        );
        $out = ['labels' => [], 'joined' => [], 'left' => [], 'active' => [], 'hasLeft' => $hasLeft];
        for ($i = $months - 1; $i >= 0; $i--) {
            $start = $this->monthStart($i)->format('Y-m-d');
            $end = $this->monthStart($i - 1)->format('Y-m-d');
            $joined = $left = $active = 0;
            foreach ($rows as $r) {
                $added = substr((string) ($r['added_at'] ?? ''), 0, 10);
                $changed = $hasLeft ? substr((string) ($r['ch'] ?? ''), 0, 10) : '';
                $isActive = (int) $r['st'] === 1;
                if ($added !== '' && $added >= $start && $added < $end) {
                    $joined++;
                }
                if (!$isActive && $changed !== '' && $changed >= $start && $changed < $end) {
                    $left++;
                }
                // active at the end of the month: joined before it ended and still active, or left after it ended
                if (($added === '' || $added < $end) && ($isActive || ($changed !== '' && $changed >= $end))) {
                    $active++;
                }
            }
            $out['labels'][] = $this->monthStart($i)->format('M');
            $out['joined'][] = $joined;
            $out['left'][] = $left;
            $out['active'][] = $active;
        }
        return $out;
    }

    private function charts(int $tutorId, array $classes, array $att, ?array $fees, bool $money): array
    {
        $wk = $this->now->modify('monday this week')->setTime(0, 0);
        $a = ['labels' => [], 'present' => [], 'watched' => [], 'absent' => []];
        for ($i = 5; $i >= 0; $i--) {
            $from = $wk->modify("-$i weeks")->format('Y-m-d');
            $to = $wk->modify('-' . ($i - 1) . ' weeks')->format('Y-m-d');
            $p = $w = $ab = 0;
            foreach ($att as $s) {
                if ($s['date'] >= $from && $s['date'] < $to) {
                    $p += $s['attended'];
                    $w += $s['watched'];
                    $ab += $s['absent'];
                }
            }
            $a['labels'][] = (new DateTimeImmutable($from))->format('j M');
            $a['present'][] = $p;
            $a['watched'][] = $w;
            $a['absent'][] = $ab;
        }
        $active = array_filter($classes, static fn ($c) => (int) $c['status_status_id'] === 1);
        usort($active, static fn ($x, $y) => (int) $y['students'] <=> (int) $x['students']);
        $growth = $this->growth($tutorId, 6);

        $out = [
            'monthName' => $this->now->format('F'),
            'attendance' => $a,
            'classes' => array_map(static fn ($c) => ['label' => (string) $c['class_name'], 'value' => (int) $c['students']], array_slice($active, 0, 8)),
            'growth' => ['labels' => $growth['labels'], 'joined' => $growth['joined'], 'left' => $growth['hasLeft'] ? $growth['left'] : null],
        ];
        if ($money && $fees !== null) {
            $out['fees'] = ['labels' => array_column($fees, 'label'), 'collected' => array_map(static fn ($m) => round($m['paid']), array_values($fees)), 'due' => array_map(static fn ($m) => round($m['due']), array_values($fees))];
            $cur = end($fees);
            $out['month'] = [
                ['label' => 'Paid', 'value' => round($cur['paid']), 'color' => 'success'],
                ['label' => 'Waiting for approval', 'value' => round($cur['waiting']), 'color' => 'orange'],
                ['label' => 'Not paid', 'value' => round($cur['owed']), 'color' => 'danger'],
                ['label' => 'Free cards', 'value' => round($cur['free']), 'color' => 'grey'],
            ];
        }
        return $out;
    }

    /** Active students who missed the last 3 class days, owe 2 months or more, or missed a recorded class and did not watch it. */
    private function followUp(int $tutorId, bool $money): array
    {
        $reasons = [];
        $add = static function (int $student, int $class, string $type, string $text) use (&$reasons) {
            $reasons[$student . '-' . $class][] = ['type' => $type, 'text' => $text];
        };

        // missed the last 3 finished class days of a class they were enrolled in for all three
        $att = $this->attendance($this->finishedSessions($tutorId, $this->now->modify('-90 days')));
        $byClass = [];
        foreach ($att as $id => $s) {
            $byClass[$s['class']][$id] = $s;
        }
        foreach ($byClass as $classId => $list) {
            $last = array_slice($list, -3, 3, true);
            if (count($last) < 3) {
                continue;
            }
            $students = array_keys(reset($last)['came']);
            foreach ($students as $student) {
                $missed = true;
                foreach ($last as $s) {
                    $st = $s['came'][$student] ?? null;
                    if ($st === null || in_array($st, [1, 2, self::WATCHED], true)) {
                        $missed = false;
                        break;
                    }
                }
                if ($missed) {
                    $add($student, $classId, 'absent', 'Missed 3 classes');
                }
            }
        }

        // missed a recorded class in the last 14 days and has not watched it
        $recorded = [];
        foreach ($this->db->all('SELECT DISTINCT time_table_time_table_id tt FROM lecture_recording WHERE time_table_time_table_id IS NOT NULL') as $r) {
            $recorded[(int) $r['tt']] = true;
        }
        $since = $this->now->modify('-14 days')->format('Y-m-d');
        $flagged = [];
        foreach ($att as $id => $s) {
            if ($s['date'] < $since || !isset($recorded[$id])) {
                continue;
            }
            foreach ($s['came'] as $student => $st) {
                if (!in_array($st, [1, 2, self::WATCHED], true) && !isset($flagged[$student . '-' . $s['class']])) {
                    $flagged[$student . '-' . $s['class']] = true;
                    $add($student, $s['class'], 'watch', "Didn't watch the recording");
                }
            }
        }

        if ($money) {
            foreach ($this->owed($tutorId) as $o) {
                if ($o['months'] >= 2) {
                    $add($o['student'], $o['class'], 'unpaid', $o['months'] . ' months unpaid');
                }
            }
        }
        if ($reasons === []) {
            return [];
        }
        $keys = array_keys($reasons);
        usort($keys, static fn ($a, $b) => count($reasons[$b]) <=> count($reasons[$a]));
        $keys = array_slice($keys, 0, 6);

        $students = $classNames = [];
        $sids = array_unique(array_map(static fn ($k) => (int) explode('-', $k)[0], $keys));
        $in = implode(',', array_map('intval', $sids));
        foreach ($this->db->all("SELECT s.student_id, ld.first_name, ld.last_name, ld.whatsapp, s.mobile FROM students s JOIN login_details ld ON ld.login_details_id = s.login_details_login_details_id WHERE s.student_id IN ($in)") as $r) {
            $students[(int) $r['student_id']] = $r;
        }
        foreach ($this->db->all('SELECT class_id, class_name FROM classes WHERE tutor_id = :t', ['t' => $tutorId]) as $r) {
            $classNames[(int) $r['class_id']] = (string) $r['class_name'];
        }
        $out = [];
        foreach ($keys as $k) {
            [$sid, $cid] = array_map('intval', explode('-', $k));
            $s = $students[$sid] ?? null;
            if ($s === null) {
                continue;
            }
            $phone = (string) ($s['whatsapp'] ?: $s['mobile']);
            $out[] = [
                'id' => $sid,
                'name' => trim($s['first_name'] . ' ' . $s['last_name']),
                'phone' => $phone,
                'whatsapp' => self::waNumber($phone),
                'className' => $classNames[$cid] ?? '',
                'reasons' => $reasons[$k],
            ];
        }
        return $out;
    }

    private function recentPayments(int $tutorId): array
    {
        $rows = $this->db->all(
            'SELECT ld.first_name, ld.last_name, c.class_name, p.date_added, p.date_paid, p.payment_status_payment_status_id st, ' . self::NET . ' AS net
               FROM student_has_payments p
               JOIN students_has_classes x ON x.students_has_class_id = p.students_has_class_students_has_class_id
               JOIN classes c ON c.class_id = x.classes_class_id
               JOIN students s ON s.student_id = x.students_student_id
               JOIN login_details ld ON ld.login_details_id = s.login_details_login_details_id
               ' . self::PENALTIES . '
              WHERE c.tutor_id = :t AND p.payment_status_payment_status_id IN (2, 3, 4) AND p.date_paid IS NOT NULL AND ' . self::NET . ' > 0
              ORDER BY p.date_paid DESC LIMIT 6',
            ['t' => $tutorId],
        );
        return array_map(fn (array $r) => [
            'name' => trim($r['first_name'] . ' ' . $r['last_name']),
            'when' => $this->ago((string) $r['date_paid']),
            'className' => (string) $r['class_name'],
            'month' => (new DateTimeImmutable((string) $r['date_added']))->format('F') . ' fee',
            'amount' => round((float) $r['net']),
            'status' => match ((int) $r['st']) { 3 => 'Approved', 4 => 'Rejected', default => 'Waiting' },
        ], $rows);
    }

    private function classRows(int $tutorId, array $classes, array $att, bool $money): array
    {
        $month = [];
        $owed = [];
        if ($money) {
            foreach ($this->db->all(
                'SELECT x.classes_class_id cid, SUM(CASE WHEN ' . self::NET . ' > 0 THEN ' . self::NET . ' ELSE 0 END) due,
                        SUM(CASE WHEN p.payment_status_payment_status_id = 3 AND ' . self::NET . ' > 0 THEN ' . self::NET . ' ELSE 0 END) paid
                   FROM student_has_payments p
                   JOIN students_has_classes x ON x.students_has_class_id = p.students_has_class_students_has_class_id
                   JOIN classes c ON c.class_id = x.classes_class_id
                   ' . self::PENALTIES . '
                  WHERE c.tutor_id = :t AND p.date_added >= :f AND p.date_added < :e
                  GROUP BY x.classes_class_id',
                ['t' => $tutorId, 'f' => $this->monthStart(0)->format('Y-m-d'), 'e' => $this->monthStart(-1)->format('Y-m-d')],
            ) as $r) {
                $month[(int) $r['cid']] = ['due' => (float) $r['due'], 'paid' => (float) $r['paid']];
            }
            foreach ($this->owed($tutorId) as $o) {
                $owed[$o['class']]['amount'] = ($owed[$o['class']]['amount'] ?? 0) + $o['amount'];
                $owed[$o['class']]['students'][$o['student']] = true;
            }
        }
        $times = [];
        if ($this->hasTable('class_schedules')) {
            foreach ($this->db->all(
                'SELECT cs.classes_class_id c, cs.weekday, cs.start_time FROM class_schedules cs JOIN classes c ON c.class_id = cs.classes_class_id
                  WHERE c.tutor_id = :t AND cs.status_status_id = 1 AND (cs.valid_until IS NULL OR cs.valid_until >= CURDATE()) ORDER BY cs.weekday, cs.start_time',
                ['t' => $tutorId],
            ) as $r) {
                $times[(int) $r['c']] ??= (['', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'][(int) $r['weekday']] ?? '') . ' ' . $this->clock((string) $r['start_time']);
            }
        }
        $d28 = $this->now->modify('-27 days')->format('Y-m-d');
        $tomorrow = $this->now->modify('+1 day')->format('Y-m-d');
        $modes = ['physical' => 'Physical', 'online' => 'Online', 'hybrid' => 'Both'];
        $out = [];
        foreach ($classes as $id => $c) {
            $abs = $w = 0;
            foreach ($att as $s) {
                if ($s['class'] === $id && $s['date'] >= $d28) {
                    $abs += $s['absent'] + $s['watched'];
                    $w += $s['watched'];
                }
            }
            $mode = $modes[(string) $c['delivery_mode']] ?? 'Physical';
            $due = $month[$id]['due'] ?? 0;
            $paid = $month[$id]['paid'] ?? 0;
            $out[] = [
                'id' => $id,
                'name' => (string) $c['class_name'],
                'subtitle' => $mode . (isset($times[$id]) ? ' · ' . $times[$id] : '') . ((int) $c['status_status_id'] === 1 ? '' : ' · Inactive'),
                'year' => (string) $c['year'],
                'mode' => $mode,
                'active' => (int) $c['status_status_id'] === 1,
                'students' => (int) $c['students'],
                'newStudents' => (int) $c['new_students'],
                'attendance' => $this->rate($att, $d28, $tomorrow, $id),
                'paidPct' => $money ? ($due > 0 ? (int) round($paid / $due * 100) : null) : null,
                'paid' => round($paid),
                'due' => round($due),
                'unpaid' => round($owed[$id]['amount'] ?? 0),
                'unpaidStudents' => count($owed[$id]['students'] ?? []),
                'watched' => $abs ? (int) round($w / $abs * 100) : null,
            ];
        }
        return $out;
    }

    // ---------------------------------------------------------------- helpers

    /** First day of the month $back months ago (0 = this month, -1 = next month). */
    private function monthStart(int $back): DateTimeImmutable
    {
        return $this->now->modify('first day of this month')->setTime(0, 0)->modify(($back >= 0 ? '-' : '+') . abs($back) . ' months');
    }

    private function clock(string $time): string
    {
        return (new DateTimeImmutable('2000-01-01 ' . $time))->format('g:i A');
    }

    private function ago(string $at): string
    {
        $d = new DateTimeImmutable($at);
        $days = (int) $this->now->setTime(0, 0)->diff($d->setTime(0, 0))->days;
        return match (true) {
            $days === 0 => 'Today, ' . $d->format('g:i A'),
            $days === 1 => 'Yesterday',
            $days < 7 => $days . ' days ago',
            default => $d->format('j M Y'),
        };
    }

    /** 0771234567 / +94 77 123 4567 -> 94771234567 (for wa.me). */
    public static function waNumber(string $phone): string
    {
        $d = preg_replace('/\D+/', '', $phone) ?? '';
        if (str_starts_with($d, '00')) {
            $d = substr($d, 2);
        }
        if (strlen($d) === 10 && $d[0] === '0') {
            $d = '94' . substr($d, 1);
        } elseif (strlen($d) === 9) {
            $d = '94' . $d;
        }
        return $d;
    }

    private function hasTable(string $table): bool
    {
        return $this->schema["t:$table"] ??= (bool) $this->db->value(
            'SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = :t', ['t' => $table],
        );
    }

    private function hasColumn(string $table, string $column): bool
    {
        return $this->schema["c:$table.$column"] ??= (bool) $this->db->value(
            'SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = :t AND column_name = :c', ['t' => $table, 'c' => $column],
        );
    }
}

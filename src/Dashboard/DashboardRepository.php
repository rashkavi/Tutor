<?php
declare(strict_types=1);

namespace Enlixo\Dashboard;

use Enlixo\Core\Database;

/**
 * Every number the four dashboards show (tutor, admin, student, parent), read with prepared statements.
 *
 * Ids used by the existing app:
 *   payment_status   1 to pay · 2 slip sent, waiting for approval · 3 approved · 4 / 7 rejected · 8 paid by card
 *   attendance       2 present · 3 absent · 4 watched the recording
 *   lecture_type     1 physical · 2 online · 3 not a class day (kept out, like the timetable pages)
 */
final class DashboardRepository
{
    /** What the student has to pay for one fee row: fee − discount + late fees. */
    private const PAYABLE = "(p.amount
        - CASE WHEN p.discount_type_discount_type_id = 2 THEN IFNULL(p.discount, 0)
               WHEN p.discount_type_discount_type_id = 3 THEN p.amount * IFNULL(p.discount, 0) / 100
               ELSE 0 END
        + IFNULL((SELECT SUM(x.amount) FROM student_has_penalty_payments x
                   WHERE x.student_has_payments_student_has_payment_id = p.student_has_payment_id), 0))";

    private const PAID = 'p.payment_status_payment_status_id IN (3, 8)';

    public function __construct(private readonly Database $db)
    {
    }

    // ------------------------------------------------------------------ tutor

    public function tutorTotals(int $tutorId, string $today): array
    {
        $monthStart = substr($today, 0, 8) . '01';
        $lastMonthStart = date('Y-m-d', strtotime($monthStart . ' -1 month'));
        $row = $this->db->one(
            "SELECT
                (SELECT COUNT(*) FROM classes c WHERE c.tutor_id = :t1 AND c.status_status_id = 1) AS classes,
                (SELECT COUNT(DISTINCT sc.students_student_id) FROM students_has_classes sc
                   JOIN classes c ON c.class_id = sc.classes_class_id
                  WHERE c.tutor_id = :t2 AND sc.status_status_id = 1) AS students",
            ['t1' => $tutorId, 't2' => $tutorId],
        ) ?? [];
        $money = $this->db->one(
            'SELECT
                SUM(CASE WHEN ' . self::PAID . ' AND p.date_paid >= :m1 THEN ' . self::PAYABLE . ' END) AS income_month,
                SUM(CASE WHEN ' . self::PAID . ' AND p.date_paid >= :l1 AND p.date_paid < :m2 THEN ' . self::PAYABLE . ' END) AS income_last_month,
                SUM(CASE WHEN p.payment_status_payment_status_id = 1 THEN ' . self::PAYABLE . ' END) AS to_collect,
                SUM(p.payment_status_payment_status_id = 1) AS to_collect_count,
                SUM(p.payment_status_payment_status_id = 1 AND p.due_date IS NOT NULL AND p.due_date < :d) AS overdue_count,
                SUM(p.payment_status_payment_status_id = 2) AS to_approve_count
               FROM student_has_payments p
               JOIN students_has_classes sc ON sc.students_has_class_id = p.students_has_class_students_has_class_id
               JOIN classes c ON c.class_id = sc.classes_class_id
              WHERE c.tutor_id = :t',
            ['t' => $tutorId, 'm1' => $monthStart, 'm2' => $monthStart, 'l1' => $lastMonthStart, 'd' => $today],
        ) ?? [];
        return [
            'classes' => (int) ($row['classes'] ?? 0),
            'students' => (int) ($row['students'] ?? 0),
            'income_month' => (float) ($money['income_month'] ?? 0),
            'income_last_month' => (float) ($money['income_last_month'] ?? 0),
            'to_collect' => (float) ($money['to_collect'] ?? 0),
            'to_collect_count' => (int) ($money['to_collect_count'] ?? 0),
            'overdue_count' => (int) ($money['overdue_count'] ?? 0),
            'to_approve_count' => (int) ($money['to_approve_count'] ?? 0),
        ];
    }

    /** Class days of one tutor between two dates (inclusive), with how many students were marked. */
    public function tutorSessions(int $tutorId, string $from, string $to): array
    {
        return $this->sessions(
            'c.tutor_id = :id',
            ['id' => $tutorId, 'from' => $from, 'to' => $to],
            ', (SELECT COUNT(*) FROM attendance a WHERE a.time_table_time_table_id = tt.time_table_id
                  AND a.attendance_status_attendance_status_id IN (2, 3, 4)) AS marked
             , (SELECT COUNT(*) FROM students_has_classes sc WHERE sc.classes_class_id = c.class_id AND sc.status_status_id = 1) AS students',
        );
    }

    /** Present and absent marks per day for the tutor's classes. */
    public function tutorAttendanceByDay(int $tutorId, string $from, string $to): array
    {
        $rows = $this->db->all(
            'SELECT tt.`date` AS d,
                    SUM(a.attendance_status_attendance_status_id IN (2, 4)) AS present,
                    SUM(a.attendance_status_attendance_status_id = 3) AS absent
               FROM attendance a
               JOIN time_table tt ON tt.time_table_id = a.time_table_time_table_id
               JOIN classes c ON c.class_id = tt.classes_class_id
              WHERE c.tutor_id = :t AND tt.`date` BETWEEN :from AND :to
              GROUP BY tt.`date`',
            ['t' => $tutorId, 'from' => $from, 'to' => $to],
        );
        return $this->byDay($rows);
    }

    /** The class with the best attendance in the period (at least 5 marks, so one student can't decide it). */
    public function tutorBestAttendanceClass(int $tutorId, string $from, string $to): ?array
    {
        $row = $this->db->one(
            'SELECT c.class_id, c.class_name,
                    SUM(a.attendance_status_attendance_status_id IN (2, 4)) AS present, COUNT(*) AS marked
               FROM attendance a
               JOIN time_table tt ON tt.time_table_id = a.time_table_time_table_id
               JOIN classes c ON c.class_id = tt.classes_class_id
              WHERE c.tutor_id = :t AND tt.`date` BETWEEN :from AND :to
                AND a.attendance_status_attendance_status_id IN (2, 3, 4)
              GROUP BY c.class_id, c.class_name
             HAVING COUNT(*) >= 5
              ORDER BY SUM(a.attendance_status_attendance_status_id IN (2, 4)) / COUNT(*) DESC, COUNT(*) DESC
              LIMIT 1',
            ['t' => $tutorId, 'from' => $from, 'to' => $to],
        );
        return $row ? ['id' => (int) $row['class_id'], 'name' => (string) $row['class_name'], 'rate' => Stats::rate((int) $row['present'], (int) $row['marked'])] : null;
    }

    public function tutorRecentPayments(int $tutorId, int $limit = 6): array
    {
        return $this->payments('c.tutor_id = :id AND p.payment_status_payment_status_id <> 1', ['id' => $tutorId], 'COALESCE(p.date_paid, p.date_added) DESC, p.student_has_payment_id DESC', $limit);
    }

    // ---------------------------------------------------------------- student / parent (a list of students)

    /** @param list<int> $studentIds */
    public function studentTotals(array $studentIds, string $today): array
    {
        if (!$studentIds) {
            return ['classes' => 0, 'week_sessions' => 0, 'due' => 0.0, 'due_count' => 0, 'overdue_count' => 0, 'waiting_count' => 0];
        }
        $in = $this->in($studentIds);
        [$weekStart, $weekEnd] = Stats::week($today);
        $classes = (int) $this->db->value("SELECT COUNT(*) FROM students_has_classes sc JOIN classes c ON c.class_id = sc.classes_class_id
                                            WHERE sc.students_student_id IN ($in) AND sc.status_status_id = 1 AND c.status_status_id = 1");
        $week = (int) $this->db->value(
            "SELECT COUNT(*) FROM time_table tt JOIN students_has_classes sc ON sc.classes_class_id = tt.classes_class_id
              WHERE sc.students_student_id IN ($in) AND sc.status_status_id = 1 AND tt.time_table_status_time_table_status_id = 1
                AND tt.lecture_type_lecture_type_id <> 3 AND tt.`date` BETWEEN :a AND :b",
            ['a' => $weekStart, 'b' => $weekEnd],
        );
        $money = $this->db->one(
            'SELECT SUM(CASE WHEN p.payment_status_payment_status_id = 1 THEN ' . self::PAYABLE . " END) AS due,
                    SUM(p.payment_status_payment_status_id = 1) AS due_count,
                    SUM(p.payment_status_payment_status_id = 1 AND p.due_date IS NOT NULL AND p.due_date < :d) AS overdue_count,
                    SUM(p.payment_status_payment_status_id = 2) AS waiting_count
               FROM student_has_payments p
               JOIN students_has_classes sc ON sc.students_has_class_id = p.students_has_class_students_has_class_id
              WHERE sc.students_student_id IN ($in)",
            ['d' => $today],
        ) ?? [];
        return [
            'classes' => $classes,
            'week_sessions' => $week,
            'due' => (float) ($money['due'] ?? 0),
            'due_count' => (int) ($money['due_count'] ?? 0),
            'overdue_count' => (int) ($money['overdue_count'] ?? 0),
            'waiting_count' => (int) ($money['waiting_count'] ?? 0),
        ];
    }

    /** @param list<int> $studentIds */
    public function studentSessions(array $studentIds, string $from, string $to): array
    {
        if (!$studentIds) {
            return [];
        }
        return $this->sessions(
            'sc.students_student_id IN (' . $this->in($studentIds) . ') AND sc.status_status_id = 1',
            ['from' => $from, 'to' => $to],
            ', sc.students_student_id AS student_id, CONCAT(u.user_fname, \' \', u.user_lname) AS tutor',
            'JOIN students_has_classes sc ON sc.classes_class_id = c.class_id JOIN users u ON u.user_id = c.tutor_id',
        );
    }

    /**
     * Attended and missed class days per day. A class day that has passed with no mark counts as missed,
     * the same rule the Attendance page uses.
     * @param list<int> $studentIds
     */
    public function studentAttendanceByDay(array $studentIds, string $from, string $to, string $now): array
    {
        if (!$studentIds) {
            return [];
        }
        $in = $this->in($studentIds);
        $rows = $this->db->all(
            "SELECT tt.`date` AS d,
                    SUM(IFNULL(a.attendance_status_attendance_status_id, 0) IN (2, 4)) AS present,
                    SUM(IFNULL(a.attendance_status_attendance_status_id, 0) NOT IN (1, 2, 4)) AS absent
               FROM time_table tt
               JOIN students_has_classes sc ON sc.classes_class_id = tt.classes_class_id
               LEFT JOIN attendance a ON a.time_table_time_table_id = tt.time_table_id AND a.students_student_id = sc.students_student_id
              WHERE sc.students_student_id IN ($in) AND sc.status_status_id = 1
                AND tt.time_table_status_time_table_status_id = 1 AND tt.lecture_type_lecture_type_id <> 3
                AND tt.`date` BETWEEN :from AND :to AND TIMESTAMP(tt.`date`, tt.end_time) <= :now
              GROUP BY tt.`date`",
            ['from' => $from, 'to' => $to, 'now' => $now],
        );
        return $this->byDay($rows);
    }

    /** @param list<int> $studentIds */
    public function studentDuePayments(array $studentIds, int $limit = 6): array
    {
        if (!$studentIds) {
            return [];
        }
        return $this->payments('sc.students_student_id IN (' . $this->in($studentIds) . ') AND p.payment_status_payment_status_id = 1', [], 'p.due_date IS NULL, p.due_date, p.student_has_payment_id', $limit);
    }

    /** @param list<int> $studentIds */
    public function studentRecentPayments(array $studentIds, int $limit = 6): array
    {
        if (!$studentIds) {
            return [];
        }
        return $this->payments('sc.students_student_id IN (' . $this->in($studentIds) . ') AND p.payment_status_payment_status_id <> 1', [], 'COALESCE(p.date_paid, p.date_added) DESC, p.student_has_payment_id DESC', $limit);
    }

    /** @param list<int> $studentIds */
    public function studentClasses(array $studentIds, int $limit = 8): array
    {
        if (!$studentIds) {
            return [];
        }
        $in = $this->in($studentIds);
        return array_map(static fn (array $r) => [
            'id' => (int) $r['class_id'],
            'name' => (string) $r['class_name'],
            'cover' => (string) ($r['class_cover'] ?? ''),
            'subject' => (string) $r['subject'],
            'tutor' => trim((string) $r['tutor']),
            'student_id' => (int) $r['student_id'],
        ], $this->db->all(
            "SELECT c.class_id, c.class_name, c.class_cover, s.subject, sc.students_student_id AS student_id,
                    CONCAT(u.user_fname, ' ', u.user_lname) AS tutor
               FROM students_has_classes sc
               JOIN classes c ON c.class_id = sc.classes_class_id
               JOIN subjects s ON s.subject_id = c.subjects_subject_id
               JOIN users u ON u.user_id = c.tutor_id
              WHERE sc.students_student_id IN ($in) AND sc.status_status_id = 1 AND c.status_status_id = 1
              ORDER BY sc.students_has_class_id DESC
              LIMIT " . max(1, $limit),
        ));
    }

    /** A parent's children: name, picture, classes, fees to pay. */
    public function parentChildren(int $parentId): array
    {
        return array_map(static fn (array $r) => [
            'id' => (int) $r['student_id'],
            'name' => trim($r['first_name'] . ' ' . $r['last_name']),
            'first_name' => (string) $r['first_name'],
            'image' => (string) ($r['profile_img'] ?? ''),
            'classes' => (int) $r['classes'],
            'due' => (float) $r['due'],
            'due_count' => (int) $r['due_count'],
        ], $this->db->all(
            'SELECT s.student_id, s.profile_img, ld.first_name, ld.last_name,
                    (SELECT COUNT(*) FROM students_has_classes sc JOIN classes c ON c.class_id = sc.classes_class_id
                      WHERE sc.students_student_id = s.student_id AND sc.status_status_id = 1 AND c.status_status_id = 1) AS classes,
                    IFNULL((SELECT SUM(' . self::PAYABLE . ') FROM student_has_payments p
                      JOIN students_has_classes sc ON sc.students_has_class_id = p.students_has_class_students_has_class_id
                     WHERE sc.students_student_id = s.student_id AND p.payment_status_payment_status_id = 1), 0) AS due,
                    (SELECT COUNT(*) FROM student_has_payments p
                      JOIN students_has_classes sc ON sc.students_has_class_id = p.students_has_class_students_has_class_id
                     WHERE sc.students_student_id = s.student_id AND p.payment_status_payment_status_id = 1) AS due_count
               FROM parents_has_students ps
               JOIN students s ON s.student_id = ps.students_student_id
               JOIN login_details ld ON ld.login_details_id = s.login_details_login_details_id
              WHERE ps.parents_parent_id = :p
              GROUP BY s.student_id, s.profile_img, ld.first_name, ld.last_name
              ORDER BY ld.first_name',
            ['p' => $parentId],
        ));
    }

    // ------------------------------------------------------------------ admin

    public function adminTotals(string $today): array
    {
        $monthStart = substr($today, 0, 8) . '01';
        $lastMonthStart = date('Y-m-d', strtotime($monthStart . ' -1 month'));
        $row = $this->db->one(
            "SELECT
                (SELECT COUNT(*) FROM users WHERE user_roles_user_role_id = 2 AND status_status_id = 1) AS tutors,
                (SELECT COUNT(*) FROM users WHERE user_roles_user_role_id = 2 AND status_status_id = 1 AND user_reg_date >= :m1) AS tutors_new,
                (SELECT COUNT(DISTINCT sc.students_student_id) FROM students_has_classes sc WHERE sc.status_status_id = 1) AS students,
                (SELECT COUNT(*) FROM classes WHERE status_status_id = 1) AS classes,
                (SELECT COUNT(*) FROM requested_users WHERE request_status_request_status_id = 1) AS requests,
                (SELECT COUNT(*) FROM users_has_payment_plans_payments WHERE payment_status_payment_status_id = 2) AS to_approve,
                (SELECT IFNULL(SUM(paid_amount), 0) FROM users_has_payment_plans_payments
                  WHERE payment_status_payment_status_id IN (3, 8) AND date_paid >= :m2) AS income_month,
                (SELECT IFNULL(SUM(paid_amount), 0) FROM users_has_payment_plans_payments
                  WHERE payment_status_payment_status_id IN (3, 8) AND date_paid >= :l AND date_paid < :m3) AS income_last_month",
            ['m1' => $monthStart, 'm2' => $monthStart, 'm3' => $monthStart, 'l' => $lastMonthStart],
        ) ?? [];
        return [
            'tutors' => (int) ($row['tutors'] ?? 0),
            'tutors_new' => (int) ($row['tutors_new'] ?? 0),
            'students' => (int) ($row['students'] ?? 0),
            'classes' => (int) ($row['classes'] ?? 0),
            'requests' => (int) ($row['requests'] ?? 0),
            'to_approve' => (int) ($row['to_approve'] ?? 0),
            'income_month' => (float) ($row['income_month'] ?? 0),
            'income_last_month' => (float) ($row['income_last_month'] ?? 0),
        ];
    }

    /** Active tutors per package, in package order. */
    public function adminTutorsByPlan(): array
    {
        return array_map(static fn (array $r) => ['plan' => (string) $r['payment_plan'], 'tutors' => (int) $r['tutors']], $this->db->all(
            'SELECT pp.payment_plan, COUNT(u.user_id) AS tutors
               FROM payment_plans pp
               LEFT JOIN users_has_payment_plans up ON up.payment_plans_payment_plan_id = pp.payment_plan_id
               LEFT JOIN users u ON u.user_id = up.users_user_id AND u.status_status_id = 1 AND u.user_roles_user_role_id = 2
              GROUP BY pp.payment_plan_id, pp.payment_plan
              ORDER BY pp.payment_plan_id',
        ));
    }

    /** New tutors per month, keyed 'YYYY-MM'. */
    public function adminTutorsByMonth(string $from): array
    {
        $out = [];
        foreach ($this->db->all(
            "SELECT DATE_FORMAT(user_reg_date, '%Y-%m') AS m, COUNT(*) AS n FROM users
              WHERE user_roles_user_role_id = 2 AND user_reg_date >= :f GROUP BY m",
            ['f' => $from],
        ) as $r) {
            $out[(string) $r['m']] = (int) $r['n'];
        }
        return $out;
    }

    public function adminRecentRequests(int $limit = 5): array
    {
        return array_map(static fn (array $r) => [
            'id' => (int) $r['requested_user_id'],
            'name' => trim($r['user_fname'] . ' ' . $r['user_lname']),
            'email' => (string) ($r['email'] ?? ''),
            'date' => (string) $r['requested_date_time'],
            'status_id' => (int) $r['request_status_id'],
            'status' => (string) $r['request_status'],
        ], $this->db->all(
            'SELECT r.requested_user_id, r.user_fname, r.user_lname, r.email, r.requested_date_time, s.request_status_id, s.request_status
               FROM requested_users r JOIN request_status s ON s.request_status_id = r.request_status_request_status_id
              ORDER BY r.request_status_request_status_id = 1 DESC, r.requested_user_id DESC
              LIMIT ' . max(1, $limit),
        ));
    }

    public function adminRecentSubscriptionPayments(int $limit = 6): array
    {
        return array_map(static fn (array $r) => [
            'id' => (int) $r['users_has_payment_plans_payment_id'],
            'name' => trim($r['user_fname'] . ' ' . $r['user_lname']),
            'plan' => (string) $r['payment_plan'],
            'amount' => (float) $r['paid_amount'],
            'date' => (string) ($r['date_paid'] ?? ''),
            'status_id' => (int) $r['payment_status_id'],
            'status' => (string) $r['payment_status'],
        ], $this->db->all(
            'SELECT p.users_has_payment_plans_payment_id, p.paid_amount, p.date_paid, u.user_fname, u.user_lname, pp.payment_plan,
                    ps.payment_status_id, ps.payment_status
               FROM users_has_payment_plans_payments p
               JOIN users_has_payment_plans up ON up.users_has_payment_plan_id = p.users_has_payment_plans_users_has_payment_plan_id
               JOIN payment_plans pp ON pp.payment_plan_id = up.payment_plans_payment_plan_id
               JOIN users u ON u.user_id = up.users_user_id
               JOIN payment_status ps ON ps.payment_status_id = p.payment_status_payment_status_id
              ORDER BY p.payment_status_payment_status_id = 2 DESC, p.users_has_payment_plans_payment_id DESC
              LIMIT ' . max(1, $limit),
        ));
    }

    // ---------------------------------------------------------------- shared

    private function sessions(string $where, array $params, string $extraColumns = '', string $extraJoins = ''): array
    {
        $rows = $this->db->all(
            "SELECT tt.time_table_id, tt.`date`, tt.start_time, tt.end_time, tt.lecture_title, tt.lecture_type_lecture_type_id AS lt,
                    c.class_id, c.class_name $extraColumns
               FROM time_table tt
               JOIN classes c ON c.class_id = tt.classes_class_id
               $extraJoins
              WHERE $where AND tt.time_table_status_time_table_status_id = 1 AND tt.lecture_type_lecture_type_id <> 3
                AND tt.`date` BETWEEN :from AND :to
              ORDER BY tt.`date`, tt.start_time",
            $params,
        );
        return array_map(static fn (array $r) => [
            'id' => (int) $r['time_table_id'],
            'date' => (string) $r['date'],
            'start' => substr((string) $r['start_time'], 0, 5),
            'end' => substr((string) $r['end_time'], 0, 5),
            'title' => (string) ($r['lecture_title'] ?? ''),
            'online' => (int) $r['lt'] === 2,
            'class_id' => (int) $r['class_id'],
            'class' => (string) $r['class_name'],
            'marked' => isset($r['marked']) ? (int) $r['marked'] : null,
            'students' => isset($r['students']) ? (int) $r['students'] : null,
            'student_id' => isset($r['student_id']) ? (int) $r['student_id'] : null,
            'tutor' => isset($r['tutor']) ? trim((string) $r['tutor']) : null,
        ], $rows);
    }

    private function payments(string $where, array $params, string $order, int $limit): array
    {
        $rows = $this->db->all(
            'SELECT p.student_has_payment_id, p.amount, p.due_date, p.date_paid, ' . self::PAYABLE . " AS payable,
                    ps.payment_status_id, ps.payment_status, pm.payment_method,
                    c.class_name, s.student_id, s.profile_img, ld.first_name, ld.last_name
               FROM student_has_payments p
               JOIN payment_status ps ON ps.payment_status_id = p.payment_status_payment_status_id
               LEFT JOIN payment_method pm ON pm.payment_method_id = p.payment_method_payment_method_id
               JOIN students_has_classes sc ON sc.students_has_class_id = p.students_has_class_students_has_class_id
               JOIN classes c ON c.class_id = sc.classes_class_id
               JOIN students s ON s.student_id = sc.students_student_id
               JOIN login_details ld ON ld.login_details_id = s.login_details_login_details_id
              WHERE $where
              ORDER BY $order
              LIMIT " . max(1, $limit),
            $params,
        );
        return array_map(static fn (array $r) => [
            'id' => (int) $r['student_has_payment_id'],
            'student_id' => (int) $r['student_id'],
            'student' => trim($r['first_name'] . ' ' . $r['last_name']),
            'image' => (string) ($r['profile_img'] ?? ''),
            'class' => (string) $r['class_name'],
            'amount' => (float) $r['payable'],
            'due_date' => $r['due_date'] ? (string) $r['due_date'] : null,
            'date_paid' => $r['date_paid'] ? (string) $r['date_paid'] : null,
            'method' => $r['payment_method'] !== null ? (string) $r['payment_method'] : null,
            'status_id' => (int) $r['payment_status_id'],
            'status' => (string) $r['payment_status'],
        ], $rows);
    }

    /** @return array<string, array{present:int, absent:int}> keyed by date */
    private function byDay(array $rows): array
    {
        $out = [];
        foreach ($rows as $r) {
            $out[(string) $r['d']] = ['present' => (int) $r['present'], 'absent' => (int) $r['absent']];
        }
        return $out;
    }

    /** Ids are cast to int, so the list is safe to place in the SQL. */
    private function in(array $ids): string
    {
        return implode(',', array_map('intval', $ids)) ?: '0';
    }
}

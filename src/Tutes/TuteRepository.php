<?php
declare(strict_types=1);

namespace Enlixo\Tutes;

use Enlixo\Core\Database;

/**
 * Tutes and their deliveries, always scoped to the tutor who owns the class.
 * Rules (who gets a tute, delivery steps) are in TuteRules; this class only reads and writes.
 */
final class TuteRepository
{
    public function __construct(private readonly Database $db)
    {
    }

    public function findClass(int $tutorId, int $classId): ?array
    {
        return $this->db->one(
            'SELECT c.class_id, c.class_name, c.is_free, c.status_status_id, s.subject
               FROM classes c LEFT JOIN subjects s ON s.subject_id = c.subjects_subject_id
              WHERE c.class_id = :c AND c.tutor_id = :t',
            ['c' => $classId, 't' => $tutorId],
        );
    }

    /** The tute with its class, or null when it is not this tutor's. */
    public function findTute(int $tutorId, int $tuteId): ?array
    {
        return $this->db->one(
            'SELECT ct.class_tute_id, ct.classes_class_id, ct.tute_title, ct.tute_description, ct.payment_month, ct.tute_types_tute_type_id,
                    ct.courier_name, ct.dispatch_on, ct.student_note, ct.date_added, c.class_name, c.is_free
               FROM class_tutes ct JOIN classes c ON c.class_id = ct.classes_class_id
              WHERE ct.class_tute_id = :id AND c.tutor_id = :t',
            ['id' => $tuteId, 't' => $tutorId],
        );
    }

    /** SQL (and its parameters) for "this enrolment gets the tute": active, and the month's fee paid when a month is set. */
    private function whoGets(?string $month, bool $free): array
    {
        if ($month === null || $free) {
            return ['', []];
        }
        return [
            ' AND EXISTS (SELECT 1 FROM student_has_payments p
                          WHERE p.students_has_class_students_has_class_id = sc.students_has_class_id
                            AND p.payment_status_payment_status_id IN (' . implode(',', TuteRules::PAID) . ')
                            AND DATE_FORMAT(p.date_added, \'%Y-%m\') = :month)',
            ['month' => $month],
        ];
    }

    /** How many active students get it: everyone, and for each month option those who paid that month. */
    public function audience(int $classId, bool $free, array $months): array
    {
        $all = (int) $this->db->value('SELECT COUNT(*) FROM students_has_classes sc WHERE sc.classes_class_id = :c AND sc.status_status_id = 1', ['c' => $classId]);
        $paid = [];
        foreach ($months as $m) {
            [$sql, $p] = $this->whoGets($m, $free);
            $paid[$m] = (int) $this->db->value('SELECT COUNT(*) FROM students_has_classes sc WHERE sc.classes_class_id = :c AND sc.status_status_id = 1' . $sql, ['c' => $classId] + $p);
        }
        return ['all' => $all, 'paid' => $paid];
    }

    /**
     * Saves a tute in one go: the tute, its files or parcel items, and a delivery for every student who gets a posted tute.
     * @param list<array{stored:string, name:string}> $files already moved into the uploads folder
     * @return array{id:int, deliveries:int}
     */
    public function create(int $classId, int $userId, array $t, array $files, bool $free, string $now): array
    {
        return $this->db->transaction(function () use ($classId, $userId, $t, $files, $free, $now) {
            $id = $this->db->insert(
                'INSERT INTO class_tutes (classes_class_id, tute_title, tute_description, payment_month, tute_types_tute_type_id, date_added,
                                          courier_name, dispatch_on, student_note, added_by)
                 VALUES (:c, :title, :d, :m, :type, :now, :courier, :dispatch, :note, :u)',
                ['c' => $classId, 'title' => $t['title'], 'd' => $t['description'], 'm' => $t['payment_month'], 'type' => $t['type'], 'now' => $now,
                    'courier' => $t['courier_name'], 'dispatch' => $t['dispatch_on'], 'note' => $t['student_note'], 'u' => $userId],
            );
            foreach ($files as $f) {
                $this->db->execute(
                    'INSERT INTO class_tutes_has_attachments (class_tutes_class_tute_id, attachment_name, attachment, date_added) VALUES (:t, :n, :a, :now)',
                    ['t' => $id, 'n' => mb_substr($f['name'], 0, 100), 'a' => $f['stored'], 'now' => $now],
                );
            }
            foreach ($t['items'] as $item) {
                $this->db->execute(
                    'INSERT INTO class_tutes_has_attachments (class_tutes_class_tute_id, attachment_name, attachment, date_added) VALUES (:t, :n, NULL, :now)',
                    ['t' => $id, 'n' => $item, 'now' => $now],
                );
            }
            $made = $t['type'] === TuteRules::TYPE_COURIER ? $this->addMissingDeliveries($id, $classId, $t['payment_month'], $free) : 0;
            return ['id' => $id, 'deliveries' => $made];
        });
    }

    /**
     * Adds a delivery for every student who gets the tute and has none yet (students who paid after the tute was added,
     * or joined the class later). Copies the student's latest address. Returns how many were added.
     */
    public function addMissingDeliveries(int $tuteId, int $classId, ?string $month, bool $free): int
    {
        [$sql, $p] = $this->whoGets($month, $free);
        return $this->db->execute(
            "INSERT INTO tute_deliveries (class_tutes_class_tute_id, students_student_id, recipient_name, phone,
                                          address_line_1, address_line_2, city, district, postal_code)
             SELECT :tute, s.student_id, LEFT(TRIM(CONCAT(ld.first_name, ' ', ld.last_name)), 150), LEFT(COALESCE(NULLIF(s.mobile, ''), ld.whatsapp), 20),
                    a.address_line_1, a.address_line_2, ci.city, di.district, a.postal_code
               FROM students_has_classes sc
               JOIN students s ON s.student_id = sc.students_student_id
               JOIN login_details ld ON ld.login_details_id = s.login_details_login_details_id
               LEFT JOIN student_has_address a ON a.student_has_address_id = (SELECT MAX(a2.student_has_address_id) FROM student_has_address a2 WHERE a2.students_student_id = s.student_id)
               LEFT JOIN city ci ON ci.city_id = a.city_city_id
               LEFT JOIN district di ON di.district_id = ci.district_district_id
              WHERE sc.classes_class_id = :class AND sc.status_status_id = 1 $sql
                AND NOT EXISTS (SELECT 1 FROM tute_deliveries d WHERE d.class_tutes_class_tute_id = :tute2 AND d.students_student_id = s.student_id)",
            ['tute' => $tuteId, 'tute2' => $tuteId, 'class' => $classId] + $p,
        );
    }

    /** Every delivery of a tute, with the student's name, for the deliveries page. */
    public function deliveries(int $tuteId): array
    {
        return array_map(static fn (array $r) => [
            'id' => (int) $r['tute_delivery_id'],
            'student_id' => (int) $r['students_student_id'],
            'name' => (string) ($r['recipient_name'] ?: trim($r['first_name'] . ' ' . $r['last_name'])),
            'student' => trim($r['first_name'] . ' ' . $r['last_name']),
            'image' => (string) ($r['profile_img'] ?? ''),
            'phone' => (string) ($r['phone'] ?? ''),
            'address_line_1' => (string) ($r['address_line_1'] ?? ''),
            'address_line_2' => (string) ($r['address_line_2'] ?? ''),
            'city' => (string) ($r['city'] ?? ''),
            'district' => (string) ($r['district'] ?? ''),
            'postal_code' => (string) ($r['postal_code'] ?? ''),
            'has_address' => trim((string) $r['address_line_1']) !== '',
            'status' => (string) $r['status'],
            'courier_name' => (string) ($r['courier_name'] ?? ''),
            'tracking_no' => (string) ($r['tracking_no'] ?? ''),
            'dispatched_on' => $r['dispatched_on'],
            'delivered_on' => $r['delivered_on'],
            'note' => (string) ($r['note'] ?? ''),
        ], $this->db->all(
            'SELECT d.*, ld.first_name, ld.last_name, s.profile_img
               FROM tute_deliveries d
               JOIN students s ON s.student_id = d.students_student_id
               JOIN login_details ld ON ld.login_details_id = s.login_details_login_details_id
              WHERE d.class_tutes_class_tute_id = :t
              ORDER BY FIELD(d.status, \'pending\', \'packed\', \'dispatched\', \'returned\', \'delivered\'), ld.first_name, ld.last_name',
            ['t' => $tuteId],
        ));
    }

    /** Changes the ticked deliveries of one tute. Ids from another tute are ignored. Returns how many changed. */
    public function updateDeliveries(int $tuteId, array $ids, array $set, int $userId, string $today): int
    {
        $cols = [];
        $params = ['t' => $tuteId, 'u' => $userId];
        foreach ($set as $k => $v) {
            if ($k === 'dispatched_on_if_empty') {
                $cols[] = 'dispatched_on = COALESCE(dispatched_on, :dif)';
                $params['dif'] = $v;
                continue;
            }
            if (!in_array($k, ['status', 'courier_name', 'tracking_no', 'note', 'recipient_name', 'phone', 'address_line_1', 'address_line_2', 'city', 'district', 'postal_code', 'dispatched_on', 'delivered_on'], true)) {
                continue;
            }
            $cols[] = "$k = :$k";
            $params[$k] = $v;
        }
        if (!$cols) {
            return 0;
        }
        $in = implode(',', array_map('intval', $ids));
        return $this->db->execute('UPDATE tute_deliveries SET ' . implode(', ', $cols) . ', updated_by = :u
                                    WHERE class_tutes_class_tute_id = :t AND tute_delivery_id IN (' . $in . ')', $params);
    }

    /** The courier tutes of one class, newest first, with how far their deliveries are. */
    public function courierTutes(int $classId): array
    {
        return array_map(static fn (array $r) => [
            'id' => (int) $r['class_tute_id'],
            'title' => (string) $r['tute_title'],
            'month' => TuteRules::monthLabel($r['payment_month']),
            'added' => (string) $r['date_added'],
            'total' => (int) $r['total'],
            'delivered' => (int) $r['delivered'],
            'sent' => (int) $r['sent'],
            'waiting' => (int) $r['waiting'],
        ], $this->db->all(
            "SELECT ct.class_tute_id, ct.tute_title, ct.payment_month, ct.date_added,
                    COUNT(d.tute_delivery_id) total,
                    SUM(d.status = 'delivered') delivered,
                    SUM(d.status = 'dispatched') sent,
                    SUM(d.status IN ('pending', 'packed')) waiting
               FROM class_tutes ct LEFT JOIN tute_deliveries d ON d.class_tutes_class_tute_id = ct.class_tute_id
              WHERE ct.classes_class_id = :c AND ct.tute_types_tute_type_id = :type
              GROUP BY ct.class_tute_id, ct.tute_title, ct.payment_month, ct.date_added
              ORDER BY ct.class_tute_id DESC",
            ['c' => $classId, 'type' => TuteRules::TYPE_COURIER],
        ));
    }

    /** A student's posted tutes, for the student portal. */
    public function forStudent(int $studentId): array
    {
        return array_map(static fn (array $r) => [
            'id' => (int) $r['tute_delivery_id'],
            'tute' => (string) $r['tute_title'],
            'class' => (string) $r['class_name'],
            'status' => (string) $r['status'],
            'step' => TuteRules::STATUSES[$r['status']] ?? $r['status'],
            'courier_name' => (string) ($r['courier_name'] ?: ($r['tute_courier'] ?? '')),
            'tracking_no' => (string) ($r['tracking_no'] ?? ''),
            'tracking_url' => TuteRules::trackingUrl($r['courier_name'] ?: $r['tute_courier']),
            'dispatched_on' => $r['dispatched_on'],
            'delivered_on' => $r['delivered_on'],
            'expected_on' => $r['dispatch_on'],
            'note' => (string) ($r['student_note'] ?? ''),
            'address' => trim(implode(', ', array_filter([$r['address_line_1'], $r['address_line_2'], $r['city']]))),
        ], $this->db->all(
            'SELECT d.*, ct.tute_title, ct.courier_name AS tute_courier, ct.dispatch_on, ct.student_note, c.class_name
               FROM tute_deliveries d
               JOIN class_tutes ct ON ct.class_tute_id = d.class_tutes_class_tute_id
               JOIN classes c ON c.class_id = ct.classes_class_id
              WHERE d.students_student_id = :s
              ORDER BY d.tute_delivery_id DESC LIMIT 50',
            ['s' => $studentId],
        ));
    }
}

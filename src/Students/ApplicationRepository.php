<?php
declare(strict_types=1);

namespace Enlixo\Students;

use Enlixo\Core\Database;
use Enlixo\Support\ListQuery;
use Enlixo\Support\Paginated;

/** Requested Students: applications, their history and the tutor's public application link. */
final class ApplicationRepository
{
    public const STATUSES = ['pending', 'in_review', 'on_hold', 'approved', 'rejected'];
    public const FILTERS = ['status', 'class'];
    public const SORTS = ['name', 'date', 'status'];

    public function __construct(private readonly Database $db)
    {
    }

    // ---------- link ----------

    /** @return array{token:string, is_open:bool} */
    public function link(int $tutorId): array
    {
        $row = $this->db->one('SELECT token, is_open FROM tutor_application_links WHERE tutor_id = :t', ['t' => $tutorId]);
        if (!$row) {
            $token = self::newToken();
            $this->db->execute('INSERT INTO tutor_application_links (tutor_id, token) VALUES (:t, :k)', ['t' => $tutorId, 'k' => $token]);
            return ['token' => $token, 'is_open' => true];
        }
        return ['token' => (string) $row['token'], 'is_open' => (bool) $row['is_open']];
    }

    public function setLinkOpen(int $tutorId, bool $open): void
    {
        $this->link($tutorId);
        $this->db->execute('UPDATE tutor_application_links SET is_open = :o WHERE tutor_id = :t', ['o' => $open ? 1 : 0, 't' => $tutorId]);
    }

    /** A new link; the old one stops working. */
    public function renewLink(int $tutorId): string
    {
        $this->link($tutorId);
        $token = self::newToken();
        $this->db->execute('UPDATE tutor_application_links SET token = :k, created_at = NOW() WHERE tutor_id = :t', ['k' => $token, 't' => $tutorId]);
        return $token;
    }

    /** @return array{tutor_id:int, is_open:bool}|null */
    public function findLink(string $token): ?array
    {
        if (!preg_match('/^[A-Za-z0-9]{16,32}$/', $token)) {
            return null;
        }
        $row = $this->db->one('SELECT tutor_id, is_open FROM tutor_application_links WHERE token = :k', ['k' => $token]);
        return $row ? ['tutor_id' => (int) $row['tutor_id'], 'is_open' => (bool) $row['is_open']] : null;
    }

    private static function newToken(): string
    {
        return substr(str_replace(['+', '/', '='], '', base64_encode(random_bytes(24))), 0, 20);
    }

    // ---------- applications ----------

    public function create(int $tutorId, ?int $classId, array $values, ?string $photo, ?string $message, ?string $ip, string $now): array
    {
        $id = $this->db->insert(
            'INSERT INTO student_applications (tutor_id, classes_class_id, status, first_name, last_name, email, whatsapp, data, photo, message, ip, created_at, updated_at)
             VALUES (:t, :c, \'pending\', :f, :l, :e, :w, :d, :p, :m, :ip, :now, :now2)',
            [
                't' => $tutorId, 'c' => $classId, 'f' => $values['first_name'] ?? '', 'l' => $values['last_name'] ?? '',
                'e' => ($values['email'] ?? '') ?: null, 'w' => ($values['whatsapp'] ?? '') ?: null,
                'd' => json_encode($values, JSON_UNESCAPED_UNICODE), 'p' => $photo, 'm' => $message ?: null, 'ip' => $ip, 'now' => $now, 'now2' => $now,
            ],
        );
        $ref = sprintf('APP%s%05d', substr($now, 2, 2), $id);
        $this->db->execute('UPDATE student_applications SET ref_no = :r WHERE application_id = :id', ['r' => $ref, 'id' => $id]);
        $this->addHistory($id, null, 'Received', $message ? 'Applied online. Message: ' . mb_substr($message, 0, 400) : 'Applied online', $now);
        return ['id' => $id, 'ref' => $ref];
    }

    /** Applications sent from this IP in the last hour (simple flood limit for the public link). */
    public function recentFromIp(string $ip, string $since): int
    {
        return (int) $this->db->value('SELECT COUNT(*) FROM student_applications WHERE ip = :ip AND created_at >= :s', ['ip' => $ip, 's' => $since]);
    }

    /** An open application from the same WhatsApp number to the same class (so a double submit doesn't make two). */
    public function openDuplicate(int $tutorId, ?int $classId, string $whatsapp): ?array
    {
        return $this->db->one(
            "SELECT application_id id, ref_no ref FROM student_applications
              WHERE tutor_id = :t AND whatsapp = :w AND (classes_class_id <=> :c) AND status IN ('pending','in_review','on_hold') LIMIT 1",
            ['t' => $tutorId, 'w' => $whatsapp, 'c' => $classId],
        );
    }

    public function forTutor(int $tutorId, ListQuery $q): Paginated
    {
        [$where, $params] = $q->where(
            ['a.ref_no', 'a.first_name', 'a.last_name', "CONCAT(a.first_name, ' ', a.last_name)", 'a.email', 'a.whatsapp'],
            ['status' => 'a.status', 'class' => 'a.classes_class_id'],
            ['a.tutor_id = :tutor'],
            ['tutor' => $tutorId],
        );
        $from = 'FROM student_applications a LEFT JOIN classes c ON c.class_id = a.classes_class_id';
        $total = (int) $this->db->value("SELECT COUNT(*) $from $where", $params);
        $rows = $this->db->all(
            "SELECT a.application_id id, a.ref_no ref, a.first_name, a.last_name, a.email, a.whatsapp, a.photo, a.status, a.status_note,
                    a.created_at, a.students_student_id student_id, c.class_name
             $from $where " . ($q->sort === null ? 'ORDER BY a.created_at DESC' : $q->orderBy(['name' => 'a.first_name', 'date' => 'a.created_at', 'status' => 'a.status'], 'a.created_at')) . ', a.application_id DESC ' . $q->limit(),
            $params,
        );
        return new Paginated($rows, $total, $q);
    }

    /** Numbers for the stat cards: per status, this month vs last month, and applications per day for the last 7 days. */
    public function stats(int $tutorId, string $today): array
    {
        $by = array_fill_keys(self::STATUSES, 0);
        foreach ($this->db->all('SELECT status, COUNT(*) n FROM student_applications WHERE tutor_id = :t GROUP BY status', ['t' => $tutorId]) as $r) {
            $by[$r['status']] = (int) $r['n'];
        }
        $days = [];
        $start = (new \DateTimeImmutable($today))->modify('-6 days');
        for ($i = 0; $i < 7; $i++) {
            $days[$start->modify("+$i days")->format('Y-m-d')] = 0;
        }
        foreach ($this->db->all('SELECT DATE(created_at) d, COUNT(*) n FROM student_applications WHERE tutor_id = :t AND created_at >= :s GROUP BY DATE(created_at)', ['t' => $tutorId, 's' => $start->format('Y-m-d')]) as $r) {
            if (isset($days[$r['d']])) {
                $days[$r['d']] = (int) $r['n'];
            }
        }
        $month = substr($today, 0, 7);
        $prev = (new \DateTimeImmutable($month . '-01'))->modify('-1 month')->format('Y-m');
        $m = $this->db->one(
            "SELECT SUM(DATE_FORMAT(created_at, '%Y-%m') = :m) this_month, SUM(DATE_FORMAT(created_at, '%Y-%m') = :p) last_month FROM student_applications WHERE tutor_id = :t",
            ['m' => $month, 'p' => $prev, 't' => $tutorId],
        ) ?? [];
        return [
            'total' => array_sum($by),
            'by_status' => $by,
            'this_month' => (int) ($m['this_month'] ?? 0),
            'last_month' => (int) ($m['last_month'] ?? 0),
            'days' => array_map(static fn ($d, $n) => ['date' => $d, 'count' => $n], array_keys($days), $days),
        ];
    }

    public function find(int $tutorId, int $id): ?array
    {
        $row = $this->db->one(
            "SELECT a.*, c.class_name, CONCAT(r.user_fname, ' ', r.user_lname) reviewed_by_name, CONCAT(d.user_fname, ' ', d.user_lname) decided_by_name
               FROM student_applications a
               LEFT JOIN classes c ON c.class_id = a.classes_class_id
               LEFT JOIN users r ON r.user_id = a.reviewed_by
               LEFT JOIN users d ON d.user_id = a.decided_by
              WHERE a.application_id = :id AND a.tutor_id = :t",
            ['id' => $id, 't' => $tutorId],
        );
        if (!$row) {
            return null;
        }
        $row['data'] = json_decode((string) $row['data'], true) ?: [];
        return $row;
    }

    public function setStatus(int $id, string $status, ?string $note, int $userId, string $now, ?int $studentId = null): void
    {
        $decided = in_array($status, ['approved', 'rejected'], true);
        $this->db->execute(
            'UPDATE student_applications SET status = :s, status_note = :n, updated_at = :now,
                    reviewed_by = COALESCE(reviewed_by, :u), reviewed_at = COALESCE(reviewed_at, :now2),
                    decided_by = ' . ($decided ? ':u2' : 'NULL') . ', decided_at = ' . ($decided ? ':now3' : 'NULL') . ',
                    students_student_id = COALESCE(:st, students_student_id)
              WHERE application_id = :id',
            ['s' => $status, 'n' => $note, 'now' => $now, 'u' => $userId, 'now2' => $now, 'st' => $studentId, 'id' => $id]
                + ($decided ? ['u2' => $userId, 'now3' => $now] : []),
        );
    }

    public function addHistory(int $id, ?int $userId, string $action, ?string $remark, string $now): void
    {
        $this->db->execute(
            'INSERT INTO student_application_history (application_id, user_id, action, remark, created_at) VALUES (:a, :u, :act, :r, :now)',
            ['a' => $id, 'u' => $userId, 'act' => $action, 'r' => $remark !== null ? mb_substr($remark, 0, 500) : null, 'now' => $now],
        );
    }

    public function history(int $id): array
    {
        return $this->db->all(
            "SELECT h.id, h.action, h.remark, h.created_at, h.user_id, CONCAT(u.user_fname, ' ', u.user_lname) user_name
               FROM student_application_history h LEFT JOIN users u ON u.user_id = h.user_id
              WHERE h.application_id = :a ORDER BY h.created_at DESC, h.id DESC",
            ['a' => $id],
        );
    }

    /** Pending + in review, for the side menu badge. */
    public function waitingCount(int $tutorId): int
    {
        return (int) $this->db->value("SELECT COUNT(*) FROM student_applications WHERE tutor_id = :t AND status IN ('pending','in_review')", ['t' => $tutorId]);
    }
}

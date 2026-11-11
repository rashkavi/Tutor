<?php
declare(strict_types=1);

namespace Enlixo\Recordings;

use Enlixo\Core\Database;

final class PdoRecordingRepository implements RecordingRepository
{
    private const STATUS_ACTIVE = 1;

    public function __construct(private readonly Database $db)
    {
    }

    public function find(string $kind, int $id): ?RecordingInfo
    {
        // NOTE: the existing `duration` columns are treated as MINUTES. Confirm with the live app.
        $row = $kind === 'lecture'
            ? $this->db->one(
                'SELECT lr.lecture_recording_id AS id, lr.recording_title AS title, lr.duration, lr.unlock_at, c.class_id, c.tutor_id
                   FROM lecture_recording lr
                   JOIN time_table tt ON tt.time_table_id = lr.time_table_time_table_id
                   JOIN classes c ON c.class_id = tt.classes_class_id
                  WHERE lr.lecture_recording_id = :id',
                ['id' => $id],
            )
            : $this->db->one(
                'SELECT pr.pre_recording_id AS id, pr.title, pr.duration, pr.unlock_at, c.class_id, c.tutor_id
                   FROM pre_recordings pr JOIN classes c ON c.class_id = pr.classes_class_id
                  WHERE pr.pre_recording_id = :id',
                ['id' => $id],
            );

        return $row ? new RecordingInfo($kind, (int) $row['id'], (int) $row['class_id'], (int) $row['tutor_id'], $row['title'], (int) $row['duration'] * 60,
            !empty($row['unlock_at']) ? new \DateTimeImmutable($row['unlock_at']) : null) : null;
    }

    public function studentEnrolled(int $studentId, int $classId): bool
    {
        return (bool) $this->db->value(
            'SELECT 1 FROM students_has_classes WHERE students_student_id = :s AND classes_class_id = :c AND status_status_id = :a',
            ['s' => $studentId, 'c' => $classId, 'a' => self::STATUS_ACTIVE],
        );
    }

    public function rule(string $kind, int $id): ?AccessRule
    {
        $r = $this->db->one('SELECT * FROM recording_access_rules WHERE recording_kind = :k AND recording_id = :id', ['k' => $kind, 'id' => $id]);
        if ($r === null) {
            // Fall back to the old lecture_recording.expire_date column so existing expiry dates keep working.
            $legacy = $kind === 'lecture' ? $this->db->value('SELECT expire_date FROM lecture_recording WHERE lecture_recording_id = :id', ['id' => $id]) : null;
            return $legacy ? new AccessRule(AccessRule::MULTIPLIER, null, null, null, new \DateTimeImmutable($legacy . ' 23:59:59')) : null;
        }
        return new AccessRule(
            $r['limit_mode'],
            $r['multiplier'] !== null ? (float) $r['multiplier'] : null,
            $r['fixed_minutes'] !== null ? (int) $r['fixed_minutes'] : null,
            $r['available_from'] ? new \DateTimeImmutable($r['available_from']) : null,
            $r['expires_at'] ? new \DateTimeImmutable($r['expires_at']) : null,
        );
    }

    public function override(int $studentId, string $kind, int $id): ?StudentOverride
    {
        $r = $this->db->one(
            'SELECT extra_minutes, expires_at FROM recording_student_overrides WHERE students_student_id = :s AND recording_kind = :k AND recording_id = :id',
            ['s' => $studentId, 'k' => $kind, 'id' => $id],
        );
        return $r ? new StudentOverride((int) $r['extra_minutes'], $r['expires_at'] ? new \DateTimeImmutable($r['expires_at']) : null) : null;
    }

    public function usedSeconds(int $studentId, string $kind, int $id): int
    {
        return (int) $this->db->value(
            'SELECT COALESCE(SUM(watched_seconds), 0) FROM recording_watch_sessions WHERE students_student_id = :s AND recording_kind = :k AND recording_id = :id',
            ['s' => $studentId, 'k' => $kind, 'id' => $id],
        );
    }

    public function createSession(string $token, int $studentId, string $kind, int $id, \DateTimeImmutable $now, ?string $ip, ?string $userAgent): void
    {
        $this->db->execute(
            'INSERT INTO recording_watch_sessions (session_token, students_student_id, recording_kind, recording_id, started_at, last_heartbeat_at, ip_address, user_agent)
             VALUES (:t, :s, :k, :id, :now, :now2, :ip, :ua)',
            ['t' => $token, 's' => $studentId, 'k' => $kind, 'id' => $id, 'now' => $now->format('Y-m-d H:i:s'), 'now2' => $now->format('Y-m-d H:i:s'), 'ip' => $ip, 'ua' => $userAgent],
        );
    }

    public function findSession(string $token): ?WatchSession
    {
        $r = $this->db->one('SELECT * FROM recording_watch_sessions WHERE session_token = :t', ['t' => $token]);
        return $r ? new WatchSession((int) $r['recording_watch_session_id'], $r['session_token'], (int) $r['students_student_id'], $r['recording_kind'], (int) $r['recording_id'], new \DateTimeImmutable($r['last_heartbeat_at']), (int) $r['watched_seconds']) : null;
    }

    public function addWatchTime(int $sessionId, int $seconds, int $positionSeconds, \DateTimeImmutable $now): void
    {
        $this->db->execute(
            'UPDATE recording_watch_sessions SET watched_seconds = watched_seconds + :sec, last_position_seconds = :pos, last_heartbeat_at = :now WHERE recording_watch_session_id = :id',
            ['sec' => $seconds, 'pos' => $positionSeconds, 'now' => $now->format('Y-m-d H:i:s'), 'id' => $sessionId],
        );
    }
}

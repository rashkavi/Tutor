<?php
declare(strict_types=1);

namespace Enlixo\Scheduling;

use Enlixo\Core\Database;

final class PdoTimetableRepository implements TimetableRepository
{
    private const TT_ACTIVE = 1;

    public function __construct(private readonly Database $db)
    {
    }

    public function activeSlots(): array
    {
        $rows = $this->db->all(
            'SELECT cs.*, c.class_name, c.tutor_id FROM class_schedules cs
               JOIN classes c ON c.class_id = cs.classes_class_id
              WHERE cs.status_status_id = 1 AND c.status_status_id = 1',
        );
        return array_map(static fn (array $r) => new WeeklySlot(
            (int) $r['class_schedule_id'], (int) $r['classes_class_id'], (int) $r['tutor_id'], $r['class_name'],
            (int) $r['weekday'], $r['start_time'], $r['end_time'], (int) $r['lecture_type_lecture_type_id'],
            (bool) $r['auto_create_zoom'], new \DateTimeImmutable($r['valid_from']),
            $r['valid_until'] ? new \DateTimeImmutable($r['valid_until']) : null,
        ), $rows);
    }

    public function sessionExists(int $slotId, string $date): bool
    {
        return (bool) $this->db->value(
            'SELECT 1 FROM time_table WHERE class_schedules_class_schedule_id = :s AND `date` = :d',
            ['s' => $slotId, 'd' => $date],
        );
    }

    public function findTutorConflict(int $tutorId, string $date, string $start, string $end): ?string
    {
        $title = $this->db->value(
            'SELECT CONCAT(c.class_name, " ", TIME_FORMAT(tt.start_time, "%H:%i"), "–", TIME_FORMAT(tt.end_time, "%H:%i"))
               FROM time_table tt JOIN classes c ON c.class_id = tt.classes_class_id
              WHERE c.tutor_id = :t AND tt.`date` = :d AND tt.time_table_status_time_table_status_id = :active
                AND tt.start_time < :end AND :start < tt.end_time
              LIMIT 1',
            ['t' => $tutorId, 'd' => $date, 'active' => self::TT_ACTIVE, 'start' => $start, 'end' => $end],
        );
        return $title === null ? null : (string) $title;
    }

    public function insertSession(WeeklySlot $slot, string $date, int $createdBy): int
    {
        return $this->db->insert(
            'INSERT INTO time_table (classes_class_id, `date`, start_time, end_time, duration, lecture_type_lecture_type_id, lecture,
                                     users_user_id, date_added, time_table_status_time_table_status_id, lecture_title, class_schedules_class_schedule_id)
             VALUES (:c, :d, :s, :e, :dur, :lt, "", :u, NOW(), :active, :title, :slot)',
            [
                'c' => $slot->classId, 'd' => $date, 's' => $slot->startTime, 'e' => $slot->endTime,
                'dur' => $slot->durationMinutes() / 60, 'lt' => $slot->lectureType,
                'u' => $createdBy ?: $slot->tutorId, 'active' => self::TT_ACTIVE,
                'title' => mb_substr($slot->className, 0, 50), 'slot' => $slot->id,
            ],
        );
    }

    public function saveMeeting(int $timeTableId, CreatedMeeting $meeting, string $encryptedStartUrl, ?int $integrationId): void
    {
        $this->db->execute(
            'INSERT INTO zoom_meetings (time_table_time_table_id, integrations_integration_id, zoom_meeting_id, join_url, start_url_encrypted, passcode, status)
             VALUES (:tt, :i, :m, :j, :s, :p, "scheduled")
             ON DUPLICATE KEY UPDATE zoom_meeting_id = VALUES(zoom_meeting_id), join_url = VALUES(join_url),
                start_url_encrypted = VALUES(start_url_encrypted), passcode = VALUES(passcode), status = "scheduled", last_error = NULL',
            ['tt' => $timeTableId, 'i' => $integrationId, 'm' => $meeting->meetingId, 'j' => $meeting->joinUrl, 's' => $encryptedStartUrl, 'p' => $meeting->passcode],
        );
    }

    public function saveMeetingFailure(int $timeTableId, string $error): void
    {
        $this->db->execute(
            'INSERT INTO zoom_meetings (time_table_time_table_id, zoom_meeting_id, join_url, start_url_encrypted, status, last_error)
             VALUES (:tt, "", "", "", "failed", :e)
             ON DUPLICATE KEY UPDATE status = "failed", last_error = VALUES(last_error)',
            ['tt' => $timeTableId, 'e' => $error],
        );
    }
}

<?php
declare(strict_types=1);

namespace Enlixo\Classes;

use Enlixo\Core\Database;

/** The tutor's Classes page: every class with what the cards, table and details views show. */
final class ClassListRepository
{
    public function __construct(private readonly Database $db)
    {
    }

    public function overview(int $tutorId): array
    {
        $rows = $this->db->all(
            "SELECT c.class_id, c.class_name, c.class_cover, c.status_status_id, c.class_type, c.delivery_mode, c.location, c.capacity,
                    c.enrollment_mode, c.is_free, c.added_at, c.sort_order, c.description,
                    c.exam_type_exam_type_id, c.year_year_id, c.subjects_subject_id, c.medium_medium_id,
                    s.subject, y.`year`, m.medium, e.exam_type,
                    (SELECT COUNT(*) FROM students_has_classes sc WHERE sc.classes_class_id = c.class_id AND sc.status_status_id = 1) AS students,
                    (SELECT p.amount FROM class_payment_plans p WHERE p.classes_class_id = c.class_id ORDER BY p.class_payment_plan_id DESC LIMIT 1) AS fee,
                    (SELECT p.due_date FROM class_payment_plans p WHERE p.classes_class_id = c.class_id ORDER BY p.class_payment_plan_id DESC LIMIT 1) AS due_day
               FROM classes c
               JOIN subjects s ON s.subject_id = c.subjects_subject_id
               JOIN `year` y ON y.year_id = c.year_year_id
               JOIN medium m ON m.medium_id = c.medium_medium_id
               JOIN exam_type e ON e.exam_type_id = c.exam_type_exam_type_id
              WHERE c.tutor_id = :t
              ORDER BY c.sort_order IS NULL, c.sort_order, c.class_id DESC",
            ['t' => $tutorId],
        );
        $times = [];
        if ($rows) {
            $ids = implode(',', array_map(static fn ($r) => (int) $r['class_id'], $rows));
            foreach ($this->db->all(
                "SELECT classes_class_id c, weekday, start_time, end_time, lecture_type_lecture_type_id lt FROM class_schedules
                  WHERE classes_class_id IN ($ids) AND status_status_id = 1 AND (valid_until IS NULL OR valid_until >= CURDATE())
                  ORDER BY weekday, start_time",
            ) as $t) {
                $times[(int) $t['c']][] = ['weekday' => (int) $t['weekday'], 'start' => substr((string) $t['start_time'], 0, 5), 'end' => substr((string) $t['end_time'], 0, 5), 'mode' => (int) $t['lt'] === 2 ? 'online' : 'physical'];
            }
        }
        return array_map(static fn (array $r) => [
            'id' => (int) $r['class_id'],
            'name' => (string) $r['class_name'],
            'cover' => (string) $r['class_cover'],
            'active' => (int) $r['status_status_id'] === 1,
            'subject' => (string) $r['subject'], 'subject_id' => (string) $r['subjects_subject_id'],
            'exam' => (string) $r['exam_type'], 'exam_id' => (string) $r['exam_type_exam_type_id'],
            'year' => (string) $r['year'], 'year_id' => (string) $r['year_year_id'],
            'medium' => (string) $r['medium'], 'medium_id' => (string) $r['medium_medium_id'],
            'class_type' => $r['class_type'],
            'delivery_mode' => $r['delivery_mode'],
            'location' => $r['location'],
            'description' => (string) ($r['description'] ?? ''),
            'capacity' => $r['capacity'] === null ? null : (int) $r['capacity'],
            'enrollment_mode' => $r['enrollment_mode'],
            'is_free' => (bool) $r['is_free'],
            'fee' => $r['fee'] === null ? null : (float) $r['fee'],
            'due_day' => $r['due_day'] === null ? null : (int) $r['due_day'],
            'students' => (int) $r['students'],
            'added_at' => (string) $r['added_at'],
            'times' => $times[(int) $r['class_id']] ?? [],
        ], $rows);
    }

    /** Saves the order the tutor dragged the classes into. Ids that aren't this tutor's are ignored. */
    public function saveOrder(int $tutorId, array $ids): int
    {
        return $this->db->transaction(function () use ($tutorId, $ids) {
            $n = 0;
            foreach (array_values($ids) as $i => $id) {
                $n += $this->db->execute('UPDATE classes SET sort_order = :o WHERE class_id = :id AND tutor_id = :t', ['o' => $i + 1, 'id' => (int) $id, 't' => $tutorId]);
            }
            return $n;
        });
    }

    public function setStatus(int $tutorId, array $ids, bool $active): int
    {
        $n = 0;
        foreach ($ids as $id) {
            $n += $this->db->execute('UPDATE classes SET status_status_id = :s WHERE class_id = :id AND tutor_id = :t', ['s' => $active ? 1 : 2, 'id' => (int) $id, 't' => $tutorId]);
        }
        return $n;
    }
}

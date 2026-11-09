<?php
declare(strict_types=1);

namespace Enlixo\Classes;

use Enlixo\Core\Database;

final class ClassFormRepository
{
    public function __construct(private readonly Database $db)
    {
    }

    /** Dropdown options for the built-in select fields. */
    public function lookups(): array
    {
        $opts = fn (string $sql) => array_map(static fn (array $r) => ['value' => (string) $r['v'], 'label' => (string) $r['l']], $this->db->all($sql));
        return [
            'exam_types' => $opts('SELECT exam_type_id v, exam_type l FROM exam_type ORDER BY exam_type_id'),
            'years' => $opts('SELECT year_id v, `year` l FROM `year` ORDER BY `year`'),
            'subjects' => $opts('SELECT subject_id v, subject l FROM subjects ORDER BY subject, subject_id'),
            'mediums' => $opts('SELECT medium_id v, medium l FROM medium ORDER BY medium_id'),
        ];
    }

    public function lookupLabel(string $table, int $id): ?string
    {
        [$col, $idCol] = match ($table) {
            'exam_type' => ['exam_type', 'exam_type_id'],
            'year' => ['year', 'year_id'],
            'subjects' => ['subject', 'subject_id'],
            'medium' => ['medium', 'medium_id'],
        };
        $v = $this->db->value("SELECT `$col` FROM `$table` WHERE `$idCol` = :id", ['id' => $id]);
        return $v === null ? null : (string) $v;
    }

    public function settings(int $tutorId): array
    {
        $json = $this->db->value('SELECT config FROM tutor_class_form_settings WHERE tutor_id = :t', ['t' => $tutorId]);
        $cfg = is_string($json) ? json_decode($json, true) : null;
        return is_array($cfg) ? $cfg + ['hidden' => [], 'custom' => []] : ['hidden' => [], 'custom' => []];
    }

    public function saveSettings(int $tutorId, array $config): void
    {
        $this->db->execute(
            'INSERT INTO tutor_class_form_settings (tutor_id, config) VALUES (:t, :c) ON DUPLICATE KEY UPDATE config = VALUES(config)',
            ['t' => $tutorId, 'c' => json_encode($config, JSON_UNESCAPED_UNICODE)],
        );
    }

    public function nameTaken(int $tutorId, string $name): bool
    {
        return (bool) $this->db->value('SELECT 1 FROM classes WHERE tutor_id = :t AND class_name = :n LIMIT 1', ['t' => $tutorId, 'n' => $name]);
    }

    /** Inserts the class, its weekly times, its monthly fee and custom values in one transaction. */
    public function create(int $tutorId, int $userId, array $c, array $slots, ?array $fee, array $custom, string $now): int
    {
        return $this->db->transaction(function () use ($tutorId, $userId, $c, $slots, $fee, $custom, $now) {
            $classId = $this->db->insert(
                'INSERT INTO classes (class_name, medium_medium_id, year_year_id, exam_type_exam_type_id, subjects_subject_id, class_cover, tutor_id,
                                      status_status_id, added_by, added_at, class_type, description, delivery_mode, location, capacity, enrollment_mode, starts_on, is_free)
                 VALUES (:name, :medium, :year, :exam, :subject, :cover, :tutor, 1, :by, :at, :type, :descr, :mode, :loc, :cap, :enrol, :starts, :free)',
                [
                    'name' => $c['name'], 'medium' => $c['medium'], 'year' => $c['year'], 'exam' => $c['exam_type'], 'subject' => $c['subject'], 'cover' => $c['cover'] ?? '',
                    'tutor' => $tutorId, 'by' => $userId, 'at' => $now, 'type' => $c['class_type'], 'descr' => $c['description'], 'mode' => $c['delivery_mode'],
                    'loc' => $c['location'], 'cap' => $c['capacity'], 'enrol' => $c['enrollment_mode'], 'starts' => $c['starts_on'], 'free' => $c['is_free'] ? 1 : 0,
                ],
            );
            foreach ($slots as $s) {
                $this->db->execute(
                    'INSERT INTO class_schedules (classes_class_id, weekday, start_time, end_time, lecture_type_lecture_type_id, auto_create_zoom, valid_from, status_status_id, created_by)
                     VALUES (:c, :d, :s, :e, :lt, :z, :from, 1, :by)',
                    ['c' => $classId, 'd' => $s['weekday'], 's' => $s['start'], 'e' => $s['end'], 'lt' => $s['mode'] === 'online' ? 2 : 1, 'z' => $s['zoom'] ? 1 : 0, 'from' => $c['starts_on'] ?? substr($now, 0, 10), 'by' => $userId],
                );
            }
            if ($fee !== null) {
                // Same row the existing Payment Plans page writes: no discount, no penalty.
                $this->db->execute(
                    'INSERT INTO class_payment_plans (classes_class_id, amount, users_user_id, date_added, due_date, discount_type_discount_type_id, discount, discount_note, panelty_payment_option_panelty_payment_option_id)
                     VALUES (:c, :a, :u, :at, :due, 1, 0, \'\', 1)',
                    ['c' => $classId, 'a' => $fee['amount'], 'u' => $userId, 'at' => $now, 'due' => $fee['due_day']],
                );
            }
            foreach ($custom as $key => $value) {
                $this->db->execute('INSERT INTO class_custom_field_values (classes_class_id, field_key, value) VALUES (:c, :k, :v)', ['c' => $classId, 'k' => $key, 'v' => $value]);
            }
            return $classId;
        });
    }
}

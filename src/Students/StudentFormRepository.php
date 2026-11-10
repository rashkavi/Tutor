<?php
declare(strict_types=1);

namespace Enlixo\Students;

use Enlixo\Core\Database;

/** The tutor's student form settings and the dropdown lists the form uses. */
final class StudentFormRepository
{
    public function __construct(private readonly Database $db)
    {
    }

    public function settings(int $tutorId): array
    {
        $json = $this->db->value('SELECT config FROM tutor_student_form_settings WHERE tutor_id = :t', ['t' => $tutorId]);
        $cfg = is_string($json) ? json_decode($json, true) : null;
        $empty = ['hidden' => [], 'required' => [], 'labels' => [], 'order' => [], 'custom' => []];
        return is_array($cfg) ? $cfg + $empty : $empty;
    }

    public function saveSettings(int $tutorId, array $config): void
    {
        $this->db->execute(
            'INSERT INTO tutor_student_form_settings (tutor_id, config) VALUES (:t, :c) ON DUPLICATE KEY UPDATE config = VALUES(config)',
            ['t' => $tutorId, 'c' => json_encode($config, JSON_UNESCAPED_UNICODE)],
        );
    }

    /** Dropdown lists for the select fields. `classes` = this tutor's active classes only. */
    public function lookups(int $tutorId): array
    {
        $map = static fn (array $rows) => array_map(static fn (array $r) => ['value' => (string) $r['v'], 'label' => (string) $r['l']], $rows);
        return [
            'classes' => $map($this->db->all('SELECT class_id v, class_name l FROM classes WHERE tutor_id = :t AND status_status_id = 1 ORDER BY class_name', ['t' => $tutorId])),
            'genders' => $map($this->db->all('SELECT gender_id v, gender l FROM gender ORDER BY gender_id')),
            'titles' => $map($this->db->all('SELECT title_id v, title l FROM title ORDER BY title_id')),
            'relations' => $map($this->db->all('SELECT guardian_relation_id v, guardian_relation l FROM guardian_relation ORDER BY guardian_relation_id')),
            'cities' => array_map(static fn (array $r) => (string) $r['l'], $this->db->all('SELECT DISTINCT city l FROM city ORDER BY city')),
        ];
    }

    public function cityId(string $name): ?int
    {
        $id = $this->db->value('SELECT city_id FROM city WHERE LOWER(TRIM(city)) = LOWER(TRIM(:n)) ORDER BY city_id LIMIT 1', ['n' => $name]);
        return $id === null || $id === false ? null : (int) $id;
    }

    /** Name and photo of the tutor, for the public application page. */
    public function tutor(int $tutorId): ?array
    {
        return $this->db->one('SELECT user_id id, user_fname first_name, user_lname last_name, user_image image FROM users WHERE user_id = :t', ['t' => $tutorId]);
    }
}

<?php
declare(strict_types=1);

namespace Enlixo\Classes;

use Enlixo\Core\Database;

final class ClassFormRepository
{
    public function __construct(private readonly Database $db)
    {
    }

    /** The dropdown lists a tutor can add to: list key => [table, id column, label column, max length, singular name]. */
    public const OWN_LISTS = [
        'exam_types' => ['exam_type', 'exam_type_id', 'exam_type', 45, 'exam type'],
        'subjects' => ['subjects', 'subject_id', 'subject', 150, 'subject'],
        'mediums' => ['medium', 'medium_id', 'medium', 20, 'medium'],
    ];
    public const MAX_OWN_PER_LIST = 50;

    /**
     * Dropdown options for the built-in select fields: the defaults plus this tutor's own
     * (own = a row in tutor_lookup_owners for this tutor; other tutors' own options are left out).
     */
    public function lookups(int $tutorId): array
    {
        $out = [];
        foreach (self::OWN_LISTS as $list => [$table, $idCol, $col]) {
            $out[$list] = array_map(
                static fn (array $r) => ['value' => (string) $r['v'], 'label' => (string) $r['l']] + ($r['own'] ? ['own' => true] : []),
                $this->db->all(
                    "SELECT t.`$idCol` v, t.`$col` l, o.tutor_id IS NOT NULL own
                       FROM `$table` t LEFT JOIN tutor_lookup_owners o ON o.list = :list AND o.option_id = t.`$idCol`
                      WHERE o.tutor_id IS NULL OR o.tutor_id = :t
                      ORDER BY o.tutor_id IS NOT NULL, " . ($list === 'subjects' ? "t.`$col`, t.`$idCol`" : "t.`$idCol`"),
                    ['list' => $list, 't' => $tutorId],
                ),
            );
        }
        $out['years'] = array_map(static fn (array $r) => ['value' => (string) $r['v'], 'label' => (string) $r['l']], $this->db->all('SELECT year_id v, `year` l FROM `year` ORDER BY `year`'));
        return ['exam_types' => $out['exam_types'], 'years' => $out['years'], 'subjects' => $out['subjects'], 'mediums' => $out['mediums']];
    }

    /** The three lists with how many of this tutor's classes use each option (for the settings page). */
    public function optionsWithUsage(int $tutorId): array
    {
        $usageCol = ['exam_types' => 'exam_type_exam_type_id', 'subjects' => 'subjects_subject_id', 'mediums' => 'medium_medium_id'];
        $lookups = $this->lookups($tutorId);
        $out = [];
        foreach ($usageCol as $list => $col) {
            $used = [];
            foreach ($this->db->all("SELECT `$col` id, COUNT(*) n FROM classes WHERE tutor_id = :t GROUP BY `$col`", ['t' => $tutorId]) as $r) {
                $used[(string) $r['id']] = (int) $r['n'];
            }
            $out[$list] = array_map(static fn (array $o) => $o + ['own' => false, 'used' => $used[$o['value']] ?? 0], $lookups[$list]);
        }
        return $out;
    }

    /** @return array{id:int,label:string,tutor_id:?int}|null */
    public function findOption(string $list, int $id): ?array
    {
        [$table, $idCol, $col] = self::OWN_LISTS[$list];
        return $this->db->one(
            "SELECT t.`$idCol` id, t.`$col` label, o.tutor_id FROM `$table` t
               LEFT JOIN tutor_lookup_owners o ON o.list = :list AND o.option_id = t.`$idCol`
              WHERE t.`$idCol` = :id",
            ['list' => $list, 'id' => $id],
        );
    }

    /** Is this label already in the list the tutor sees (defaults or own)? Case and spaces are ignored. */
    public function optionExists(string $list, int $tutorId, string $label, ?int $exceptId = null): bool
    {
        [$table, $idCol, $col] = self::OWN_LISTS[$list];
        return (bool) $this->db->value(
            "SELECT 1 FROM `$table` t LEFT JOIN tutor_lookup_owners o ON o.list = :list AND o.option_id = t.`$idCol`
              WHERE (o.tutor_id IS NULL OR o.tutor_id = :t) AND LOWER(TRIM(t.`$col`)) = LOWER(:l) AND t.`$idCol` <> :x LIMIT 1",
            ['list' => $list, 't' => $tutorId, 'l' => $label, 'x' => $exceptId ?? 0],
        );
    }

    public function countOwnOptions(string $list, int $tutorId): int
    {
        return (int) $this->db->value('SELECT COUNT(*) FROM tutor_lookup_owners WHERE list = :list AND tutor_id = :t', ['list' => $list, 't' => $tutorId]);
    }

    public function addOption(string $list, int $tutorId, string $label): int
    {
        [$table, , $col] = self::OWN_LISTS[$list];
        return $this->db->transaction(function () use ($list, $tutorId, $label, $table, $col) {
            $id = $this->db->insert("INSERT INTO `$table` (`$col`) VALUES (:l)", ['l' => $label]);
            $this->db->execute('INSERT INTO tutor_lookup_owners (list, option_id, tutor_id) VALUES (:list, :id, :t)', ['list' => $list, 'id' => $id, 't' => $tutorId]);
            return $id;
        });
    }

    /** Only call for an option this tutor owns (see findOption). */
    public function renameOption(string $list, int $id, string $label): void
    {
        [$table, $idCol, $col] = self::OWN_LISTS[$list];
        $this->db->execute("UPDATE `$table` SET `$col` = :l WHERE `$idCol` = :id", ['l' => $label, 'id' => $id]);
    }

    /** Classes (of any tutor) that use this option. Own options are only ever used by their tutor. */
    public function optionUsage(string $list, int $id): int
    {
        $col = ['exam_types' => 'exam_type_exam_type_id', 'subjects' => 'subjects_subject_id', 'mediums' => 'medium_medium_id'][$list];
        return (int) $this->db->value("SELECT COUNT(*) FROM classes WHERE `$col` = :id", ['id' => $id]);
    }

    /** Only call for an option this tutor owns and no class uses. */
    public function deleteOption(string $list, int $id): void
    {
        [$table, $idCol] = self::OWN_LISTS[$list];
        $this->db->transaction(function () use ($list, $id, $table, $idCol) {
            $this->db->execute('DELETE FROM tutor_lookup_owners WHERE list = :list AND option_id = :id', ['list' => $list, 'id' => $id]);
            $this->db->execute("DELETE FROM `$table` WHERE `$idCol` = :id", ['id' => $id]);
        });
    }

    public function aiUsageToday(int $tutorId, string $kind, string $dayStart): int
    {
        return (int) $this->db->value('SELECT COUNT(*) FROM ai_usage_log WHERE tutor_id = :t AND kind = :k AND created_at >= :d', ['t' => $tutorId, 'k' => $kind, 'd' => $dayStart]);
    }

    public function logAiUsage(int $tutorId, string $kind, string $now): void
    {
        $this->db->execute('INSERT INTO ai_usage_log (tutor_id, kind, created_at) VALUES (:t, :k, :n)', ['t' => $tutorId, 'k' => $kind, 'n' => $now]);
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
        $empty = ['hidden' => [], 'required' => [], 'labels' => [], 'order' => [], 'custom' => []];
        return is_array($cfg) ? $cfg + $empty : $empty;
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

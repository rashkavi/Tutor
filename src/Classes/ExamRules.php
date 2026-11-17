<?php
declare(strict_types=1);

namespace Enlixo\Classes;

/**
 * Which exam years and subjects fit an exam type on the class form.
 * Exam types and subjects are matched by NAME (ids differ between databases); a tutor's own exam type or subject
 * is not in these lists and is never limited.
 *
 * Years: the current year and the next ones, as many as the exam type allows
 * (Scholarship 3 -> 2026 - 2028, O/L 7 -> 2026 - 2032, A/L 4 -> 2026 - 2029 in 2026).
 */
final class ExamRules
{
    /** exam type name => how many exam years (counting this year) a class can be for */
    public const YEARS = [
        'scholarship' => 3,
        'o/l' => 7,
        'a/l' => 4,
        'london o/l' => 7,
        'london a/l' => 4,
    ];

    /** exam type name => subject names that belong to it */
    public const SUBJECTS = [
        'scholarship' => ['Grade 5 Scholarship', 'Sinhala', 'Tamil', 'English', 'Engliish', 'Mathematics', 'Environment'],
        'o/l' => self::OL,
        'london o/l' => self::OL,
        'a/l' => self::AL,
        'london a/l' => self::AL,
    ];

    private const OL = ['Sinhala', 'Tamil', 'English', 'Engliish', 'Mathematics', 'Science', 'History', 'Buddhism', 'Catholicism', 'Christianity',
        'Islam', 'Hinduism', 'Commerce', 'Geography', 'Civic Education', 'Health & Physical Education', 'Information & Communication Technology',
        'Art', 'Music', 'Dancing', 'Drama & Theatre'];

    private const AL = ['Combined Mathematics', 'Physics', 'Chemistry', 'Biology', 'Agricultural Science', 'Accounting', 'Business Studies',
        'Economics', 'Information & Communication Technology', 'Sinhala', 'Tamil', 'English', 'Engliish', 'Geography', 'History', 'Buddhism',
        'Catholicism', 'Christianity', 'Islam', 'Hinduism', 'Art', 'Music', 'Dancing', 'Drama & Theatre'];

    public static function key(string $name): string
    {
        return mb_strtolower(trim(preg_replace('/\s+/', ' ', $name)));
    }

    /**
     * Adds `when: {exam_type: [ids]}` to the year and subject options, so the form lists only what fits the chosen exam type.
     * @param array{exam_types: list<array>, years: list<array>, subjects: list<array>} $lookups
     */
    public static function annotate(array $lookups, int $currentYear): array
    {
        $examIds = [];
        foreach ($lookups['exam_types'] as $e) {
            $examIds[self::key($e['label'])] = (string) $e['value'];
        }
        foreach ($lookups['years'] as &$y) {
            $ids = [];
            foreach (self::YEARS as $exam => $count) {
                if (isset($examIds[$exam]) && (int) $y['label'] >= $currentYear && (int) $y['label'] < $currentYear + $count) {
                    $ids[] = $examIds[$exam];
                }
            }
            $y['when'] = ['exam_type' => $ids];
        }
        unset($y);
        foreach ($lookups['subjects'] as &$s) {
            if (!empty($s['own'])) {
                continue; // a tutor's own subject fits every exam type
            }
            $ids = [];
            foreach (self::SUBJECTS as $exam => $names) {
                if (isset($examIds[$exam]) && in_array(self::key($s['label']), array_map(self::key(...), $names), true)) {
                    $ids[] = $examIds[$exam];
                }
            }
            if ($ids) {
                $s['when'] = ['exam_type' => $ids];
            }
        }
        unset($s);
        return $lookups;
    }

    /** Message when the year or subject does not fit the exam type, keyed by field; [] when it fits. */
    public static function check(array $lookups, string $examId, string $yearId, string $subjectId, int $currentYear): array
    {
        $l = self::annotate($lookups, $currentYear);
        $exam = '';
        foreach ($l['exam_types'] as $e) {
            if ((string) $e['value'] === $examId) {
                $exam = self::key($e['label']);
            }
        }
        $errors = [];
        if (isset(self::YEARS[$exam])) {
            foreach ($l['years'] as $y) {
                if ((string) $y['value'] === $yearId && !in_array($examId, $y['when']['exam_type'], true)) {
                    $last = $currentYear + self::YEARS[$exam] - 1;
                    $errors['year'] = "Choose an exam year from {$currentYear} to {$last} for this exam type.";
                }
            }
        }
        if (isset(self::SUBJECTS[$exam])) {
            foreach ($l['subjects'] as $s) {
                if ((string) $s['value'] === $subjectId && isset($s['when']) && !in_array($examId, $s['when']['exam_type'], true)) {
                    $errors['subject'] = 'This subject is not part of this exam. Choose one from the list, or add your own subject.';
                }
            }
        }
        return $errors;
    }
}

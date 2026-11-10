<?php
declare(strict_types=1);

namespace Enlixo\Students;

use Enlixo\Core\Database;
use Enlixo\Core\HttpException;

/**
 * Registers a student from the student form, the same way the old tutor/api/addStudent.php does
 * (login_details + students + student_has_address + students_has_classes + parents), with prepared statements.
 *
 * When a student with the same WhatsApp number already exists (for example registered by another tutor),
 * that student is added to the class instead of making a second account.
 */
final class StudentRegistrar
{
    public function __construct(private readonly Database $db, private readonly StudentFormRepository $forms)
    {
    }

    /**
     * @param array<string,string> $v      values from StudentFormValidator
     * @param list<string>         $extraKeys keys saved to student_custom_field_values (nic, school, own fields)
     * @return array{student_id:int, existing:bool, already_in_class:bool}
     */
    public function register(int $tutorId, int $byUserId, array $v, array $extraKeys, ?string $photo, string $now): array
    {
        $classId = (int) ($v['class_id'] ?? 0);
        if (!$this->db->value('SELECT 1 FROM classes WHERE class_id = :c AND tutor_id = :t', ['c' => $classId, 't' => $tutorId])) {
            throw HttpException::validation(['class_id' => 'Choose one of your classes.']);
        }

        return $this->db->transaction(function () use ($tutorId, $byUserId, $v, $extraKeys, $photo, $now, $classId) {
            $existing = $this->db->value(
                'SELECT s.student_id FROM students s JOIN login_details ld ON ld.login_details_id = s.login_details_login_details_id
                  WHERE ld.whatsapp = :w ORDER BY s.student_id LIMIT 1',
                ['w' => $v['whatsapp']],
            );
            if ($existing) {
                $studentId = (int) $existing;
            } else {
                $studentId = $this->createStudent($tutorId, $v, $photo, $now);
            }

            $inClass = (bool) $this->db->value('SELECT 1 FROM students_has_classes WHERE classes_class_id = :c AND students_student_id = :s', ['c' => $classId, 's' => $studentId]);
            if (!$inClass) {
                $this->db->execute(
                    'INSERT INTO students_has_classes (classes_class_id, students_student_id, status_status_id, added_at, added_by) VALUES (:c, :s, 1, :at, :by)',
                    ['c' => $classId, 's' => $studentId, 'at' => $now, 'by' => $byUserId],
                );
            }

            foreach ($extraKeys as $key) {
                if (($v[$key] ?? '') === '') {
                    continue;
                }
                $this->db->execute(
                    'INSERT INTO student_custom_field_values (students_student_id, tutor_id, field_key, value) VALUES (:s, :t, :k, :v)
                     ON DUPLICATE KEY UPDATE value = VALUES(value)',
                    ['s' => $studentId, 't' => $tutorId, 'k' => $key, 'v' => $v[$key]],
                );
            }
            return ['student_id' => $studentId, 'existing' => (bool) $existing, 'already_in_class' => $inClass];
        });
    }

    private function createStudent(int $tutorId, array $v, ?string $photo, string $now): int
    {
        $email = ($v['email'] ?? '') !== '' ? $v['email'] : null;
        if ($email !== null && $this->db->value('SELECT 1 FROM login_details WHERE email = :e LIMIT 1', ['e' => $email])) {
            $email = null; // another account already signs in with this e-mail; WhatsApp is the sign-in for students
        }
        $loginId = $this->db->insert(
            'INSERT INTO login_details (first_name, last_name, whatsapp, email, password, date_registered, status_status_id, verification)
             VALUES (:f, :l, :w, :e, :p, :at, 1, :tok)',
            [
                'f' => $v['first_name'], 'l' => $v['last_name'], 'w' => $v['whatsapp'], 'e' => $email,
                'p' => password_hash((string) random_int(100000, 999999), PASSWORD_BCRYPT),
                'at' => $now, 'tok' => substr(str_replace(['+', '/', '='], '', base64_encode(random_bytes(75))), 0, 100),
            ],
        );
        $studentId = $this->db->insert(
            'INSERT INTO students (full_name, name_with_initials, date_of_birth, date_added, gender_gender_id, users_user_id, login_details_login_details_id, mobile, profile_img)
             VALUES (:fn, :nwi, :dob, :at, :g, :t, :ld, :m, :img)',
            [
                'fn' => ($v['full_name'] ?? '') !== '' ? $v['full_name'] : trim($v['first_name'] . ' ' . $v['last_name']),
                'nwi' => ($v['name_with_initials'] ?? '') !== '' ? $v['name_with_initials'] : null,
                'dob' => $v['date_of_birth'], 'at' => $now, 'g' => (int) $v['gender'], 't' => $tutorId, 'ld' => $loginId,
                'm' => ($v['mobile'] ?? '') !== '' ? $v['mobile'] : null, 'img' => $photo,
            ],
        );

        if (($v['address_line_1'] ?? '') !== '' && ($v['city'] ?? '') !== '' && ($cityId = $this->forms->cityId($v['city'])) !== null) {
            $this->db->execute(
                'INSERT INTO student_has_address (address_line_1, address_line_2, city_city_id, postal_code, students_student_id) VALUES (:a1, :a2, :c, :p, :s)',
                ['a1' => $v['address_line_1'], 'a2' => ($v['address_line_2'] ?? '') !== '' ? $v['address_line_2'] : null, 'c' => $cityId, 'p' => $v['postal_code'] ?? '', 's' => $studentId],
            );
        }

        if (($v['guardian_first_name'] ?? '') !== '' && ($v['guardian_whatsapp'] ?? '') !== '') {
            $parentId = $this->db->value('SELECT parent_id FROM parents WHERE whatsapp = :w ORDER BY parent_id LIMIT 1', ['w' => $v['guardian_whatsapp']]);
            if (!$parentId) {
                $parentId = $this->db->insert(
                    'INSERT INTO parents (guardian_relation_guardian_relation_id, title_title_id, parent_fname, parent_lname, email, mobile, whatsapp)
                     VALUES (:r, :ti, :f, :l, :e, :m, :w)',
                    [
                        'r' => ($v['guardian_relation'] ?? '') !== '' ? (int) $v['guardian_relation'] : null,
                        'ti' => ($v['guardian_title'] ?? '') !== '' ? (int) $v['guardian_title'] : null,
                        'f' => $v['guardian_first_name'], 'l' => $v['guardian_last_name'] ?? '',
                        'e' => ($v['guardian_email'] ?? '') !== '' ? $v['guardian_email'] : null,
                        'm' => ($v['guardian_mobile'] ?? '') !== '' ? $v['guardian_mobile'] : $v['guardian_whatsapp'],
                        'w' => $v['guardian_whatsapp'],
                    ],
                );
            }
            $this->db->execute('INSERT INTO parents_has_students (students_student_id, parents_parent_id, added_at) VALUES (:s, :p, :at)', ['s' => $studentId, 'p' => $parentId, 'at' => $now]);
        }
        return $studentId;
    }
}

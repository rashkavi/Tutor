<?php
declare(strict_types=1);

namespace Enlixo\Students;

use Enlixo\Core\Database;
use Enlixo\Support\ListQuery;
use Enlixo\Support\Paginated;

/** Students of one tutor. Replaces the string-built SQL in tutor/api/filterStudents.php (prepared statements only). */
final class StudentRepository
{
    public const FILTERS = ['class', 'status'];
    public const SORTS = ['name', 'date', 'id'];

    public function __construct(private readonly Database $db)
    {
    }

    public function forTutor(int $tutorId, ListQuery $q): Paginated
    {
        [$where, $params] = $q->where(
            ['s.student_id', 'ld.first_name', 'ld.last_name', "CONCAT(ld.first_name, ' ', ld.last_name)", 'ld.email', 'ld.whatsapp', 's.mobile'],
            ['status' => 'ld.status_status_id'],
            [
                'EXISTS (SELECT 1 FROM students_has_classes x JOIN classes c ON c.class_id = x.classes_class_id
                          WHERE x.students_student_id = s.student_id AND c.tutor_id = :tutor' . ($q->filter('class') !== null ? ' AND c.class_id = :class' : '') . ')',
            ],
            ['tutor' => $tutorId] + ($q->filter('class') !== null ? ['class' => (int) $q->filter('class')] : []),
        );

        $from = 'FROM students s
                 JOIN login_details ld ON ld.login_details_id = s.login_details_login_details_id
                 JOIN status st ON st.status_id = ld.status_status_id
                 JOIN gender g ON g.gender_id = s.gender_gender_id';

        $total = (int) $this->db->value("SELECT COUNT(*) $from $where", $params);
        $rows = $this->db->all(
            "SELECT s.student_id AS id, ld.first_name, ld.last_name, ld.email, s.mobile, ld.whatsapp, s.profile_img,
                    ld.date_registered, s.date_of_birth, st.status, g.gender,
                    (SELECT GROUP_CONCAT(c.class_name ORDER BY c.class_name SEPARATOR ', ')
                       FROM students_has_classes x JOIN classes c ON c.class_id = x.classes_class_id
                      WHERE x.students_student_id = s.student_id AND c.tutor_id = :tutor2) AS classes
             $from $where " .
            $q->orderBy(['name' => 'ld.first_name', 'date' => 'ld.date_registered', 'id' => 's.student_id'], 's.student_id') . ' ' . $q->limit(),
            $params + ['tutor2' => $tutorId],
        );
        return new Paginated($rows, $total, $q);
    }
}

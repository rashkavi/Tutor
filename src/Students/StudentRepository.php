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
        $class = $q->filter('class') !== null ? (int) $q->filter('class') : null;
        // PDO can't reuse a named placeholder, so each sub-query gets its own suffix.
        $params = [];
        $enrolled = function (string $n, string $extra = '') use ($tutorId, $class, &$params): string {
            $params["tutor$n"] = $tutorId;
            if ($class !== null) {
                $params["class$n"] = $class;
            }
            return "SELECT 1 FROM students_has_classes x JOIN classes c ON c.class_id = x.classes_class_id
                     WHERE x.students_student_id = s.student_id AND c.tutor_id = :tutor$n"
                . ($class !== null ? " AND c.class_id = :class$n" : '') . $extra;
        };
        $base = ['EXISTS (' . $enrolled('') . ')'];
        // Status is per enrolment (students_has_classes), so one tutor never changes a student's account for other tutors.
        $status = $q->filter('status');
        if ($status === '1') {
            $base[] = 'EXISTS (' . $enrolled('_s', ' AND x.status_status_id = 1') . ')';
        } elseif ($status === '2') {
            $base[] = 'NOT EXISTS (' . $enrolled('_s', ' AND x.status_status_id = 1') . ')';
        }
        [$where, $params] = $q->where(
            ['s.student_id', 'ld.first_name', 'ld.last_name', "CONCAT(ld.first_name, ' ', ld.last_name)", 'ld.email', 'ld.whatsapp', 's.mobile'],
            [],
            $base,
            $params,
        );

        $from = 'FROM students s
                 JOIN login_details ld ON ld.login_details_id = s.login_details_login_details_id
                 JOIN gender g ON g.gender_id = s.gender_gender_id';

        $total = (int) $this->db->value("SELECT COUNT(*) $from $where", $params);
        $rows = $this->db->all(
            "SELECT s.student_id AS id, ld.first_name, ld.last_name, ld.email, s.mobile, ld.whatsapp, s.profile_img,
                    ld.date_registered, s.date_of_birth, g.gender,
                    IF(EXISTS (SELECT 1 FROM students_has_classes x JOIN classes c ON c.class_id = x.classes_class_id
                                WHERE x.students_student_id = s.student_id AND c.tutor_id = :tutor3 AND x.status_status_id = 1" . ($class !== null ? ' AND c.class_id = :class3' : '') . "),
                       'Active', 'Inactive') AS status,
                    (SELECT GROUP_CONCAT(c.class_name ORDER BY c.class_name SEPARATOR ', ')
                       FROM students_has_classes x JOIN classes c ON c.class_id = x.classes_class_id
                      WHERE x.students_student_id = s.student_id AND c.tutor_id = :tutor2) AS classes
             $from $where " .
            $q->orderBy(['name' => 'ld.first_name', 'date' => 'ld.date_registered', 'id' => 's.student_id'], 's.student_id') . ' ' . $q->limit(),
            $params + ['tutor2' => $tutorId, 'tutor3' => $tutorId] + ($class !== null ? ['class3' => $class] : []),
        );
        return new Paginated($rows, $total, $q);
    }

    /**
     * Sets the enrolment status of the given students in this tutor's classes (optionally one class).
     * Rows of other tutors' classes are never touched. Returns the number of enrolments changed.
     *
     * @param list<int> $studentIds
     */
    public function setStatus(int $tutorId, array $studentIds, int $statusId, ?int $classId = null): int
    {
        $studentIds = array_values(array_unique(array_filter(array_map('intval', $studentIds), fn (int $id) => $id > 0)));
        if ($studentIds === []) {
            return 0;
        }
        $params = ['tutor' => $tutorId, 'status' => $statusId];
        $in = [];
        foreach ($studentIds as $i => $id) {
            $in[] = ":s$i";
            $params["s$i"] = $id;
        }
        $classSql = '';
        if ($classId !== null) {
            $classSql = ' AND c.class_id = :class';
            $params['class'] = $classId;
        }
        return $this->db->execute(
            'UPDATE students_has_classes x JOIN classes c ON c.class_id = x.classes_class_id
                SET x.status_status_id = :status
              WHERE c.tutor_id = :tutor' . $classSql . ' AND x.students_student_id IN (' . implode(',', $in) . ')',
            $params,
        );
    }
}

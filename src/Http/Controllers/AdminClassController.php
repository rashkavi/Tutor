<?php
declare(strict_types=1);

namespace Enlixo\Http\Controllers;

use Enlixo\App;
use Enlixo\Core\HttpException;
use Enlixo\Core\JsonResponse;
use Enlixo\Core\Request;

/**
 * Admin → Content → Classes: make or change a class for any tutor on a page (admin/class-form.php, it was a pop-up).
 *   GET  /api/v1/admin/classes/form?class=<id>  the lists (tutors, subjects, mediums, years, exam types) and the class
 *   POST /api/v1/admin/classes                  multipart: data = JSON {tutor, subject, medium, year, exam_type, name, active}, cover
 *   POST /api/v1/admin/classes/{id}             the same, changes the class
 */
final class AdminClassController
{
    private const TUTOR_ROLE = 2;
    private const COVER_TYPES = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];

    public function __construct(private readonly App $app, private readonly string $coverDir)
    {
    }

    private function admin(Request $r): void
    {
        if ($r->auth()->kind !== 'admin') {
            throw HttpException::forbidden('Only the ENLIXO admin team can do this.');
        }
    }

    public function form(Request $r): JsonResponse
    {
        $this->admin($r);
        $db = $this->app->db();
        $id = (int) ($r->query['class'] ?? 0);
        $class = $id ? $db->one('SELECT class_id, class_name, tutor_id, subjects_subject_id, medium_medium_id, year_year_id, exam_type_exam_type_id, class_cover, status_status_id FROM classes WHERE class_id = :c', ['c' => $id]) : null;
        if ($id && !$class) {
            throw HttpException::notFound('This class was not found.');
        }
        $opt = static fn (array $rows, string $id, string $name) => array_map(static fn ($x) => ['id' => (int) $x[$id], 'name' => (string) $x[$name]], $rows);
        return JsonResponse::ok([
            'tutors' => array_map(static fn ($t) => ['id' => (int) $t['user_id'], 'name' => trim($t['user_fname'] . ' ' . $t['user_lname']), 'email' => (string) $t['user_email']],
                $db->all('SELECT user_id, user_fname, user_lname, user_email FROM users WHERE user_roles_user_role_id = :r AND status_status_id = 1 ORDER BY user_fname, user_lname', ['r' => self::TUTOR_ROLE])),
            'subjects' => $opt($db->all('SELECT subject_id, subject FROM subjects ORDER BY subject'), 'subject_id', 'subject'),
            'mediums' => $opt($db->all('SELECT medium_id, medium FROM medium ORDER BY medium_id'), 'medium_id', 'medium'),
            'years' => $opt($db->all('SELECT year_id, year FROM year ORDER BY year'), 'year_id', 'year'),
            'exam_types' => $opt($db->all('SELECT exam_type_id, exam_type FROM exam_type ORDER BY exam_type_id'), 'exam_type_id', 'exam_type'),
            'class' => $class === null ? null : [
                'id' => (int) $class['class_id'], 'name' => (string) $class['class_name'], 'tutor' => (int) $class['tutor_id'],
                'subject' => (int) $class['subjects_subject_id'], 'medium' => (int) $class['medium_medium_id'], 'year' => (int) $class['year_year_id'],
                'exam_type' => (int) $class['exam_type_exam_type_id'], 'active' => (int) $class['status_status_id'] === 1,
                'cover' => $class['class_cover'] ? (string) $class['class_cover'] : null,
            ],
        ]);
    }

    public function store(Request $r): JsonResponse
    {
        $this->admin($r);
        return JsonResponse::ok($this->save($r, null), [], 201);
    }

    public function update(Request $r): JsonResponse
    {
        $this->admin($r);
        return JsonResponse::ok($this->save($r, (int) $r->param('id')));
    }

    private function save(Request $r, ?int $id): array
    {
        $db = $this->app->db();
        $data = $r->body['data'] ?? null;
        $in = is_string($data) ? (json_decode($data, true) ?: []) : $r->body;
        if ($id !== null && !$db->value('SELECT 1 FROM classes WHERE class_id = :c', ['c' => $id])) {
            throw HttpException::notFound('This class was not found.');
        }
        $e = [];
        $pick = function (string $key, string $sql, string $msg) use ($in, $db, &$e): int {
            $v = (int) ($in[$key] ?? 0);
            if ($v <= 0 || !$db->value($sql, ['v' => $v])) {
                $e[$key] = $msg;
            }
            return $v;
        };
        $tutor = $pick('tutor', 'SELECT 1 FROM users WHERE user_id = :v AND user_roles_user_role_id = ' . self::TUTOR_ROLE, 'Choose the tutor who teaches this class.');
        $subject = $pick('subject', 'SELECT 1 FROM subjects WHERE subject_id = :v', 'Choose the subject, for example Physics.');
        $medium = $pick('medium', 'SELECT 1 FROM medium WHERE medium_id = :v', 'Choose the language the class is taught in.');
        $year = $pick('year', 'SELECT 1 FROM year WHERE year_id = :v', 'Choose the exam year, for example 2027.');
        $exam = $pick('exam_type', 'SELECT 1 FROM exam_type WHERE exam_type_id = :v', 'Choose the exam, for example A/L.');
        $name = trim((string) ($in['name'] ?? ''));
        if (mb_strlen($name) > 100) {
            $e['name'] = 'Keep the class name under 100 characters.';
        }
        $cover = null;
        $file = $r->files['cover'] ?? null;
        if (is_array($file) && ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            $type = is_file((string) $file['tmp_name']) ? (string) (new \finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']) : '';
            if (($file['error'] ?? 1) !== UPLOAD_ERR_OK || !isset(self::COVER_TYPES[$type])) {
                $e['cover'] = 'Use a JPG, PNG or WebP picture.';
            } elseif ((int) $file['size'] > 2 * 1024 * 1024) {
                $e['cover'] = 'The picture is larger than 2 MB. Use a smaller one.';
            } else {
                $cover = ['tmp' => (string) $file['tmp_name'], 'ext' => self::COVER_TYPES[$type]];
            }
        }
        if ($e) {
            throw HttpException::validation($e);
        }
        if ($name === '') { // like the tutor's form: "2027 A/L Physics-Sinhala"
            $name = sprintf('%s %s %s-%s', $db->value('SELECT year FROM year WHERE year_id = :i', ['i' => $year]), $db->value('SELECT exam_type FROM exam_type WHERE exam_type_id = :i', ['i' => $exam]),
                $db->value('SELECT subject FROM subjects WHERE subject_id = :i', ['i' => $subject]), $db->value('SELECT medium FROM medium WHERE medium_id = :i', ['i' => $medium]));
        }
        $dup = $db->value('SELECT 1 FROM classes WHERE tutor_id = :t AND class_name = :n' . ($id ? ' AND class_id <> :id' : ''), ['t' => $tutor, 'n' => $name] + ($id ? ['id' => $id] : []));
        if ($dup) {
            throw HttpException::validation(['name' => 'This tutor already has a class with this name. Change the name a little.']);
        }
        $coverName = null;
        if ($cover) {
            if (!is_dir($this->coverDir)) {
                @mkdir($this->coverDir, 0775, true);
            }
            $coverName = uniqid('cls_') . '.' . $cover['ext'];
            if (!move_uploaded_file($cover['tmp'], $this->coverDir . '/' . $coverName) && !rename($cover['tmp'], $this->coverDir . '/' . $coverName)) {
                throw new HttpException(500, 'upload_failed', 'The picture could not be saved. Please try again.');
            }
        }
        $active = !array_key_exists('active', $in) || filter_var($in['active'], FILTER_VALIDATE_BOOL);
        $p = ['n' => $name, 't' => $tutor, 's' => $subject, 'm' => $medium, 'y' => $year, 'e' => $exam, 'st' => $active ? 1 : 2];
        if ($id === null) {
            $id = $db->insert(
                'INSERT INTO classes (class_name, tutor_id, subjects_subject_id, medium_medium_id, year_year_id, exam_type_exam_type_id, status_status_id, added_by, added_at, class_cover)
                 VALUES (:n, :t, :s, :m, :y, :e, :st, :by, NOW(), :c)',
                $p + ['by' => $r->auth()->userId, 'c' => $coverName],
            );
            $msg = "Class \"$name\" added.";
        } else {
            $db->execute(
                'UPDATE classes SET class_name = :n, tutor_id = :t, subjects_subject_id = :s, medium_medium_id = :m, year_year_id = :y, exam_type_exam_type_id = :e, status_status_id = :st'
                . ($coverName ? ', class_cover = :c' : '') . ' WHERE class_id = :id',
                $p + ['id' => $id] + ($coverName ? ['c' => $coverName] : []),
            );
            $msg = "Class \"$name\" saved.";
        }
        return ['id' => $id, 'message' => $msg];
    }
}

<?php
declare(strict_types=1);

namespace Enlixo\Http\Controllers;

use Enlixo\App;
use Enlixo\Core\FileResponse;
use Enlixo\Core\HttpException;
use Enlixo\Core\JsonResponse;
use Enlixo\Core\Request;
use Enlixo\Students\ApplicationService;
use Enlixo\Students\StudentBulkTemplate;
use Enlixo\Students\StudentBulkUpload;
use Enlixo\Students\StudentFormRepository;
use Enlixo\Students\StudentFormSchema;
use Enlixo\Students\StudentRegistrar;

/**
 * Upload Students Bulk (tutor/upload-student-bulk):
 *   GET  /api/v1/tutor/students/bulk                       columns, lists, classes, package room
 *   GET  /api/v1/tutor/students/bulk/template?format=xlsx|csv
 *   POST /api/v1/tutor/students/bulk  (multipart: file, class_id, action = check | import)
 */
final class StudentBulkController
{
    public function __construct(private readonly App $app)
    {
    }

    private function forms(): StudentFormRepository { return new StudentFormRepository($this->app->db()); }

    private function today(): string
    {
        return $this->app->clock()->now()->setTimezone(new \DateTimeZone('Asia/Colombo'))->format('Y-m-d');
    }

    /** @return array{0: list<array>, 1: array, 2: list<array>} fields, lookups, columns */
    private function form(int $tutor): array
    {
        $fields = StudentFormSchema::forTutor($this->forms()->settings($tutor));
        $lookups = $this->forms()->lookups($tutor);
        return [$fields, $lookups, StudentBulkUpload::columns($fields, $lookups)];
    }

    /** header => names the column accepts (lists of the template and of the instructions) */
    private static function lists(array $columns, array $fields, array $lookups): array
    {
        $out = [];
        foreach ($columns as $c) {
            if ($c['list'] !== null) {
                $out[$c['header']] = array_map(static fn ($o) => (string) $o['label'], StudentBulkUpload::listChoices($c, $fields, $lookups));
            }
        }
        return $out;
    }

    public function info(Request $r): JsonResponse
    {
        $auth = $r->auth();
        $this->app->permissions()->require($auth, 'students.manage');
        $tutor = $auth->tenantId();
        [$fields, $lookups, $columns] = $this->form($tutor);
        $lists = [];
        foreach ($columns as $c) {
            if ($c['list'] !== null) {
                $lists[] = ['header' => $c['header'], 'key' => $c['key'], 'choices' => StudentBulkUpload::listChoices($c, $fields, $lookups)];
            }
        }
        $ent = $this->app->entitlements()->for($tutor);
        return JsonResponse::ok([
            'columns' => $columns,
            'lists' => $lists,
            'classes' => $lookups['classes'],
            'students' => ['count' => $ent->studentCount, 'limit' => $ent->studentLimit],
            'max_rows' => StudentBulkUpload::MAX_ROWS,
            'max_bytes' => StudentBulkUpload::MAX_BYTES,
            'excel' => class_exists(\ZipArchive::class) && is_file(dirname(__DIR__, 3) . '/vendor/autoload.php'),
        ]);
    }

    public function template(Request $r): FileResponse
    {
        $auth = $r->auth();
        $this->app->permissions()->require($auth, 'students.manage');
        [$fields, $lookups, $columns] = $this->form($auth->tenantId());
        if ($r->input('format') === 'csv') {
            return new FileResponse(StudentBulkTemplate::csv($columns), 'students-template.csv', 'text/csv; charset=utf-8');
        }
        return new FileResponse(StudentBulkTemplate::xlsx($columns, self::lists($columns, $fields, $lookups)), 'students-template.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }

    public function upload(Request $r): JsonResponse
    {
        $auth = $r->auth();
        $this->app->permissions()->require($auth, 'students.manage');
        $tutor = $auth->tenantId();
        $this->app->entitlements()->require($tutor, 'students.registration');
        $action = (string) $r->input('action', 'check');
        $classId = (int) $r->input('class_id', 0);
        $db = $this->app->db();
        if (!$db->value('SELECT 1 FROM classes WHERE class_id = :c AND tutor_id = :t AND status_status_id = 1', ['c' => $classId, 't' => $tutor])) {
            throw HttpException::validation(['class_id' => 'Choose the class these students join.']);
        }
        $file = $r->files['file'] ?? null;
        if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            throw HttpException::validation(['file' => 'Choose the file first.']);
        }
        if (($file['error'] ?? 1) !== UPLOAD_ERR_OK || ($file['size'] ?? 0) > StudentBulkUpload::MAX_BYTES) {
            throw HttpException::validation(['file' => 'The file did not upload or is larger than 5 MB. Split it into smaller files.']);
        }

        [$fields, $lookups] = $this->form($tutor);
        [$headers, $rows] = StudentBulkUpload::read((string) $file['tmp_name'], (string) ($file['name'] ?? ''));
        if (!$rows) {
            throw HttpException::validation(['file' => 'The file has no students. Fill one student per row under the header row.']);
        }
        $existing = function (string $whatsapp) use ($db, $tutor, $classId): ?array {
            $s = $db->one(
                "SELECT s.student_id, CONCAT(ld.first_name, ' ', ld.last_name) name FROM students s JOIN login_details ld ON ld.login_details_id = s.login_details_login_details_id
                  WHERE ld.whatsapp = :w ORDER BY s.student_id LIMIT 1",
                ['w' => $whatsapp],
            );
            if (!$s) {
                return null;
            }
            $in = $db->all(
                'SELECT x.classes_class_id c, x.status_status_id st FROM students_has_classes x JOIN classes c ON c.class_id = x.classes_class_id WHERE x.students_student_id = :s AND c.tutor_id = :t',
                ['s' => $s['student_id'], 't' => $tutor],
            );
            return [
                'student_id' => (int) $s['student_id'], 'name' => trim((string) $s['name']),
                'in_class' => in_array($classId, array_map(static fn ($x) => (int) $x['c'], $in), true),
                'of_tutor' => (bool) array_filter($in, static fn ($x) => (int) $x['st'] === 1),
            ];
        };
        $result = StudentBulkUpload::check($headers, $rows, $fields, $lookups, $existing, $this->today());
        $ent = $this->app->entitlements()->for($tutor);
        $room = $ent->studentLimit === null ? null : max(0, $ent->studentLimit - $ent->studentCount);
        $result['room'] = $room;
        $result['limit'] = $ent->studentLimit;

        if ($action !== 'import') {
            return JsonResponse::ok(self::public($result));
        }

        if ($result['missing']) {
            throw new HttpException(422, 'missing_columns', 'Required columns are missing: ' . implode(', ', $result['missing']) . '. Add them and check the file again.');
        }
        if (!$result['ready']) {
            throw new HttpException(422, 'nothing_ready', 'No row is ready to import. Fix the problems and check the file again.');
        }
        if ($room !== null && $result['adds'] > $room) {
            throw new HttpException(402, 'student_limit', "Your package allows {$ent->studentLimit} students and you have {$ent->studentCount}, so you can add {$room} more. This file adds {$result['adds']}. Remove some rows or upgrade.", ['feature' => 'students.registration']);
        }

        $registrar = new StudentRegistrar($db, $this->forms());
        $extra = ApplicationService::extraKeys($fields);
        $now = $this->app->clock()->now()->setTimezone(new \DateTimeZone('Asia/Colombo'))->format('Y-m-d H:i:s');
        $added = 0;
        $joined = 0;
        $failed = [];
        foreach ($result['rows'] as $row) {
            if ($row['kind'] === 'problem') {
                continue;
            }
            try {
                $res = $registrar->register($tutor, $auth->userId, ['class_id' => (string) $classId] + $row['values'], $extra, null, $now, true);
                $res['existing'] ? $joined++ : $added++;
            } catch (HttpException $e) {
                $failed[] = ['row' => $row['row'], 'name' => $row['name'], 'message' => implode(' ', array_map('strval', $e->details['fields'] ?? [])) ?: $e->getMessage()];
            } catch (\Throwable $e) {
                error_log('Bulk upload row ' . $row['row'] . ': ' . $e->getMessage());
                $failed[] = ['row' => $row['row'], 'name' => $row['name'], 'message' => 'Could not be saved. Please try this student again.'];
            }
        }
        return JsonResponse::ok(['added' => $added, 'joined' => $joined, 'failed' => $failed, 'skipped' => $result['problems']]);
    }

    /** Leave the cleaned values out of the answer: the browser only needs what to show. */
    private static function public(array $result): array
    {
        $result['rows'] = array_map(static function ($row) {
            unset($row['values']);
            return $row;
        }, $result['rows']);
        return $result;
    }
}

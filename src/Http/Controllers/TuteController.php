<?php
declare(strict_types=1);

namespace Enlixo\Http\Controllers;

use Enlixo\App;
use Enlixo\Core\HttpException;
use Enlixo\Core\JsonResponse;
use Enlixo\Core\Request;
use Enlixo\Tutes\TuteRepository;
use Enlixo\Tutes\TuteRules;

/**
 * Tutes: the Add Tute form (tutor/add-tute.php) and the courier deliveries (tutor/couriered-tute.php),
 * plus the student's own deliveries (portal).
 *
 *   GET  /api/v1/tutor/tutes/form?class=ID              months, how many students get it, couriers
 *   POST /api/v1/tutor/tutes                            multipart: data (JSON) + files[] (downloadable)
 *   GET  /api/v1/tutor/classes/{id}/courier-tutes       posted tutes of a class with their progress
 *   GET  /api/v1/tutor/tutes/{id}/deliveries            one row per student
 *   POST /api/v1/tutor/tutes/{id}/deliveries            change ticked rows: step, courier, tracking, address
 *   POST /api/v1/tutor/tutes/{id}/deliveries/sync       add students who paid or joined after the tute was added
 *   GET  /api/v1/student/tute-deliveries                the signed-in student's parcels
 */
final class TuteController
{
    public function __construct(private readonly App $app, private readonly string $uploadDir)
    {
    }

    private function repo(): TuteRepository
    {
        return new TuteRepository($this->app->db());
    }

    private function now(): \DateTimeImmutable
    {
        return $this->app->clock()->now()->setTimezone(new \DateTimeZone('Asia/Colombo'));
    }

    private function classOf(Request $r, int $classId): array
    {
        $auth = $r->auth();
        $this->app->permissions()->require($auth, 'tutes.manage');
        return $this->repo()->findClass($auth->tenantId(), $classId) ?? throw new HttpException(404, 'not_found', 'This class was not found.');
    }

    private function tuteOf(Request $r): array
    {
        $auth = $r->auth();
        $this->app->permissions()->require($auth, 'tutes.manage');
        $tute = $this->repo()->findTute($auth->tenantId(), (int) $r->param('id'));
        if (!$tute) {
            throw new HttpException(404, 'not_found', 'This tute was not found.');
        }
        return $tute;
    }

    public function form(Request $r): JsonResponse
    {
        $class = $this->classOf($r, (int) ($r->query['class'] ?? 0));
        $months = TuteRules::monthOptions($this->now()->format('Y-m-d'));
        $audience = $this->repo()->audience((int) $class['class_id'], (bool) $class['is_free'], array_column($months, 'value'));
        return JsonResponse::ok([
            'class' => ['id' => (int) $class['class_id'], 'name' => (string) $class['class_name'], 'free' => (bool) $class['is_free']],
            'students' => $audience['all'],
            'months' => array_map(static fn ($m) => $m + ['paid' => $audience['paid'][$m['value']] ?? 0], $months),
            'this_month' => $this->now()->format('Y-m'),
            'couriers' => TuteRules::COURIERS,
        ]);
    }

    public function store(Request $r): JsonResponse
    {
        $data = $r->body['data'] ?? null;
        $in = is_string($data) ? json_decode($data, true) : $r->body;
        if (!is_array($in)) {
            throw new HttpException(400, 'bad_request', 'The form could not be read. Please reload and try again.');
        }
        $class = $this->classOf($r, (int) ($in['class'] ?? 0));
        $files = $this->uploads($r->files['files'] ?? null);
        [$t, $errors] = TuteRules::validateTute($in, $files, $this->now()->format('Y-m-d'));
        if ($errors) {
            throw new HttpException(422, 'validation_failed', 'Please check the highlighted fields.', $errors);
        }

        $stored = [];
        if ($t['type'] === TuteRules::TYPE_DOWNLOAD) {
            if (!is_dir($this->uploadDir) && !@mkdir($this->uploadDir, 0775, true)) {
                throw new HttpException(500, 'upload_folder', 'The tute could not be saved. Please tell ENLIXO support (upload folder missing).');
            }
            foreach ($files as $f) {
                $name = bin2hex(random_bytes(8)) . '.' . TuteRules::FILE_TYPES[$f['mime']];
                if (!move_uploaded_file($f['tmp'], $this->uploadDir . '/' . $name)) {
                    $this->remove($stored);
                    throw new HttpException(500, 'upload_failed', "\"{$f['name']}\" could not be saved. Please try again.");
                }
                $stored[] = ['stored' => $name, 'name' => $f['name']];
            }
        }
        try {
            $res = $this->repo()->create((int) $class['class_id'], $r->auth()->userId, $t, $stored, (bool) $class['is_free'], $this->now()->format('Y-m-d H:i:s'));
        } catch (\Throwable $e) {
            $this->remove($stored);
            throw $e;
        }
        $msg = $t['type'] === TuteRules::TYPE_COURIER
            ? ($res['deliveries'] ? "Tute added. {$res['deliveries']} " . ($res['deliveries'] === 1 ? 'parcel is' : 'parcels are') . ' ready to pack.' : 'Tute added. No student gets it yet; parcels are added when students pay.')
            : 'Tute added. Students can download it now.';
        return JsonResponse::ok(['id' => $res['id'], 'deliveries' => $res['deliveries'], 'courier' => $t['type'] === TuteRules::TYPE_COURIER, 'message' => $msg], [], 201);
    }

    public function courierTutes(Request $r): JsonResponse
    {
        $class = $this->classOf($r, (int) $r->param('id'));
        return JsonResponse::ok(['class' => ['id' => (int) $class['class_id'], 'name' => (string) $class['class_name']], 'tutes' => $this->repo()->courierTutes((int) $class['class_id'])]);
    }

    public function deliveries(Request $r): JsonResponse
    {
        $tute = $this->tuteOf($r);
        return JsonResponse::ok($this->deliveryPayload($tute));
    }

    public function sync(Request $r): JsonResponse
    {
        $tute = $this->tuteOf($r);
        $added = $this->repo()->addMissingDeliveries((int) $tute['class_tute_id'], (int) $tute['classes_class_id'], $tute['payment_month'], (bool) $tute['is_free']);
        return JsonResponse::ok($this->deliveryPayload($tute) + ['added' => $added, 'message' => $added ? "$added new " . ($added === 1 ? 'student was' : 'students were') . ' added.' : 'Everyone who gets this tute is already on the list.']);
    }

    public function updateDeliveries(Request $r): JsonResponse
    {
        $tute = $this->tuteOf($r);
        [$u, $errors] = TuteRules::validateDeliveryUpdate($r->body);
        if ($errors) {
            throw new HttpException(422, 'validation_failed', reset($errors), $errors);
        }
        $today = $this->now()->format('Y-m-d');
        $changed = $this->repo()->updateDeliveries((int) $tute['class_tute_id'], $u['ids'], TuteRules::withStepDates($u['set'], $today), $r->auth()->userId, $today);
        return JsonResponse::ok($this->deliveryPayload($tute) + ['changed' => $changed]);
    }

    public function forStudent(Request $r): JsonResponse
    {
        $auth = $r->auth();
        if ($auth->studentId === null) {
            throw new HttpException(403, 'forbidden', 'This page is for students.');
        }
        return JsonResponse::ok($this->repo()->forStudent($auth->studentId));
    }

    private function deliveryPayload(array $tute): array
    {
        return [
            'tute' => [
                'id' => (int) $tute['class_tute_id'],
                'title' => (string) $tute['tute_title'],
                'description' => (string) ($tute['tute_description'] ?? ''),
                'class_id' => (int) $tute['classes_class_id'],
                'class' => (string) $tute['class_name'],
                'month' => TuteRules::monthLabel($tute['payment_month']),
                'courier_name' => (string) ($tute['courier_name'] ?? ''),
                'dispatch_on' => $tute['dispatch_on'],
                'courier' => (int) $tute['tute_types_tute_type_id'] === TuteRules::TYPE_COURIER,
            ],
            'steps' => TuteRules::STATUSES,
            'couriers' => TuteRules::COURIERS,
            'deliveries' => $this->repo()->deliveries((int) $tute['class_tute_id']),
        ];
    }

    /** $_FILES['files'] (multiple) as a flat list, with the type read from the file itself, not from the browser. */
    private function uploads(?array $f): array
    {
        if (!$f || !isset($f['name'])) {
            return [];
        }
        $names = (array) $f['name'];
        $out = [];
        $finfo = function_exists('finfo_open') ? finfo_open(FILEINFO_MIME_TYPE) : null;
        foreach ($names as $i => $name) {
            $tmp = (string) ((array) $f['tmp_name'])[$i];
            $error = (int) ((array) $f['error'])[$i];
            if ($error === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            $mime = $error === 0 && $tmp !== '' && is_file($tmp) ? ($finfo ? (string) finfo_file($finfo, $tmp) : (string) ((array) $f['type'])[$i]) : '';
            // some servers report old .doc files as application/CDFV2; treat them as Word when the name says so
            if (in_array($mime, ['application/CDFV2', 'application/x-ole-storage'], true) && str_ends_with(strtolower((string) $name), '.doc')) {
                $mime = 'application/msword';
            }
            $out[] = ['name' => (string) $name, 'tmp' => $tmp, 'size' => (int) ((array) $f['size'])[$i], 'error' => $error, 'mime' => $mime];
        }
        return $out;
    }

    private function remove(array $stored): void
    {
        foreach ($stored as $s) {
            @unlink($this->uploadDir . '/' . $s['stored']);
        }
    }
}

<?php
declare(strict_types=1);

namespace Enlixo\Http\Controllers;

use Enlixo\App;
use Enlixo\Classes\ClassCreator;
use Enlixo\Classes\ClassFormRepository;
use Enlixo\Classes\ClassFormSchema;
use Enlixo\Core\HttpException;
use Enlixo\Core\JsonResponse;
use Enlixo\Core\Request;

final class ClassController
{
    public function __construct(private readonly App $app, private readonly string $coverDir)
    {
    }

    private function repo(): ClassFormRepository
    {
        return new ClassFormRepository($this->app->db());
    }

    /** GET /api/v1/tutor/class-form: sections, fields (after this tutor's customisation), dropdown options, locks. */
    public function form(Request $r): JsonResponse
    {
        $auth = $r->auth();
        $this->app->permissions()->require($auth, 'classes.manage');
        $tutor = $auth->tenantId();
        $ent = $this->app->entitlements()->for($tutor);
        $settings = $this->repo()->settings($tutor);
        $fields = array_map(static function (array $f) use ($ent) {
            if (isset($f['feature'])) {
                $f['locked'] = !$ent->has($f['feature']);
            }
            return $f;
        }, ClassFormSchema::forTutor($settings));

        return JsonResponse::ok([
            'sections' => ClassFormSchema::sections(),
            'fields' => $fields,
            'lookups' => $this->repo()->lookups(),
            'settings' => $settings,
            'hideable' => ClassFormSchema::hideable(),
            'custom_types' => ClassFormSchema::CUSTOM_TYPES,
            'max_custom' => ClassFormSchema::MAX_CUSTOM,
        ]);
    }

    /** PUT /api/v1/tutor/class-form: save "Customise form" (hidden fields + own fields). */
    public function saveForm(Request $r): JsonResponse
    {
        $auth = $r->auth();
        $this->app->permissions()->require($auth, 'classes.manage');
        $config = ClassFormSchema::sanitiseSettings($r->body);
        $this->repo()->saveSettings($auth->tenantId(), $config);
        return JsonResponse::ok($config);
    }

    /** POST /api/v1/tutor/classes (multipart: data = JSON of the form values, cover = image file) */
    public function store(Request $r): JsonResponse
    {
        $auth = $r->auth();
        $this->app->permissions()->require($auth, 'classes.manage');
        $this->app->entitlements()->require($auth->tenantId(), 'classes.setup');
        $data = $r->body['data'] ?? null;
        $in = is_string($data) ? json_decode($data, true) : $r->body;
        if (!is_array($in)) {
            throw new HttpException(400, 'bad_request', 'The form could not be read. Please reload and try again.');
        }
        $tutor = $auth->tenantId();
        $result = (new ClassCreator($this->repo(), $this->coverDir))->create(
            $tutor,
            $auth->userId,
            $in,
            $r->files['cover'] ?? null,
            $this->app->entitlements()->for($tutor),
            $this->app->clock()->now()->setTimezone(new \DateTimeZone('Asia/Colombo'))->format('Y-m-d H:i:s'),
        );
        return JsonResponse::ok($result, [], 201);
    }
}

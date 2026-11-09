<?php
declare(strict_types=1);

namespace Enlixo\Http\Controllers;

use Enlixo\App;
use Enlixo\Classes\ClassCreator;
use Enlixo\Classes\ClassFormRepository;
use Enlixo\Classes\ClassFormSchema;
use Enlixo\Classes\ClassThumbnailService;
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
        $all = $r->input('all') === '1';
        $fields = array_map(static function (array $f) use ($ent) {
            if (isset($f['feature'])) {
                $f['locked'] = !$ent->has($f['feature']);
            }
            return $f;
        }, $all ? ClassFormSchema::allForTutor($settings) : ClassFormSchema::forTutor($settings));

        return JsonResponse::ok([
            'sections' => ClassFormSchema::sections(),
            'fields' => $fields,
            'lookups' => $this->repo()->lookups($tutor),
            'settings' => $settings,
            'hideable' => ClassFormSchema::hideable(),
            'custom_types' => ClassFormSchema::CUSTOM_TYPES,
            'max_custom' => ClassFormSchema::MAX_CUSTOM,
            'type_names' => ClassFormSchema::TYPE_NAMES,
            'ai_thumbnails' => $this->app->geminiImages()->configured(),
            'thumbnail_styles' => array_keys(ClassThumbnailService::STYLES),
        ]);
    }

    /** PUT /api/v1/tutor/class-form: save the form settings (shown/hidden, required, order, names, own fields). */
    public function saveForm(Request $r): JsonResponse
    {
        $auth = $r->auth();
        $this->app->permissions()->require($auth, 'classes.manage');
        $config = ClassFormSchema::sanitiseSettings($r->body);
        $this->repo()->saveSettings($auth->tenantId(), $config);
        return JsonResponse::ok($config);
    }

    /** GET /api/v1/tutor/class-form/options: exam types, subjects and mediums with `own` and `used` (classes using it). */
    public function options(Request $r): JsonResponse
    {
        $auth = $r->auth();
        $this->app->permissions()->require($auth, 'classes.manage');
        return JsonResponse::ok($this->repo()->optionsWithUsage($auth->tenantId()) + ['max_own' => ClassFormRepository::MAX_OWN_PER_LIST]);
    }

    /** POST /api/v1/tutor/class-form/options/{list} {label}: add an option only this tutor sees. */
    public function addOption(Request $r): JsonResponse
    {
        $auth = $r->auth();
        $this->app->permissions()->require($auth, 'classes.manage');
        $tutor = $auth->tenantId();
        $list = $this->list($r);
        $label = $this->optionLabel($r, $list, $tutor, null);
        if ($this->repo()->countOwnOptions($list, $tutor) >= ClassFormRepository::MAX_OWN_PER_LIST) {
            throw HttpException::validation(['label' => 'You can add up to ' . ClassFormRepository::MAX_OWN_PER_LIST . ' of your own here.']);
        }
        $id = $this->repo()->addOption($list, $tutor, $label);
        return JsonResponse::ok(['value' => (string) $id, 'label' => $label, 'own' => true, 'used' => 0], [], 201);
    }

    /** PUT /api/v1/tutor/class-form/options/{list}/{id} {label}: rename one of the tutor's own options. */
    public function renameOption(Request $r): JsonResponse
    {
        $auth = $r->auth();
        $this->app->permissions()->require($auth, 'classes.manage');
        $tutor = $auth->tenantId();
        $list = $this->list($r);
        $id = $this->ownOption($r, $list, $tutor);
        $label = $this->optionLabel($r, $list, $tutor, $id);
        $this->repo()->renameOption($list, $id, $label);
        return JsonResponse::ok(['value' => (string) $id, 'label' => $label]);
    }

    /** DELETE /api/v1/tutor/class-form/options/{list}/{id}: remove one of the tutor's own options when no class uses it. */
    public function deleteOption(Request $r): JsonResponse
    {
        $auth = $r->auth();
        $this->app->permissions()->require($auth, 'classes.manage');
        $tutor = $auth->tenantId();
        $list = $this->list($r);
        $id = $this->ownOption($r, $list, $tutor);
        $used = $this->repo()->optionUsage($list, $id);
        if ($used > 0) {
            throw new HttpException(409, 'in_use', "It is used by $used " . ($used === 1 ? 'class' : 'classes') . '. Change those classes first, or keep it.');
        }
        $this->repo()->deleteOption($list, $id);
        return JsonResponse::ok(['deleted' => true]);
    }

    /** POST /api/v1/tutor/classes/thumbnail {exam_type, year, subject, medium, class_type, name, description, style} */
    public function thumbnail(Request $r): JsonResponse
    {
        $auth = $r->auth();
        $this->app->permissions()->require($auth, 'classes.manage');
        $now = $this->app->clock()->now()->setTimezone(new \DateTimeZone('Asia/Colombo'));
        $service = new ClassThumbnailService($this->repo(), $this->app->geminiImages(), (int) ($this->app->config['ai']['thumbnails_per_day'] ?? 10));
        return JsonResponse::ok($service->generate($auth->tenantId(), $r->body, $now->format('Y-m-d H:i:s'), $now->format('Y-m-d 00:00:00')));
    }

    private function list(Request $r): string
    {
        $list = $r->param('list');
        if (!isset(ClassFormRepository::OWN_LISTS[$list])) {
            throw HttpException::notFound('This list can\'t be changed.');
        }
        return $list;
    }

    private function ownOption(Request $r, string $list, int $tutor): int
    {
        $id = (int) $r->param('id');
        $row = $this->repo()->findOption($list, $id);
        if ($row === null) {
            throw HttpException::notFound('This option no longer exists.');
        }
        if ((int) ($row['tutor_id'] ?? 0) !== $tutor) {
            throw HttpException::forbidden('Default options are shared by every tutor, so they can\'t be changed. Add your own instead.');
        }
        return $id;
    }

    private function optionLabel(Request $r, string $list, int $tutor, ?int $exceptId): string
    {
        [, , , $max, $name] = ClassFormRepository::OWN_LISTS[$list];
        $label = trim((string) preg_replace('/\s+/u', ' ', (string) ($r->body['label'] ?? '')));
        if ($label === '') {
            throw HttpException::validation(['label' => "Type the $name."]);
        }
        if (mb_strlen($label) > $max) {
            throw HttpException::validation(['label' => "Keep it under $max letters."]);
        }
        if ($this->repo()->optionExists($list, $tutor, $label, $exceptId)) {
            throw HttpException::validation(['label' => "\"$label\" is already in the list."]);
        }
        return $label;
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

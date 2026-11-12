<?php
declare(strict_types=1);

namespace Enlixo\Http\Controllers;

use Enlixo\App;
use Enlixo\Core\HttpException;
use Enlixo\Core\JsonResponse;
use Enlixo\Core\Request;
use Enlixo\Students\ApplicationRepository;
use Enlixo\Students\ApplicationService;
use Enlixo\Students\StudentFormRepository;
use Enlixo\Students\StudentFormSchema;
use Enlixo\Students\StudentRegistrar;
use Enlixo\Support\ListQuery;

/** Student form settings, Add New Student, Requested Students and the public application link. */
final class ApplicationController
{
    public function __construct(private readonly App $app, private readonly string $photoDir)
    {
    }

    private function forms(): StudentFormRepository { return new StudentFormRepository($this->app->db()); }
    private function apps(): ApplicationRepository { return new ApplicationRepository($this->app->db()); }

    private function service(): ApplicationService
    {
        return new ApplicationService($this->forms(), $this->apps(), new StudentRegistrar($this->app->db(), $this->forms()), $this->photoDir);
    }

    private function now(): string
    {
        return $this->app->clock()->now()->setTimezone(new \DateTimeZone('Asia/Colombo'))->format('Y-m-d H:i:s');
    }

    // ---------- the form ----------

    /** GET /api/v1/tutor/student-form[?all=1]: sections, fields after the tutor's settings, dropdown lists. */
    public function form(Request $r): JsonResponse
    {
        $auth = $r->auth();
        $this->app->permissions()->require($auth, 'students.view');
        $tutor = $auth->tenantId();
        $settings = $this->forms()->settings($tutor);
        return JsonResponse::ok([
            'sections' => StudentFormSchema::sections(),
            'fields' => $r->input('all') === '1' ? StudentFormSchema::allForTutor($settings) : StudentFormSchema::forTutor($settings),
            'lookups' => $this->forms()->lookups($tutor),
            'settings' => $settings,
            'hideable' => StudentFormSchema::hideable(),
            'custom_types' => StudentFormSchema::CUSTOM_TYPES,
            'max_custom' => StudentFormSchema::MAX_CUSTOM,
            'type_names' => StudentFormSchema::TYPE_NAMES,
        ]);
    }

    /** PUT /api/v1/tutor/student-form */
    public function saveForm(Request $r): JsonResponse
    {
        $auth = $r->auth();
        $this->app->permissions()->require($auth, 'students.manage');
        $config = StudentFormSchema::sanitiseSettings($r->body);
        $this->forms()->saveSettings($auth->tenantId(), $config);
        return JsonResponse::ok($config);
    }

    /** POST /api/v1/tutor/students (multipart: data = JSON of the form values, photo = image) */
    public function addStudent(Request $r): JsonResponse
    {
        $auth = $r->auth();
        $this->app->permissions()->require($auth, 'students.manage');
        $tutor = $auth->tenantId();
        $this->app->entitlements()->require($tutor, 'students.registration');
        $in = json_decode((string) ($r->body['data'] ?? ''), true);
        if (!is_array($in)) {
            throw new HttpException(400, 'bad_request', 'The form could not be read. Please reload and try again.');
        }
        $result = $this->service()->addStudent($tutor, $auth->userId, $in, $r->files['photo'] ?? null, $this->app->entitlements()->for($tutor), $this->now());
        return JsonResponse::ok($result, [], 201);
    }

    // ---------- Requested Students ----------

    /** GET /api/v1/tutor/applications?q=&status=&class=&sort=&dir=&page=&per_page= */
    public function index(Request $r): JsonResponse
    {
        $auth = $r->auth();
        $this->app->permissions()->require($auth, 'students.view');
        $q = ListQuery::fromArray($r->query, ApplicationRepository::FILTERS, ApplicationRepository::SORTS, 10);
        return $this->apps()->forTutor($auth->tenantId(), $q)->toResponse();
    }

    /** GET /api/v1/tutor/applications/stats */
    public function stats(Request $r): JsonResponse
    {
        $auth = $r->auth();
        $this->app->permissions()->require($auth, 'students.view');
        return JsonResponse::ok($this->apps()->stats($auth->tenantId(), substr($this->now(), 0, 10)));
    }

    /** GET /api/v1/tutor/applications/{id}: the answers in the form's sections, the history and the next moves. */
    public function show(Request $r): JsonResponse
    {
        $auth = $r->auth();
        $this->app->permissions()->require($auth, 'students.view');
        $tutor = $auth->tenantId();
        $app = $this->apps()->find($tutor, (int) $r->param('id')) ?? throw HttpException::notFound('This application was not found.');
        unset($app['ip']);
        $settings = $this->forms()->settings($tutor);
        // every field that is on the form now, plus any hidden one that has an answer
        $fields = array_values(array_filter(StudentFormSchema::allForTutor($settings), static fn ($f) => $f['type'] !== 'image' && ($f['enabled'] || ($app['data'][$f['key']] ?? '') !== '')));
        return JsonResponse::ok([
            'application' => $app,
            'sections' => StudentFormSchema::sections(),
            'fields' => $fields,
            'lookups' => $this->forms()->lookups($tutor),
            'history' => $this->apps()->history((int) $app['application_id']),
            'moves' => ApplicationService::MOVES[$app['status']] ?? [],
        ]);
    }

    /** POST /api/v1/tutor/applications/{id}/status { status, note?, class_id? } */
    public function status(Request $r): JsonResponse
    {
        $auth = $r->auth();
        $this->app->permissions()->require($auth, 'students.approve_requests');
        $tutor = $auth->tenantId();
        $to = (string) $r->input('status', '');
        if ($to === 'approved') {
            $this->app->entitlements()->require($tutor, 'students.registration');
        }
        $class = $r->input('class_id');
        $result = $this->service()->changeStatus(
            $tutor, $auth->userId, (int) $r->param('id'), $to, (string) $r->input('note', ''),
            $class !== null && $class !== '' ? (int) $class : null, $this->app->entitlements()->for($tutor), $this->now(),
        );
        return JsonResponse::ok($result);
    }

    // ---------- the public link ----------

    /** GET /api/v1/tutor/application-link */
    public function link(Request $r): JsonResponse
    {
        $auth = $r->auth();
        $this->app->permissions()->require($auth, 'students.view');
        return JsonResponse::ok($this->apps()->link($auth->tenantId()));
    }

    /** POST /api/v1/tutor/application-link { open?: bool, renew?: bool } */
    public function updateLink(Request $r): JsonResponse
    {
        $auth = $r->auth();
        $this->app->permissions()->require($auth, 'students.manage');
        $tutor = $auth->tenantId();
        if ($r->input('renew')) {
            $this->apps()->renewLink($tutor);
        }
        if ($r->input('open') !== null) {
            $this->apps()->setLinkOpen($tutor, filter_var($r->input('open'), FILTER_VALIDATE_BOOL));
        }
        return JsonResponse::ok($this->apps()->link($tutor));
    }

    /** GET /api/v1/public/apply/{token}: the tutor's form for the public application page (no login). */
    public function publicForm(Request $r): JsonResponse
    {
        $link = $this->apps()->findLink($r->param('token')) ?? throw HttpException::notFound('This application link is not valid. Ask your tutor for a new one.');
        $tutor = $this->forms()->tutor($link['tutor_id']);
        $lookups = $this->forms()->lookups($link['tutor_id']);
        return JsonResponse::ok([
            'open' => $link['is_open'],
            'tutor' => ['name' => trim(($tutor['first_name'] ?? '') . ' ' . ($tutor['last_name'] ?? ''))],
            'sections' => StudentFormSchema::sections(),
            'fields' => array_map(static fn ($f) => array_diff_key($f, array_flip(['default_label', 'canHide', 'canRequire', 'enabled', 'typeName'])), $this->service()->fields($link['tutor_id'])),
            'lookups' => $lookups,
        ]);
    }

    /** POST /api/v1/public/apply/{token} (multipart: data = JSON {values, message, confirm, website}, photo) */
    public function publicApply(Request $r): JsonResponse
    {
        $in = json_decode((string) ($r->body['data'] ?? ''), true);
        if (!is_array($in)) {
            throw new HttpException(400, 'bad_request', 'The form could not be read. Please reload and try again.');
        }
        $result = $this->service()->apply($r->param('token'), $in, $r->files['photo'] ?? null, $r->ip, $this->now());
        return JsonResponse::ok(['ref' => $result['ref'], 'duplicate' => $result['duplicate']], [], 201);
    }
}

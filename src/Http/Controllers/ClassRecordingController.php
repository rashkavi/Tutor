<?php
declare(strict_types=1);

namespace Enlixo\Http\Controllers;

use Enlixo\App;
use Enlixo\Core\AuthContext;
use Enlixo\Core\HttpException;
use Enlixo\Core\JsonResponse;
use Enlixo\Core\Request;
use Enlixo\Recordings\ClassRecordingRepository;
use Enlixo\Recordings\LinkChecker;
use Enlixo\Recordings\RecordingTypeRepository;
use Enlixo\Roles\PdoAuditLogger;
use Enlixo\Support\ListQuery;

/**
 * Settings -> Recording Types and Academic -> Class Recordings (tutor/recording-types.php, tutor/recordings.php).
 * Every change is written to audit_logs (entity recording_type / recording) and shown in the Activity Log tab.
 */
final class ClassRecordingController
{
    public function __construct(private readonly App $app)
    {
    }

    private function types(): RecordingTypeRepository
    {
        return new RecordingTypeRepository($this->app->db());
    }

    private function recordings(): ClassRecordingRepository
    {
        return new ClassRecordingRepository($this->app->db(), $this->types());
    }

    private function manage(Request $r): AuthContext
    {
        $auth = $r->auth();
        $this->app->permissions()->require($auth, 'recordings.manage');
        return $auth;
    }

    private function log(Request $r, AuthContext $auth, string $action, string $entity, ?int $id, string $summary): void
    {
        try {
            (new PdoAuditLogger($this->app->db(), $r->ip))->log($auth->tenantId(), $auth->userId, $action, $entity, $id !== null ? (string) $id : null, ['summary' => $summary]);
        } catch (\Throwable $e) {
            error_log('recordings log: ' . $e->getMessage()); // a log line never stops the change
        }
    }

    private function activity(int $tutor, string $entity): array
    {
        $rows = $this->app->db()->all(
            "SELECT a.created_at, a.action, a.details, CONCAT_WS(' ', u.user_fname, u.user_lname) AS who
               FROM audit_logs a LEFT JOIN users u ON u.user_id = a.users_user_id
              WHERE a.tutor_id = :t AND a.entity = :e ORDER BY a.audit_log_id DESC LIMIT 200",
            ['t' => $tutor, 'e' => $entity],
        );
        return array_map(static fn (array $r): array => [
            'when' => (string) $r['created_at'], 'by' => trim((string) $r['who']) ?: 'Someone', 'action' => (string) $r['action'],
            'what' => (string) (json_decode((string) $r['details'], true)['summary'] ?? $r['action']),
        ], $rows);
    }

    // ------------------------------------------------------------------ recording types

    /** GET /api/v1/tutor/recording-types */
    public function typeIndex(Request $r): JsonResponse
    {
        $auth = $this->manage($r);
        $platforms = [];
        foreach (LinkChecker::PLATFORMS as $key => [$label, $embed, $help]) {
            $platforms[] = ['key' => $key, 'label' => $label, 'embeddable' => $embed, 'help' => $help];
        }
        return JsonResponse::ok([
            'types' => $this->types()->all($auth->tenantId()),
            'platforms' => $platforms,
            'log' => $this->activity($auth->tenantId(), 'recording_type'),
        ]);
    }

    /** POST /api/v1/tutor/recording-types  and  PUT /api/v1/tutor/recording-types/{id} */
    public function typeSave(Request $r): JsonResponse
    {
        $auth = $this->manage($r);
        $id = isset($r->routeParams['id']) ? (int) $r->param('id') : null;
        $old = $id ? $this->types()->find($auth->tenantId(), $id) : null;
        $t = $this->types()->save($auth->tenantId(), $id, $r->body, $auth->userId);
        $this->log($r, $auth, $id ? 'updated' : 'added', 'recording_type', $t['id'], $id
            ? ($old && $old['name'] !== $t['name'] ? "Renamed {$old['name']} to {$t['name']}" : "Changed {$t['name']}")
            : "Added {$t['name']} ({$t['platform_label']})");
        return JsonResponse::ok(['type' => $t, 'types' => $this->types()->all($auth->tenantId())]);
    }

    /** POST /api/v1/tutor/recording-types/{id}/status  { active: bool } */
    public function typeStatus(Request $r): JsonResponse
    {
        $auth = $this->manage($r);
        $on = filter_var($r->input('active'), FILTER_VALIDATE_BOOLEAN);
        $t = $this->types()->setActive($auth->tenantId(), (int) $r->param('id'), $on);
        $this->log($r, $auth, $on ? 'switched_on' : 'switched_off', 'recording_type', $t['id'], ($on ? 'Switched on ' : 'Switched off ') . $t['name']);
        return JsonResponse::ok(['types' => $this->types()->all($auth->tenantId())]);
    }

    /** DELETE /api/v1/tutor/recording-types/{id} */
    public function typeDelete(Request $r): JsonResponse
    {
        $auth = $this->manage($r);
        $t = $this->types()->delete($auth->tenantId(), (int) $r->param('id'));
        $this->log($r, $auth, 'deleted', 'recording_type', $t['id'], "Deleted {$t['name']}");
        return JsonResponse::ok(['types' => $this->types()->all($auth->tenantId())]);
    }

    /** POST /api/v1/tutor/recording-types/order  { ids: [...] } */
    public function typeOrder(Request $r): JsonResponse
    {
        $auth = $this->manage($r);
        $ids = $r->input('ids');
        if (!is_array($ids)) {
            throw new HttpException(422, 'validation_failed', 'Send the new order.');
        }
        $this->types()->reorder($auth->tenantId(), $ids);
        $types = $this->types()->all($auth->tenantId());
        $this->log($r, $auth, 'reordered', 'recording_type', null, 'New order: ' . implode(', ', array_column($types, 'name')));
        return JsonResponse::ok(['types' => $types]);
    }

    /** POST /api/v1/tutor/recording-types/check  { type, link }: try a link without saving */
    public function checkLink(Request $r): JsonResponse
    {
        $auth = $this->manage($r);
        return JsonResponse::ok($this->types()->verify($auth->tenantId(), (int) $r->input('type'), (string) $r->input('link', '')));
    }

    // ------------------------------------------------------------------ class recordings

    /** GET /api/v1/tutor/recordings  (?q, class, type, state, sort, dir, page, per_page) */
    public function index(Request $r): JsonResponse
    {
        $auth = $r->auth();
        $this->app->permissions()->require($auth, 'recordings.view');
        $q = ListQuery::fromArray($r->query, ClassRecordingRepository::FILTERS, ClassRecordingRepository::SORTS, 10);
        return $this->recordings()->list($auth->tenantId(), $q)->toResponse();
    }

    /** GET /api/v1/tutor/recordings/overview?class= : stat cards, classes and types for the filters and the form, log */
    public function overview(Request $r): JsonResponse
    {
        $auth = $r->auth();
        $this->app->permissions()->require($auth, 'recordings.view');
        $tutor = $auth->tenantId();
        $class = (int) $r->input('class', 0);
        $ent = $this->app->entitlements()->for($tutor);
        return JsonResponse::ok([
            'stats' => $this->recordings()->overview($tutor, $class > 0 ? $class : null),
            'classes' => $this->recordings()->classes($tutor),
            'types' => $this->types()->all($tutor),
            'can_manage' => $this->app->permissions()->can($auth, 'recordings.manage'),
            'can_expire' => $ent->has('recordings.expiry'),
            'log' => $this->activity($tutor, 'recording'),
        ]);
    }

    /** GET /api/v1/tutor/recordings/days?class= : class days a recording can be added to */
    public function days(Request $r): JsonResponse
    {
        $auth = $this->manage($r);
        return JsonResponse::ok($this->recordings()->classDays($auth->tenantId(), (int) $r->input('class', 0)));
    }

    /** GET /api/v1/tutor/recordings/{id} */
    public function show(Request $r): JsonResponse
    {
        $auth = $r->auth();
        $this->app->permissions()->require($auth, 'recordings.view');
        return JsonResponse::ok($this->recordings()->find($auth->tenantId(), (int) $r->param('id')) ?? throw HttpException::notFound('This recording was not found.'));
    }

    /** POST /api/v1/tutor/recordings  and  PUT /api/v1/tutor/recordings/{id} */
    public function save(Request $r): JsonResponse
    {
        $auth = $this->manage($r);
        $tutor = $auth->tenantId();
        $id = isset($r->routeParams['id']) ? (int) $r->param('id') : null;
        $res = $this->recordings()->save($tutor, $id, $r->body, $auth->userId, $this->app->entitlements()->for($tutor)->has('recordings.expiry'));
        $new = $res['new'];
        $this->log($r, $auth, $id ? 'updated' : 'added', 'recording', $new['id'], $id
            ? "Changed \"{$new['title']}\" ({$new['class']['name']})"
            : "Added \"{$new['title']}\" to {$new['class']['name']} ({$new['type']['name']})");
        return JsonResponse::ok($new, [], $id ? 200 : 201);
    }

    /** DELETE /api/v1/tutor/recordings/{id} */
    public function destroy(Request $r): JsonResponse
    {
        $auth = $this->manage($r);
        $old = $this->recordings()->delete($auth->tenantId(), (int) $r->param('id'));
        $this->log($r, $auth, 'deleted', 'recording', $old['id'], "Deleted \"{$old['title']}\" ({$old['class']['name']})");
        return JsonResponse::ok(null);
    }
}

<?php
declare(strict_types=1);

namespace Enlixo\Http\Controllers;

use Enlixo\App;
use Enlixo\Catalog\PermissionCatalog;
use Enlixo\Core\JsonResponse;
use Enlixo\Core\Request;
use Enlixo\Roles\Role;

final class RoleController
{
    public function __construct(private readonly App $app)
    {
    }

    /** GET /api/v1/permissions: catalogue grouped by module, with lock state for this tutor. */
    public function catalogue(Request $r): JsonResponse
    {
        $ent = $this->app->entitlements()->for($r->auth()->tenantId());
        $modules = [];
        foreach (PermissionCatalog::permissions() as $key => $p) {
            $modules[$p['module']][] = [
                'key' => $key,
                'name' => $p['name'],
                'feature' => $p['feature'] ?? null,
                'locked' => isset($p['feature']) && !$ent->has($p['feature']),
            ];
        }
        $out = [];
        foreach ($modules as $name => $perms) {
            $out[] = ['module' => $name, 'permissions' => $perms];
        }
        return JsonResponse::ok($out);
    }

    public function index(Request $r): JsonResponse
    {
        return JsonResponse::ok(array_map(static fn (Role $role) => $role->toArray(), $this->app->roles()->list($r->auth())));
    }

    public function store(Request $r): JsonResponse
    {
        return JsonResponse::ok($this->app->roles()->create($r->auth(), $r->body)->toArray(), [], 201);
    }

    public function update(Request $r): JsonResponse
    {
        return JsonResponse::ok($this->app->roles()->update($r->auth(), (int) $r->param('id'), $r->body)->toArray());
    }

    public function destroy(Request $r): JsonResponse
    {
        $moveTo = $r->input('move_to_role_id');
        $this->app->roles()->delete($r->auth(), (int) $r->param('id'), $moveTo !== null ? (int) $moveTo : null);
        return JsonResponse::ok(null);
    }
}

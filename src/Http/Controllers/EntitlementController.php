<?php
declare(strict_types=1);

namespace Enlixo\Http\Controllers;

use Enlixo\App;
use Enlixo\Core\JsonResponse;
use Enlixo\Core\Request;

final class EntitlementController
{
    public function __construct(private readonly App $app)
    {
    }

    /** GET /api/v1/me/access: package features (with lock state) + my permissions. Drives every lock badge. */
    public function show(Request $r): JsonResponse
    {
        $auth = $r->auth();
        return JsonResponse::ok($this->app->entitlements()->for($auth->tenantId())->toArray() + [
            'permissions' => $this->app->permissions()->effective($auth),
        ]);
    }
}

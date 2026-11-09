<?php
declare(strict_types=1);

namespace Enlixo\Roles;

use Enlixo\Catalog\PermissionCatalog;
use Enlixo\Core\AuthContext;
use Enlixo\Core\HttpException;
use Enlixo\Entitlements\EntitlementService;
use Enlixo\Entitlements\FeatureLockedException;

/**
 * Effective access = (role permissions) ∩ (permissions whose feature is in the tutor's package).
 * The tutor (owner) has every permission their package allows.
 */
final class PermissionService
{
    public function __construct(
        private readonly RoleRepository $roles,
        private readonly EntitlementService $entitlements,
    ) {
    }

    /** @return list<string> */
    public function effective(AuthContext $auth): array
    {
        if (!in_array($auth->kind, ['tutor', 'staff'], true) || $auth->tutorId === null) {
            return [];
        }
        $granted = $auth->isTutorOwner()
            ? array_keys(PermissionCatalog::permissions())
            : ($this->roles->roleOfStaff($auth->tutorId, $auth->userId)?->permissions ?? []);

        $ent = $this->entitlements->for($auth->tutorId);
        return array_values(array_filter($granted, static function (string $key) use ($ent) {
            $feature = PermissionCatalog::permissions()[$key]['feature'] ?? null;
            return $feature === null || $ent->has($feature);
        }));
    }

    public function can(AuthContext $auth, string $permission): bool
    {
        return in_array($permission, $this->effective($auth), true);
    }

    /**
     * @throws FeatureLockedException when the role allows it but the package doesn't (so the UI shows "upgrade")
     * @throws HttpException 403 otherwise
     */
    public function require(AuthContext $auth, string $permission): void
    {
        if ($this->can($auth, $permission)) {
            return;
        }
        $feature = PermissionCatalog::permissions()[$permission]['feature'] ?? null;
        if ($feature !== null && $auth->tutorId !== null && !$this->entitlements->has($auth->tutorId, $feature)) {
            throw new FeatureLockedException($feature);
        }
        throw HttpException::forbidden();
    }
}

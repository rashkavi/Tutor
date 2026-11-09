<?php
declare(strict_types=1);

namespace Enlixo\Roles;

use Enlixo\Catalog\PermissionCatalog;
use Enlixo\Core\AuthContext;
use Enlixo\Core\HttpException;
use Enlixo\Entitlements\EntitlementService;

/** Settings → Roles. Only callers with `settings.roles` (owner on GROW/PRO by default). */
final class RoleService
{
    public function __construct(
        private readonly RoleRepository $roles,
        private readonly PermissionService $permissions,
        private readonly EntitlementService $entitlements,
        private readonly AuditLogger $audit,
    ) {
    }

    /** @return list<Role> */
    public function list(AuthContext $auth): array
    {
        $this->permissions->require($auth, 'settings.roles');
        // The account owner is shown as a built-in role that always has everything the package allows.
        $owner = new Role(0, $auth->tenantId(), 'Owner', 'You. Full access to everything in your package.', true, $this->permissions->effective(new AuthContext($auth->tenantId(), 'tutor', $auth->tenantId())), 1);
        return [$owner, ...$this->roles->forTutor($auth->tenantId())];
    }

    public function create(AuthContext $auth, array $input): Role
    {
        $this->permissions->require($auth, 'settings.roles');
        [$name, $description, $perms] = $this->validate($auth->tenantId(), $input, null);
        $id = $this->roles->create($auth->tenantId(), $name, $description, $perms);
        $this->audit->log($auth->tenantId(), $auth->userId, 'role.created', 'tutor_role', (string) $id, ['name' => $name, 'permissions' => $perms]);
        return $this->roles->find($auth->tenantId(), $id);
    }

    public function update(AuthContext $auth, int $roleId, array $input): Role
    {
        $this->permissions->require($auth, 'settings.roles');
        $role = $this->editableRole($auth, $roleId);
        [$name, $description, $perms] = $this->validate($auth->tenantId(), $input, $roleId);
        $this->roles->update($roleId, $name, $description, $perms);
        $this->audit->log($auth->tenantId(), $auth->userId, 'role.updated', 'tutor_role', (string) $roleId, [
            'added' => array_values(array_diff($perms, $role->permissions)),
            'removed' => array_values(array_diff($role->permissions, $perms)),
        ]);
        return $this->roles->find($auth->tenantId(), $roleId);
    }

    public function delete(AuthContext $auth, int $roleId, ?int $moveToRoleId): void
    {
        $this->permissions->require($auth, 'settings.roles');
        $role = $this->editableRole($auth, $roleId);
        if ($role->userCount > 0) {
            if ($moveToRoleId === null || $moveToRoleId === $roleId || !$this->roles->find($auth->tenantId(), $moveToRoleId)) {
                throw HttpException::validation(['move_to_role_id' => sprintf('%d staff use this role. Choose a role to move them to.', $role->userCount)]);
            }
        }
        $this->roles->delete($roleId, $role->userCount > 0 ? $moveToRoleId : null);
        $this->audit->log($auth->tenantId(), $auth->userId, 'role.deleted', 'tutor_role', (string) $roleId, ['name' => $role->name, 'moved_to' => $moveToRoleId]);
    }

    private function editableRole(AuthContext $auth, int $roleId): Role
    {
        $role = $this->roles->find($auth->tenantId(), $roleId) ?? throw HttpException::notFound('Role not found.');
        if ($role->isSystem) {
            throw HttpException::forbidden('Built-in roles can\'t be changed.');
        }
        return $role;
    }

    /** @return array{0:string,1:?string,2:list<string>} */
    private function validate(int $tutorId, array $input, ?int $roleId): array
    {
        $errors = [];
        $name = trim((string) ($input['name'] ?? ''));
        $description = trim((string) ($input['description'] ?? '')) ?: null;
        $perms = array_values(array_unique(array_filter((array) ($input['permissions'] ?? []), 'is_string')));

        if ($name === '' || mb_strlen($name) > 60) {
            $errors['name'] = 'Give the role a name (up to 60 characters).';
        } elseif (strcasecmp($name, 'Owner') === 0 || $this->roles->nameExists($tutorId, $name, $roleId)) {
            $errors['name'] = 'A role with this name already exists.';
        }
        if ($description !== null && mb_strlen($description) > 200) {
            $errors['description'] = 'Keep the description under 200 characters.';
        }
        $unknown = array_filter($perms, static fn ($p) => !PermissionCatalog::exists($p));
        if ($unknown) {
            $errors['permissions'] = 'Unknown permissions: ' . implode(', ', $unknown);
        } elseif ($perms === []) {
            $errors['permissions'] = 'Pick at least one thing this role can do.';
        }
        if ($errors) {
            throw HttpException::validation($errors);
        }
        return [$name, $description, $perms];
    }
}

<?php
declare(strict_types=1);

namespace Enlixo\Roles;

use Enlixo\Core\Database;

final class PdoRoleRepository implements RoleRepository
{
    public function __construct(private readonly Database $db)
    {
    }

    public function forTutor(int $tutorId): array
    {
        $rows = $this->db->all(
            'SELECT r.*, (SELECT COUNT(*) FROM tutor_staff s WHERE s.tutor_roles_tutor_role_id = r.tutor_role_id) AS user_count
               FROM tutor_roles r WHERE r.tutor_id = :t ORDER BY r.is_system DESC, r.name',
            ['t' => $tutorId],
        );
        return array_map(fn (array $r) => $this->hydrate($r), $rows);
    }

    public function find(int $tutorId, int $roleId): ?Role
    {
        $row = $this->db->one(
            'SELECT r.*, (SELECT COUNT(*) FROM tutor_staff s WHERE s.tutor_roles_tutor_role_id = r.tutor_role_id) AS user_count
               FROM tutor_roles r WHERE r.tutor_id = :t AND r.tutor_role_id = :id',
            ['t' => $tutorId, 'id' => $roleId],
        );
        return $row ? $this->hydrate($row) : null;
    }

    public function nameExists(int $tutorId, string $name, ?int $exceptRoleId = null): bool
    {
        return (bool) $this->db->value(
            'SELECT 1 FROM tutor_roles WHERE tutor_id = :t AND name = :n AND tutor_role_id <> :id',
            ['t' => $tutorId, 'n' => $name, 'id' => $exceptRoleId ?? 0],
        );
    }

    public function create(int $tutorId, string $name, ?string $description, array $permissions): int
    {
        return $this->db->transaction(function () use ($tutorId, $name, $description, $permissions) {
            $id = $this->db->insert(
                'INSERT INTO tutor_roles (tutor_id, name, description) VALUES (:t, :n, :d)',
                ['t' => $tutorId, 'n' => $name, 'd' => $description],
            );
            $this->syncPermissions($id, $permissions);
            return $id;
        });
    }

    public function update(int $roleId, string $name, ?string $description, array $permissions): void
    {
        $this->db->transaction(function () use ($roleId, $name, $description, $permissions) {
            $this->db->execute('UPDATE tutor_roles SET name = :n, description = :d WHERE tutor_role_id = :id', ['n' => $name, 'd' => $description, 'id' => $roleId]);
            $this->syncPermissions($roleId, $permissions);
        });
    }

    public function delete(int $roleId, ?int $moveToRoleId): void
    {
        $this->db->transaction(function () use ($roleId, $moveToRoleId) {
            if ($moveToRoleId !== null) {
                $this->db->execute('UPDATE tutor_staff SET tutor_roles_tutor_role_id = :to WHERE tutor_roles_tutor_role_id = :from', ['to' => $moveToRoleId, 'from' => $roleId]);
            }
            $this->db->execute('DELETE FROM tutor_roles WHERE tutor_role_id = :id', ['id' => $roleId]);
        });
    }

    public function roleOfStaff(int $tutorId, int $userId): ?Role
    {
        $roleId = $this->db->value(
            'SELECT tutor_roles_tutor_role_id FROM tutor_staff WHERE tutor_id = :t AND staff_user_id = :u AND status_status_id = 1',
            ['t' => $tutorId, 'u' => $userId],
        );
        return $roleId === null ? null : $this->find($tutorId, (int) $roleId);
    }

    /** @param list<string> $permissions */
    private function syncPermissions(int $roleId, array $permissions): void
    {
        $this->db->execute('DELETE FROM tutor_role_permissions WHERE tutor_roles_tutor_role_id = :id', ['id' => $roleId]);
        if ($permissions === []) {
            return;
        }
        $placeholders = implode(',', array_fill(0, count($permissions), '?'));
        $stmt = $this->db->pdo()->prepare(
            "INSERT INTO tutor_role_permissions (tutor_roles_tutor_role_id, permissions_permission_id)
             SELECT ?, permission_id FROM permissions WHERE permission_key IN ($placeholders)",
        );
        $stmt->execute([$roleId, ...$permissions]);
    }

    private function hydrate(array $row): Role
    {
        $permissions = array_column($this->db->all(
            'SELECT p.permission_key FROM tutor_role_permissions rp JOIN permissions p ON p.permission_id = rp.permissions_permission_id
              WHERE rp.tutor_roles_tutor_role_id = :id ORDER BY p.sort_order',
            ['id' => $row['tutor_role_id']],
        ), 'permission_key');

        return new Role((int) $row['tutor_role_id'], (int) $row['tutor_id'], $row['name'], $row['description'], (bool) $row['is_system'], $permissions, (int) ($row['user_count'] ?? 0));
    }
}

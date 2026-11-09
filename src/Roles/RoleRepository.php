<?php
declare(strict_types=1);

namespace Enlixo\Roles;

interface RoleRepository
{
    /** @return list<Role> */
    public function forTutor(int $tutorId): array;

    public function find(int $tutorId, int $roleId): ?Role;

    public function nameExists(int $tutorId, string $name, ?int $exceptRoleId = null): bool;

    /** @param list<string> $permissions */
    public function create(int $tutorId, string $name, ?string $description, array $permissions): int;

    /** @param list<string> $permissions */
    public function update(int $roleId, string $name, ?string $description, array $permissions): void;

    /** Moves the role's staff to $moveToRoleId, then deletes it. */
    public function delete(int $roleId, ?int $moveToRoleId): void;

    /** Role of a staff user inside a tutor's account, or null if not staff there. */
    public function roleOfStaff(int $tutorId, int $userId): ?Role;
}

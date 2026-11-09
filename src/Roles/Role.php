<?php
declare(strict_types=1);

namespace Enlixo\Roles;

final class Role
{
    /** @param list<string> $permissions */
    public function __construct(
        public readonly int $id,
        public readonly int $tutorId,
        public readonly string $name,
        public readonly ?string $description,
        public readonly bool $isSystem,
        public readonly array $permissions,
        public readonly int $userCount = 0,
    ) {
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'is_system' => $this->isSystem,
            'permissions' => $this->permissions,
            'user_count' => $this->userCount,
        ];
    }
}

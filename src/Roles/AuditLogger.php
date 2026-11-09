<?php
declare(strict_types=1);

namespace Enlixo\Roles;

interface AuditLogger
{
    public function log(?int $tutorId, int $userId, string $action, string $entity, ?string $entityId, array $details = []): void;
}

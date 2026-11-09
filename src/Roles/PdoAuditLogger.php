<?php
declare(strict_types=1);

namespace Enlixo\Roles;

use Enlixo\Core\Database;

final class PdoAuditLogger implements AuditLogger
{
    public function __construct(private readonly Database $db, private readonly ?string $ip = null)
    {
    }

    public function log(?int $tutorId, int $userId, string $action, string $entity, ?string $entityId, array $details = []): void
    {
        $this->db->execute(
            'INSERT INTO audit_logs (tutor_id, users_user_id, action, entity, entity_id, details, ip_address) VALUES (:t, :u, :a, :e, :eid, :d, :ip)',
            ['t' => $tutorId, 'u' => $userId, 'a' => $action, 'e' => $entity, 'eid' => $entityId, 'd' => json_encode($details), 'ip' => $this->ip],
        );
    }
}

<?php
declare(strict_types=1);

namespace Enlixo\Scheduling\Zoom;

use Enlixo\Core\Crypto;
use Enlixo\Core\Database;
use Enlixo\Entitlements\EntitlementService;
use Enlixo\Http\Client\HttpClient;
use Enlixo\Scheduling\MeetingProvider;
use Enlixo\Scheduling\MeetingProviderResolver;

/**
 * Picks how to create a tutor's meetings:
 *  1. ENVIIZ Zoom add-on → our Server-to-Server app, hosted by the tutor's email
 *  2. Tutor's own connected Zoom (existing `integrations` row named Zoom) → user OAuth
 */
final class ZoomProviderResolver implements MeetingProviderResolver
{
    public function __construct(
        private readonly Database $db,
        private readonly HttpClient $http,
        private readonly EntitlementService $entitlements,
        private readonly ?Crypto $crypto,
        private readonly array $zoomConfig,
    ) {
    }

    public function forTutor(int $tutorId): ?MeetingProvider
    {
        $ent = $this->entitlements->for($tutorId);

        if ($ent->has('zoom.enviiz_account') && $this->zoomConfig['s2s_client_id'] !== '') {
            $email = (string) $this->db->value('SELECT user_email FROM users WHERE user_id = :id', ['id' => $tutorId]);
            return new ZoomClient($this->http, new ServerToServerTokenSource(
                $this->http, $this->zoomConfig['s2s_account_id'], $this->zoomConfig['s2s_client_id'], $this->zoomConfig['s2s_client_secret'], $email,
            ));
        }

        if (!$ent->has('zoom.tutor_integration')) {
            return null;
        }
        $conn = $this->db->one(
            'SELECT i.integration_id, ic.integration_connection_id FROM integrations i
               JOIN integration_connections ic ON ic.integration_id = i.integration_id
              WHERE i.users_user_id = :t AND i.name = "Zoom" AND i.status_status_id = 1
              ORDER BY ic.connected_at DESC LIMIT 1',
            ['t' => $tutorId],
        );
        if ($conn === null) {
            return null;
        }
        return new ZoomClient($this->http, new UserOAuthTokenSource(
            $this->db, $this->http, $this->crypto, (int) $conn['integration_connection_id'],
            $this->zoomConfig['oauth_client_id'], $this->zoomConfig['oauth_client_secret'],
        ), (int) $conn['integration_id']);
    }
}

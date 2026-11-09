<?php
declare(strict_types=1);

namespace Enlixo\Scheduling\Zoom;

use Enlixo\Core\Crypto;
use Enlixo\Core\Database;
use Enlixo\Http\Client\HttpClient;

/**
 * Tutor connected their own Zoom (all plans). Tokens live in the existing `integration_connections` table.
 * Refreshes automatically when the access token is about to expire.
 */
final class UserOAuthTokenSource implements TokenSource
{
    public function __construct(
        private readonly Database $db,
        private readonly HttpClient $http,
        private readonly ?Crypto $crypto,
        private readonly int $connectionId,
        private readonly string $clientId,
        private readonly string $clientSecret,
    ) {
    }

    public function accessToken(): string
    {
        $row = $this->db->one('SELECT * FROM integration_connections WHERE integration_connection_id = :id', ['id' => $this->connectionId])
            ?? throw new ZoomException('Zoom connection not found.');

        if ($row['token_expires_at'] !== null && strtotime($row['token_expires_at']) > time() + 300) {
            return $this->reveal($row['access_token']);
        }

        $res = $this->http->request('POST', 'https://zoom.us/oauth/token', [
            'Authorization' => 'Basic ' . base64_encode("{$this->clientId}:{$this->clientSecret}"),
            'Content-Type' => 'application/x-www-form-urlencoded',
        ], http_build_query(['grant_type' => 'refresh_token', 'refresh_token' => $this->reveal($row['refresh_token'])]));

        if ($res['status'] !== 200 || empty($res['body']['access_token'])) {
            throw new ZoomException('Zoom connection expired. Please reconnect Zoom in Settings → Integrations.', $res['status']);
        }
        $this->db->execute(
            'UPDATE integration_connections SET access_token = :a, refresh_token = :r, token_expires_at = :e WHERE integration_connection_id = :id',
            [
                'a' => $this->hide($res['body']['access_token']),
                'r' => $this->hide($res['body']['refresh_token'] ?? $this->reveal($row['refresh_token'])),
                'e' => date('Y-m-d H:i:s', time() + (int) ($res['body']['expires_in'] ?? 3600)),
                'id' => $this->connectionId,
            ],
        );
        return $res['body']['access_token'];
    }

    public function userId(): string
    {
        return 'me';
    }

    /** Existing rows may be plain text; new ones are encrypted when an APP_KEY is set. */
    private function reveal(?string $value): string
    {
        if ($value === null || $this->crypto === null) {
            return (string) $value;
        }
        try {
            return $this->crypto->decrypt($value);
        } catch (\RuntimeException) {
            return $value;
        }
    }

    private function hide(string $value): string
    {
        return $this->crypto?->encrypt($value) ?? $value;
    }
}

<?php
declare(strict_types=1);

namespace Enlixo\Scheduling\Zoom;

use Enlixo\Http\Client\HttpClient;

/** ENVIIZ Zoom Account add-on: our Server-to-Server OAuth app hosts meetings for the tutor's licensed user. */
final class ServerToServerTokenSource implements TokenSource
{
    private ?string $token = null;
    private int $expiresAt = 0;

    public function __construct(
        private readonly HttpClient $http,
        private readonly string $accountId,
        private readonly string $clientId,
        private readonly string $clientSecret,
        private readonly string $hostEmail,
    ) {
    }

    public function accessToken(): string
    {
        if ($this->token !== null && time() < $this->expiresAt - 300) {
            return $this->token;
        }
        $res = $this->http->request('POST', 'https://zoom.us/oauth/token?grant_type=account_credentials&account_id=' . rawurlencode($this->accountId), [
            'Authorization' => 'Basic ' . base64_encode("{$this->clientId}:{$this->clientSecret}"),
        ]);
        if ($res['status'] !== 200 || empty($res['body']['access_token'])) {
            throw new ZoomException('Could not get a Zoom token (server-to-server).', $res['status']);
        }
        $this->token = $res['body']['access_token'];
        $this->expiresAt = time() + (int) ($res['body']['expires_in'] ?? 3600);
        return $this->token;
    }

    public function userId(): string
    {
        return $this->hostEmail;
    }
}

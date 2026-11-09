<?php
declare(strict_types=1);

namespace Enlixo\Scheduling\Zoom;

use Enlixo\Http\Client\HttpClient;
use Enlixo\Scheduling\CreatedMeeting;
use Enlixo\Scheduling\MeetingProvider;
use Enlixo\Scheduling\MeetingRequest;

final class ZoomClient implements MeetingProvider
{
    private const API = 'https://api.zoom.us/v2';

    public function __construct(
        private readonly HttpClient $http,
        private readonly TokenSource $tokens,
        private readonly ?int $integrationId = null,
        private readonly int $maxAttempts = 3,
    ) {
    }

    public function createMeeting(MeetingRequest $request): CreatedMeeting
    {
        $payload = json_encode([
            'topic' => mb_substr($request->topic, 0, 200),
            'type' => 2, // scheduled meeting
            'start_time' => $request->startsAt->format('Y-m-d\TH:i:s'),
            'timezone' => $request->timezone,
            'duration' => $request->durationMinutes,
            'settings' => [
                'join_before_host' => false,
                'waiting_room' => true,
                'mute_upon_entry' => true,
                'approval_type' => 2, // no registration
                'auto_recording' => 'cloud',
            ],
        ]);

        $res = $this->withRetry(fn () => $this->http->request('POST', self::API . '/users/' . rawurlencode($this->tokens->userId()) . '/meetings', [
            'Authorization' => 'Bearer ' . $this->tokens->accessToken(),
            'Content-Type' => 'application/json',
        ], $payload));

        if ($res['status'] !== 201 || empty($res['body']['id'])) {
            throw new ZoomException('Zoom refused to create the meeting: ' . ($res['body']['message'] ?? 'HTTP ' . $res['status']), $res['status']);
        }
        return new CreatedMeeting((string) $res['body']['id'], $res['body']['join_url'], $res['body']['start_url'], $res['body']['password'] ?? null);
    }

    public function integrationId(): ?int
    {
        return $this->integrationId;
    }

    /** Retries on 429 / 5xx / network errors with exponential backoff (1s, 2s, 4s). */
    private function withRetry(callable $call): array
    {
        $attempt = 0;
        while (true) {
            $attempt++;
            try {
                $res = $call();
                if (($res['status'] === 429 || $res['status'] >= 500) && $attempt < $this->maxAttempts) {
                    usleep((2 ** ($attempt - 1)) * 1_000_000);
                    continue;
                }
                return $res;
            } catch (\RuntimeException $e) {
                if ($attempt >= $this->maxAttempts) {
                    throw $e;
                }
                usleep((2 ** ($attempt - 1)) * 1_000_000);
            }
        }
    }
}

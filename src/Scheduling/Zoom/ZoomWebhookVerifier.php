<?php
declare(strict_types=1);

namespace Enlixo\Scheduling\Zoom;

/** Zoom webhook security: x-zm-signature check and the endpoint.url_validation challenge. */
final class ZoomWebhookVerifier
{
    public function __construct(private readonly string $secretToken, private readonly int $toleranceSeconds = 300)
    {
    }

    public function isValid(?string $signature, ?string $timestamp, string $rawBody, ?int $now = null): bool
    {
        if ($signature === null || $timestamp === null || !ctype_digit($timestamp) || $this->secretToken === '') {
            return false;
        }
        if (abs(($now ?? time()) - (int) $timestamp) > $this->toleranceSeconds) {
            return false;
        }
        $expected = 'v0=' . hash_hmac('sha256', "v0:{$timestamp}:{$rawBody}", $this->secretToken);
        return hash_equals($expected, $signature);
    }

    /** Response body for event `endpoint.url_validation`. */
    public function validationResponse(string $plainToken): array
    {
        return ['plainToken' => $plainToken, 'encryptedToken' => hash_hmac('sha256', $plainToken, $this->secretToken)];
    }
}

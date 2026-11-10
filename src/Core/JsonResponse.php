<?php
declare(strict_types=1);

namespace Enlixo\Core;

/** Standard envelope: { ok: true, data, meta } or { ok: false, error: { code, message, details } } */
final class JsonResponse implements Responder
{
    public function __construct(public readonly mixed $payload, public readonly int $status = 200)
    {
    }

    public static function ok(mixed $data, array $meta = [], int $status = 200): self
    {
        $payload = ['ok' => true, 'data' => $data];
        if ($meta !== []) {
            $payload['meta'] = $meta;
        }
        return new self($payload, $status);
    }

    public static function error(HttpException $e): self
    {
        return new self(['ok' => false, 'error' => [
            'code' => $e->errorCode,
            'message' => $e->getMessage(),
            'details' => (object) $e->details,
        ]], $e->status);
    }

    public function send(): void
    {
        http_response_code($this->status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($this->payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}

<?php
declare(strict_types=1);

namespace Enlixo\Http\Client;

final class CurlHttpClient implements HttpClient
{
    public function __construct(private readonly int $timeoutSeconds = 20)
    {
    }

    public function request(string $method, string $url, array $headers = [], ?string $body = null): array
    {
        $ch = curl_init($url);
        $lines = [];
        foreach ($headers as $k => $v) {
            $lines[] = "$k: $v";
        }
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $lines,
            CURLOPT_TIMEOUT => $this->timeoutSeconds,
        ]);
        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }
        $raw = curl_exec($ch);
        if ($raw === false) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new \RuntimeException("HTTP request failed: $error");
        }
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);
        $decoded = json_decode($raw, true);
        return ['status' => $status, 'body' => is_array($decoded) ? $decoded : null, 'raw' => $raw];
    }
}

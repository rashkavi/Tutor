<?php
declare(strict_types=1);

namespace Enlixo\Http\Client;

interface HttpClient
{
    /** @return array{status:int, body:array<string,mixed>|null, raw:string} */
    public function request(string $method, string $url, array $headers = [], ?string $body = null): array;
}

<?php
declare(strict_types=1);

namespace Enlixo\Core;

final class Request
{
    /** @param array<string,string> $routeParams */
    public function __construct(
        public readonly string $method,
        public readonly string $path,
        public readonly array $query = [],
        public readonly array $body = [],
        public readonly array $headers = [],
        public readonly string $rawBody = '',
        public readonly ?string $ip = null,
        public array $routeParams = [],
        /** Set by auth middleware */
        public ?AuthContext $auth = null,
        /** Uploaded files ($_FILES) */
        public readonly array $files = [],
    ) {
    }

    public static function fromGlobals(): self
    {
        $raw = (string) file_get_contents('php://input');
        $headers = [];
        foreach ($_SERVER as $key => $value) {
            if (str_starts_with($key, 'HTTP_')) {
                $headers[strtolower(str_replace('_', '-', substr($key, 5)))] = $value;
            }
        }
        $body = $_POST;
        if (str_contains($_SERVER['CONTENT_TYPE'] ?? '', 'application/json')) {
            $body = json_decode($raw, true) ?: [];
        }
        $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
        return new self($_SERVER['REQUEST_METHOD'] ?? 'GET', $path, $_GET, $body, $headers, $raw, $_SERVER['REMOTE_ADDR'] ?? null, [], null, $_FILES);
    }

    /** Same request with a different path (strips a sub-folder prefix like /ENLIXO-Tutor). */
    public function withPath(string $path): self
    {
        return new self($this->method, $path, $this->query, $this->body, $this->headers, $this->rawBody, $this->ip, $this->routeParams, $this->auth, $this->files);
    }

    public function input(string $key, mixed $default = null): mixed
    {
        return $this->body[$key] ?? $this->query[$key] ?? $default;
    }

    public function param(string $key): string
    {
        return $this->routeParams[$key] ?? throw HttpException::notFound();
    }

    public function header(string $name): ?string
    {
        return $this->headers[strtolower($name)] ?? null;
    }

    public function auth(): AuthContext
    {
        return $this->auth ?? throw new HttpException(401, 'unauthenticated', 'Please log in.');
    }
}

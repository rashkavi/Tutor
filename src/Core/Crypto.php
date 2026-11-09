<?php
declare(strict_types=1);

namespace Enlixo\Core;

/** AES-256-GCM for secrets at rest (Zoom start URLs, OAuth tokens). */
final class Crypto
{
    private string $key;

    public function __construct(string $base64Key)
    {
        $key = base64_decode($base64Key, true);
        if ($key === false || strlen($key) < 32) {
            throw new \InvalidArgumentException('APP_KEY must be at least 32 random bytes, base64 encoded.');
        }
        $this->key = substr($key, 0, 32);
    }

    public function encrypt(string $plain): string
    {
        $iv = random_bytes(12);
        $cipher = openssl_encrypt($plain, 'aes-256-gcm', $this->key, OPENSSL_RAW_DATA, $iv, $tag);
        return base64_encode($iv . $tag . $cipher);
    }

    public function decrypt(string $encoded): string
    {
        $raw = base64_decode($encoded, true);
        if ($raw === false || strlen($raw) < 28) {
            throw new \RuntimeException('Invalid encrypted value.');
        }
        $plain = openssl_decrypt(substr($raw, 28), 'aes-256-gcm', $this->key, OPENSSL_RAW_DATA, substr($raw, 0, 12), substr($raw, 12, 16));
        if ($plain === false) {
            throw new \RuntimeException('Could not decrypt value.');
        }
        return $plain;
    }
}

<?php
declare(strict_types=1);

namespace Enlixo\Messaging;

/**
 * Encrypts secrets kept in the database (a tutor's WhatsApp access token) with AES-256-GCM.
 * Key: APP_KEY when set, else the key file secure/messaging.key (made on first use; keep it out of git and back it up
 * with the database, or saved tokens can no longer be read and tutors must connect again).
 */
final class SecretBox
{
    public function __construct(private readonly string $appKey, private readonly string $keyFile)
    {
    }

    private function key(): string
    {
        if ($this->appKey !== '') {
            return hash('sha256', $this->appKey, true);
        }
        if (!is_file($this->keyFile)) {
            @mkdir(dirname($this->keyFile), 0700, true);
            if (@file_put_contents($this->keyFile, base64_encode(random_bytes(32))) === false) {
                throw new \RuntimeException('The key file for saved secrets could not be created (' . $this->keyFile . ').');
            }
            @chmod($this->keyFile, 0600);
        }
        $raw = base64_decode(trim((string) file_get_contents($this->keyFile)), true);
        if ($raw === false || strlen($raw) !== 32) {
            throw new \RuntimeException('The key file for saved secrets is damaged.');
        }
        return $raw;
    }

    public function seal(string $plain): string
    {
        $iv = random_bytes(12);
        $tag = '';
        $cipher = openssl_encrypt($plain, 'aes-256-gcm', $this->key(), OPENSSL_RAW_DATA, $iv, $tag);
        return 'v1:' . base64_encode($iv . $tag . $cipher);
    }

    public function open(?string $sealed): ?string
    {
        if (!$sealed || !str_starts_with($sealed, 'v1:')) {
            return null;
        }
        $raw = base64_decode(substr($sealed, 3), true);
        if ($raw === false || strlen($raw) < 29) {
            return null;
        }
        $plain = openssl_decrypt(substr($raw, 28), 'aes-256-gcm', $this->key(), OPENSSL_RAW_DATA, substr($raw, 0, 12), substr($raw, 12, 16));
        return $plain === false ? null : $plain;
    }
}

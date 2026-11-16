<?php
declare(strict_types=1);

namespace Enlixo\Messaging;

/** 'log' driver of the Mailer and WhatsAppCloud: one JSON line per message in storage/messages.log. */
final class MessageLog
{
    public static function write(string $file, array $entry): void
    {
        @mkdir(dirname($file), 0775, true);
        @file_put_contents($file, json_encode(['at' => date('c')] + $entry, JSON_UNESCAPED_UNICODE) . "\n", FILE_APPEND | LOCK_EX);
    }
}

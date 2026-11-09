<?php
declare(strict_types=1);

namespace Enlixo\Core;

class HttpException extends \RuntimeException
{
    public function __construct(
        public readonly int $status,
        public readonly string $errorCode,
        string $message,
        public readonly array $details = [],
    ) {
        parent::__construct($message, $status);
    }

    public static function notFound(string $message = 'Not found'): self
    {
        return new self(404, 'not_found', $message);
    }

    public static function forbidden(string $message = 'You do not have permission to do this.'): self
    {
        return new self(403, 'forbidden', $message);
    }

    public static function validation(array $errors): self
    {
        return new self(422, 'validation_failed', 'Please fix the highlighted fields.', ['fields' => $errors]);
    }
}

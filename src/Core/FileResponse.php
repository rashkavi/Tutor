<?php
declare(strict_types=1);

namespace Enlixo\Core;

/** A file download from the API (for example the bulk upload template). Errors still go out as JsonResponse. */
final class FileResponse implements Responder
{
    public function __construct(public readonly string $body, public readonly string $filename, public readonly string $type, public readonly bool $inline = false)
    {
    }

    public function send(): void
    {
        http_response_code(200);
        header('Content-Type: ' . $this->type);
        header('Content-Disposition: ' . ($this->inline ? 'inline' : 'attachment') . '; filename="' . str_replace(['"', "\r", "\n"], '', $this->filename) . '"');
        header('Content-Length: ' . strlen($this->body));
        header('Cache-Control: no-store');
        echo $this->body;
    }
}

<?php
declare(strict_types=1);

namespace Enlixo\Core;

/** What a route handler returns: JsonResponse (the API envelope) or FileResponse (a download). */
interface Responder
{
    public function send(): void;
}

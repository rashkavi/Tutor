<?php
declare(strict_types=1);

namespace Enlixo\Scheduling\Zoom;

interface TokenSource
{
    public function accessToken(): string;

    /** Zoom user to create meetings for: 'me' for a tutor's own OAuth, or an email for S2S. */
    public function userId(): string;
}

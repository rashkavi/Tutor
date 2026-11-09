<?php
declare(strict_types=1);

namespace Enlixo\Scheduling;

interface MeetingProviderResolver
{
    /** Provider for this tutor, or null if they haven't connected Zoom / bought the add-on. */
    public function forTutor(int $tutorId): ?MeetingProvider;
}

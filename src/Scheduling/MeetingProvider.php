<?php
declare(strict_types=1);

namespace Enlixo\Scheduling;

/** Anything that can create an online class meeting (Zoom today; Google Meet / Teams later). */
interface MeetingProvider
{
    public function createMeeting(MeetingRequest $request): CreatedMeeting;

    /** integrations.integration_id used, for bookkeeping */
    public function integrationId(): ?int;
}

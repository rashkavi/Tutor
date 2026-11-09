<?php
declare(strict_types=1);

namespace Enlixo\Scheduling;

final class CreatedMeeting
{
    public function __construct(
        public readonly string $meetingId,
        public readonly string $joinUrl,
        public readonly string $startUrl,
        public readonly ?string $passcode,
    ) {
    }
}

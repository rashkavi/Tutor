<?php
declare(strict_types=1);

namespace Enlixo\Scheduling;

final class MeetingRequest
{
    public function __construct(
        public readonly string $topic,
        public readonly \DateTimeImmutable $startsAt,
        public readonly int $durationMinutes,
        public readonly string $timezone = 'Asia/Colombo',
    ) {
    }
}

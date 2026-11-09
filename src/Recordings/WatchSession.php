<?php
declare(strict_types=1);

namespace Enlixo\Recordings;

final class WatchSession
{
    public function __construct(
        public readonly int $id,
        public readonly string $token,
        public readonly int $studentId,
        public readonly string $kind,
        public readonly int $recordingId,
        public readonly \DateTimeImmutable $lastHeartbeatAt,
        public readonly int $watchedSeconds,
    ) {
    }
}

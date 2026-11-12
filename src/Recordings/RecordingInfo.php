<?php
declare(strict_types=1);

namespace Enlixo\Recordings;

final class RecordingInfo
{
    public function __construct(
        public readonly string $kind, // 'lecture' | 'pre'
        public readonly int $id,
        public readonly int $classId,
        public readonly int $tutorId,
        public readonly string $title,
        public readonly int $durationSeconds,
        public readonly ?\DateTimeImmutable $unlockAt = null, // "Opens on" set by the tutor (every plan)
    ) {
    }
}

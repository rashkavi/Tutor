<?php
declare(strict_types=1);

namespace Enlixo\Recordings;

/** Extra time / later expiry the tutor gave one student. */
final class StudentOverride
{
    public function __construct(
        public readonly int $extraMinutes,
        public readonly ?\DateTimeImmutable $expiresAt = null,
    ) {
    }
}

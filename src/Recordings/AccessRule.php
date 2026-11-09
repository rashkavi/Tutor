<?php
declare(strict_types=1);

namespace Enlixo\Recordings;

/** How long a recording may be watched, per student. */
final class AccessRule
{
    public const UNLIMITED = 'unlimited';
    public const MULTIPLIER = 'multiplier';
    public const FIXED_MINUTES = 'fixed_minutes';

    public function __construct(
        public readonly string $mode,
        public readonly ?float $multiplier = null,
        public readonly ?int $fixedMinutes = null,
        public readonly ?\DateTimeImmutable $availableFrom = null,
        public readonly ?\DateTimeImmutable $expiresAt = null,
    ) {
    }

    public static function default(float $multiplier): self
    {
        return new self(self::MULTIPLIER, $multiplier);
    }

    /** Allowed seconds before extra time, or null for unlimited. */
    public function baseAllowance(int $durationSeconds): ?int
    {
        return match ($this->mode) {
            self::UNLIMITED => null,
            self::FIXED_MINUTES => max(0, (int) $this->fixedMinutes) * 60,
            default => (int) round($durationSeconds * max(1.0, (float) $this->multiplier)),
        };
    }
}

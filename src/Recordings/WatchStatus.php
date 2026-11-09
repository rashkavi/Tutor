<?php
declare(strict_types=1);

namespace Enlixo\Recordings;

/** What the student sees on the meter and whether the player may run. */
final class WatchStatus
{
    public const AVAILABLE = 'available';
    public const WARNING = 'warning';           // >= 80% used
    public const EXHAUSTED = 'exhausted';       // watch time used up
    public const EXPIRED = 'expired';           // past expiry date
    public const NOT_YET = 'not_yet_available';
    public const NO_ACCESS = 'no_access';       // not enrolled / class inactive

    public function __construct(
        public readonly string $state,
        public readonly int $usedSeconds,
        public readonly ?int $allowedSeconds,
        public readonly ?\DateTimeImmutable $expiresAt,
        public readonly ?\DateTimeImmutable $availableFrom = null,
    ) {
    }

    public function canPlay(): bool
    {
        return in_array($this->state, [self::AVAILABLE, self::WARNING], true);
    }

    public function remainingSeconds(): ?int
    {
        return $this->allowedSeconds === null ? null : max(0, $this->allowedSeconds - $this->usedSeconds);
    }

    public function toArray(): array
    {
        return [
            'state' => $this->state,
            'can_play' => $this->canPlay(),
            'used_seconds' => $this->usedSeconds,
            'allowed_seconds' => $this->allowedSeconds,
            'remaining_seconds' => $this->remainingSeconds(),
            'expires_at' => $this->expiresAt?->format(DATE_ATOM),
            'available_from' => $this->availableFrom?->format(DATE_ATOM),
        ];
    }
}

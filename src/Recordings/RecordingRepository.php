<?php
declare(strict_types=1);

namespace Enlixo\Recordings;

interface RecordingRepository
{
    public function find(string $kind, int $id): ?RecordingInfo;

    public function studentEnrolled(int $studentId, int $classId): bool;

    public function rule(string $kind, int $id): ?AccessRule;

    public function override(int $studentId, string $kind, int $id): ?StudentOverride;

    public function usedSeconds(int $studentId, string $kind, int $id): int;

    public function createSession(string $token, int $studentId, string $kind, int $id, \DateTimeImmutable $now, ?string $ip, ?string $userAgent): void;

    public function findSession(string $token): ?WatchSession;

    public function addWatchTime(int $sessionId, int $seconds, int $positionSeconds, \DateTimeImmutable $now): void;
}

<?php
declare(strict_types=1);

namespace Enlixo\Recordings;

interface RecordingRepository
{
    public function find(string $kind, int $id): ?RecordingInfo;

    public function studentEnrolled(int $studentId, int $classId): bool;

    /**
     * Did the student pay the class fee for this month ('Y-m')? True for a free class, and for a class the tutor does
     * not take fees for in the app (no payment recorded for it at all), so tutors who collect fees elsewhere are not
     * affected. Paid = payment status 3 (approved / cash) or 8 (card), like the tutes.
     */
    public function monthPaid(int $studentId, int $classId, string $month): bool;

    public function rule(string $kind, int $id): ?AccessRule;

    public function override(int $studentId, string $kind, int $id): ?StudentOverride;

    public function usedSeconds(int $studentId, string $kind, int $id): int;

    public function createSession(string $token, int $studentId, string $kind, int $id, \DateTimeImmutable $now, ?string $ip, ?string $userAgent): void;

    public function findSession(string $token): ?WatchSession;

    public function addWatchTime(int $sessionId, int $seconds, int $positionSeconds, \DateTimeImmutable $now): void;
}

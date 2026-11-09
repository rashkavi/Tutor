<?php
declare(strict_types=1);

namespace Enlixo\Scheduling;

interface TimetableRepository
{
    /** @return list<WeeklySlot> active weekly slots */
    public function activeSlots(): array;

    public function sessionExists(int $slotId, string $date): bool;

    /** Another active session of the same tutor overlapping this time? Returns its title, or null. */
    public function findTutorConflict(int $tutorId, string $date, string $start, string $end): ?string;

    public function insertSession(WeeklySlot $slot, string $date, int $createdBy): int;

    public function saveMeeting(int $timeTableId, CreatedMeeting $meeting, string $encryptedStartUrl, ?int $integrationId): void;

    public function saveMeetingFailure(int $timeTableId, string $error): void;
}

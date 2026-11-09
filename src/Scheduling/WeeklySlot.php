<?php
declare(strict_types=1);

namespace Enlixo\Scheduling;

/** "Grade 11 Maths, every Saturday 08:00–10:00, online" */
final class WeeklySlot
{
    public const PHYSICAL = 1;
    public const ONLINE = 2;

    public function __construct(
        public readonly int $id,
        public readonly int $classId,
        public readonly int $tutorId,
        public readonly string $className,
        public readonly int $weekday, // ISO: 1 = Monday ... 7 = Sunday
        public readonly string $startTime, // HH:MM:SS
        public readonly string $endTime,
        public readonly int $lectureType,
        public readonly bool $autoCreateZoom,
        public readonly \DateTimeImmutable $validFrom,
        public readonly ?\DateTimeImmutable $validUntil,
    ) {
    }

    public function durationMinutes(): int
    {
        return (int) ((strtotime("1970-01-01 {$this->endTime}") - strtotime("1970-01-01 {$this->startTime}")) / 60);
    }

    /** @return list<\DateTimeImmutable> dates in [from, from + days) that this slot falls on */
    public function datesBetween(\DateTimeImmutable $from, int $days): array
    {
        $dates = [];
        $day = $from->setTime(0, 0);
        for ($i = 0; $i < $days; $i++, $day = $day->modify('+1 day')) {
            if ((int) $day->format('N') !== $this->weekday) {
                continue;
            }
            if ($day < $this->validFrom->setTime(0, 0) || ($this->validUntil !== null && $day > $this->validUntil->setTime(0, 0))) {
                continue;
            }
            $dates[] = $day;
        }
        return $dates;
    }

    public static function timesOverlap(string $aStart, string $aEnd, string $bStart, string $bEnd): bool
    {
        return $aStart < $bEnd && $bStart < $aEnd;
    }
}

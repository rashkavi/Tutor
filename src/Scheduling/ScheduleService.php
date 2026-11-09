<?php
declare(strict_types=1);

namespace Enlixo\Scheduling;

use Enlixo\Core\Clock;
use Enlixo\Core\Crypto;

/**
 * Turns weekly class slots into real timetable sessions for the next N weeks,
 * and creates a Zoom meeting for each online session. Safe to run many times (idempotent):
 * a session that already exists for (slot, date) is skipped.
 */
final class ScheduleService
{
    public function __construct(
        private readonly TimetableRepository $timetable,
        private readonly MeetingProviderResolver $providers,
        private readonly Crypto $crypto,
        private readonly Clock $clock,
    ) {
    }

    /** @return array{created:int, meetings:int, skipped_existing:int, conflicts:list<string>, errors:list<string>} */
    public function generate(int $weeksAhead = 2, int $systemUserId = 0): array
    {
        $report = ['created' => 0, 'meetings' => 0, 'skipped_existing' => 0, 'conflicts' => [], 'errors' => []];
        $from = $this->clock->now();

        foreach ($this->timetable->activeSlots() as $slot) {
            foreach ($slot->datesBetween($from, $weeksAhead * 7) as $day) {
                $date = $day->format('Y-m-d');
                if ($this->timetable->sessionExists($slot->id, $date)) {
                    $report['skipped_existing']++;
                    continue;
                }
                $conflict = $this->timetable->findTutorConflict($slot->tutorId, $date, $slot->startTime, $slot->endTime);
                if ($conflict !== null) {
                    $report['conflicts'][] = sprintf('%s on %s clashes with "%s"', $slot->className, $date, $conflict);
                    continue;
                }

                $timeTableId = $this->timetable->insertSession($slot, $date, $systemUserId);
                $report['created']++;

                if ($slot->lectureType === WeeklySlot::ONLINE && $slot->autoCreateZoom) {
                    $this->createMeeting($slot, $day, $timeTableId, $report);
                }
            }
        }
        return $report;
    }

    private function createMeeting(WeeklySlot $slot, \DateTimeImmutable $day, int $timeTableId, array &$report): void
    {
        $provider = $this->providers->forTutor($slot->tutorId);
        if ($provider === null) {
            $report['errors'][] = sprintf('%s: Zoom is not connected, meeting not created.', $slot->className);
            $this->timetable->saveMeetingFailure($timeTableId, 'Zoom not connected');
            return;
        }
        [$h, $m] = array_map('intval', explode(':', $slot->startTime));
        try {
            $meeting = $provider->createMeeting(new MeetingRequest(
                sprintf('%s – %s', $slot->className, $day->format('D, j M')),
                $day->setTime($h, $m),
                $slot->durationMinutes(),
            ));
            $this->timetable->saveMeeting($timeTableId, $meeting, $this->crypto->encrypt($meeting->startUrl), $provider->integrationId());
            $report['meetings']++;
        } catch (\Throwable $e) {
            $this->timetable->saveMeetingFailure($timeTableId, mb_substr($e->getMessage(), 0, 250));
            $report['errors'][] = sprintf('%s on %s: %s', $slot->className, $day->format('Y-m-d'), $e->getMessage());
        }
    }
}

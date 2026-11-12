<?php
declare(strict_types=1);

use Enlixo\Catalog\FeatureCatalog;
use Enlixo\Core\Clock;
use Enlixo\Entitlements\EntitlementRepository;
use Enlixo\Recordings\AccessRule;
use Enlixo\Recordings\RecordingInfo;
use Enlixo\Recordings\RecordingRepository;
use Enlixo\Recordings\StudentOverride;
use Enlixo\Recordings\WatchSession;
use Enlixo\Roles\AuditLogger;
use Enlixo\Roles\Role;
use Enlixo\Roles\RoleRepository;
use Enlixo\Scheduling\CreatedMeeting;
use Enlixo\Scheduling\MeetingProvider;
use Enlixo\Scheduling\MeetingProviderResolver;
use Enlixo\Scheduling\MeetingRequest;
use Enlixo\Scheduling\TimetableRepository;
use Enlixo\Scheduling\WeeklySlot;

final class FakeClock implements Clock
{
    public function __construct(public \DateTimeImmutable $now)
    {
    }

    public function now(): \DateTimeImmutable
    {
        return $this->now;
    }

    public function advance(int $seconds): void
    {
        $this->now = $this->now->modify("+$seconds seconds");
    }
}

final class FakeEntitlementRepo implements EntitlementRepository
{
    /** @param array<int,string> $plans tutorId => plan */
    public function __construct(public array $plans = [], public array $addons = [], public int $students = 0)
    {
    }

    public function activePlanKey(int $tutorId): string { return $this->plans[$tutorId] ?? 'start'; }
    public function planFeatureKeys(string $planKey): array { return FeatureCatalog::featuresForPlan($planKey); }
    public function planStudentLimit(string $planKey): ?int { return FeatureCatalog::PLANS[$planKey]['student_limit']; }
    public function tutorStudentLimit(int $tutorId, string $planKey): ?int { return $this->planStudentLimit($planKey); }
    public function activeAddonFeatureKeys(int $tutorId): array { return $this->addons[$tutorId] ?? []; }
    public function activeStudentCount(int $tutorId): int { return $this->students; }
}

final class FakeRoleRepo implements RoleRepository
{
    /** @var array<int, Role> */
    public array $roles = [];
    /** @var array<int, int> staffUserId => roleId */
    public array $staff = [];
    private int $nextId = 10;

    public function forTutor(int $tutorId): array { return array_values(array_filter($this->roles, fn (Role $r) => $r->tutorId === $tutorId)); }

    public function find(int $tutorId, int $roleId): ?Role
    {
        $r = $this->roles[$roleId] ?? null;
        if (!$r || $r->tutorId !== $tutorId) {
            return null;
        }
        $count = count(array_filter($this->staff, fn ($rid) => $rid === $roleId));
        return new Role($r->id, $r->tutorId, $r->name, $r->description, $r->isSystem, $r->permissions, $count);
    }

    public function nameExists(int $tutorId, string $name, ?int $exceptRoleId = null): bool
    {
        foreach ($this->forTutor($tutorId) as $r) {
            if (strcasecmp($r->name, $name) === 0 && $r->id !== $exceptRoleId) {
                return true;
            }
        }
        return false;
    }

    public function create(int $tutorId, string $name, ?string $description, array $permissions): int
    {
        $id = $this->nextId++;
        $this->roles[$id] = new Role($id, $tutorId, $name, $description, false, $permissions);
        return $id;
    }

    public function update(int $roleId, string $name, ?string $description, array $permissions): void
    {
        $r = $this->roles[$roleId];
        $this->roles[$roleId] = new Role($roleId, $r->tutorId, $name, $description, $r->isSystem, $permissions);
    }

    public function delete(int $roleId, ?int $moveToRoleId): void
    {
        foreach ($this->staff as $u => $rid) {
            if ($rid === $roleId) {
                $this->staff[$u] = $moveToRoleId;
            }
        }
        unset($this->roles[$roleId]);
    }

    public function roleOfStaff(int $tutorId, int $userId): ?Role
    {
        return isset($this->staff[$userId]) ? $this->find($tutorId, $this->staff[$userId]) : null;
    }
}

final class FakeAudit implements AuditLogger
{
    public array $entries = [];

    public function log(?int $tutorId, int $userId, string $action, string $entity, ?string $entityId, array $details = []): void
    {
        $this->entries[] = compact('action', 'entityId', 'details');
    }
}

final class FakeRecordingRepo implements RecordingRepository
{
    public array $recordings = [];
    public array $rules = [];
    public array $overrides = [];
    public array $enrolled = []; // "student:class" => true
    /** @var array<string, array{id:int, student:int, kind:string, rec:int, last:\DateTimeImmutable, watched:int}> */
    public array $sessions = [];

    public function find(string $kind, int $id): ?RecordingInfo { return $this->recordings["$kind:$id"] ?? null; }
    public function studentEnrolled(int $studentId, int $classId): bool { return isset($this->enrolled["$studentId:$classId"]); }
    public function rule(string $kind, int $id): ?AccessRule { return $this->rules["$kind:$id"] ?? null; }
    public function override(int $studentId, string $kind, int $id): ?StudentOverride { return $this->overrides["$studentId:$kind:$id"] ?? null; }

    public function usedSeconds(int $studentId, string $kind, int $id): int
    {
        return array_sum(array_map(fn ($s) => $s['watched'], array_filter($this->sessions, fn ($s) => $s['student'] === $studentId && $s['kind'] === $kind && $s['rec'] === $id)));
    }

    public function createSession(string $token, int $studentId, string $kind, int $id, \DateTimeImmutable $now, ?string $ip, ?string $userAgent): void
    {
        $this->sessions[$token] = ['id' => count($this->sessions) + 1, 'student' => $studentId, 'kind' => $kind, 'rec' => $id, 'last' => $now, 'watched' => 0];
    }

    public function findSession(string $token): ?WatchSession
    {
        $s = $this->sessions[$token] ?? null;
        return $s ? new WatchSession($s['id'], $token, $s['student'], $s['kind'], $s['rec'], $s['last'], $s['watched']) : null;
    }

    public function addWatchTime(int $sessionId, int $seconds, int $positionSeconds, \DateTimeImmutable $now): void
    {
        foreach ($this->sessions as $t => $s) {
            if ($s['id'] === $sessionId) {
                $this->sessions[$t]['watched'] += $seconds;
                $this->sessions[$t]['last'] = $now;
            }
        }
    }
}

final class FakeTimetableRepo implements TimetableRepository
{
    public array $slots = [];
    public array $sessions = []; // id => [slot, date, tutor, start, end, title]
    public array $meetings = [];
    public array $failures = [];

    public function activeSlots(): array { return $this->slots; }

    public function sessionExists(int $slotId, string $date): bool
    {
        foreach ($this->sessions as $s) {
            if ($s['slot'] === $slotId && $s['date'] === $date) {
                return true;
            }
        }
        return false;
    }

    public function findTutorConflict(int $tutorId, string $date, string $start, string $end): ?string
    {
        foreach ($this->sessions as $s) {
            if ($s['tutor'] === $tutorId && $s['date'] === $date && WeeklySlot::timesOverlap($start, $end, $s['start'], $s['end'])) {
                return $s['title'];
            }
        }
        return null;
    }

    public function insertSession(WeeklySlot $slot, string $date, int $createdBy): int
    {
        $id = count($this->sessions) + 1;
        $this->sessions[$id] = ['slot' => $slot->id, 'date' => $date, 'tutor' => $slot->tutorId, 'start' => $slot->startTime, 'end' => $slot->endTime, 'title' => $slot->className];
        return $id;
    }

    public function saveMeeting(int $timeTableId, CreatedMeeting $meeting, string $encryptedStartUrl, ?int $integrationId): void
    {
        $this->meetings[$timeTableId] = [$meeting, $encryptedStartUrl];
    }

    public function saveMeetingFailure(int $timeTableId, string $error): void
    {
        $this->failures[$timeTableId] = $error;
    }
}

final class FakeMeetingProvider implements MeetingProvider, MeetingProviderResolver
{
    public array $requests = [];

    public function __construct(private readonly array $connectedTutors = [])
    {
    }

    public function forTutor(int $tutorId): ?MeetingProvider
    {
        return in_array($tutorId, $this->connectedTutors, true) ? $this : null;
    }

    public function createMeeting(MeetingRequest $request): CreatedMeeting
    {
        $this->requests[] = $request;
        $n = count($this->requests);
        return new CreatedMeeting("9000$n", "https://zoom.us/j/9000$n", "https://zoom.us/s/9000$n?zak=secret", 'abc123');
    }

    public function integrationId(): ?int { return 1; }
}

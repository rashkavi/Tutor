<?php
declare(strict_types=1);

namespace Enlixo\Recordings;

use Enlixo\Core\Clock;
use Enlixo\Core\HttpException;
use Enlixo\Entitlements\EntitlementService;

/**
 * Watch-time limits (like ENLIXO V3): each student may watch a recording for a limited time,
 * then it is disabled for them.
 *
 * Anti-cheat: the client only sends heartbeats. The server credits at most the real time that
 * passed since the previous heartbeat (capped at heartbeat interval + grace), so a modified
 * client cannot report less watching, and long pauses are not counted.
 *
 * Package rules: limits apply only with `recordings.controlled_access`, expiry only with
 * `recordings.expiry` (both PRO). Without them recordings are unlimited.
 */
final class RecordingAccessService
{
    public function __construct(
        private readonly RecordingRepository $repo,
        private readonly EntitlementService $entitlements,
        private readonly Clock $clock,
        private readonly int $heartbeatSeconds = 15,
        private readonly int $graceSeconds = 5,
        private readonly float $defaultMultiplier = 3.0,
        private readonly float $warnAtRatio = 0.8,
    ) {
    }

    public function status(int $studentId, string $kind, int $recordingId): WatchStatus
    {
        $rec = $this->recording($kind, $recordingId);
        return $this->statusFor($studentId, $rec);
    }

    public function statusFor(int $studentId, RecordingInfo $rec): WatchStatus
    {
        $used = $this->repo->usedSeconds($studentId, $rec->kind, $rec->id);
        if (!$this->repo->studentEnrolled($studentId, $rec->classId)) {
            return new WatchStatus(WatchStatus::NO_ACCESS, $used, null, null);
        }

        $ent = $this->entitlements->for($rec->tutorId);
        $rule = $this->repo->rule($rec->kind, $rec->id) ?? AccessRule::default($this->defaultMultiplier);
        $override = $this->repo->override($studentId, $rec->kind, $rec->id);
        $now = $this->clock->now();

        $allowed = null;
        if ($ent->has('recordings.controlled_access')) {
            $base = $rule->baseAllowance($rec->durationSeconds);
            $allowed = $base === null ? null : $base + 60 * ($override?->extraMinutes ?? 0);
        }

        $expiresAt = null;
        $availableFrom = null;
        if ($ent->has('recordings.expiry')) {
            $expiresAt = $override?->expiresAt ?? $rule->expiresAt;
            $availableFrom = $rule->availableFrom;
        }

        $state = match (true) {
            $availableFrom !== null && $now < $availableFrom => WatchStatus::NOT_YET,
            $expiresAt !== null && $now >= $expiresAt => WatchStatus::EXPIRED,
            $allowed !== null && $used >= $allowed => WatchStatus::EXHAUSTED,
            $allowed !== null && $allowed > 0 && $used / $allowed >= $this->warnAtRatio => WatchStatus::WARNING,
            default => WatchStatus::AVAILABLE,
        };

        return new WatchStatus($state, $used, $allowed, $expiresAt, $availableFrom);
    }

    /** @return array{session_token:string, heartbeat_seconds:int, status:array} */
    public function start(int $studentId, string $kind, int $recordingId, ?string $ip = null, ?string $userAgent = null): array
    {
        $status = $this->status($studentId, $kind, $recordingId);
        if (!$status->canPlay()) {
            throw new HttpException(403, 'recording_locked', $this->lockedMessage($status), $status->toArray());
        }
        $token = bin2hex(random_bytes(20));
        $this->repo->createSession($token, $studentId, $kind, $recordingId, $this->clock->now(), $ip, $userAgent ? mb_substr($userAgent, 0, 255) : null);

        return ['session_token' => $token, 'heartbeat_seconds' => $this->heartbeatSeconds, 'status' => $status->toArray()];
    }

    /**
     * Called by the player every N seconds while the video is playing, and once on pause.
     * On resume the player sends $resume = true: that only restarts the clock, so time spent paused is never counted.
     */
    public function heartbeat(int $studentId, string $token, int $positionSeconds, bool $resume = false): WatchStatus
    {
        $session = $this->repo->findSession($token);
        if ($session === null || $session->studentId !== $studentId) {
            throw new HttpException(404, 'session_not_found', 'Playback session expired. Please reopen the recording.');
        }
        $rec = $this->recording($session->kind, $session->recordingId);
        $before = $this->statusFor($studentId, $rec);
        if (!$before->canPlay()) {
            return $before;
        }

        $now = $this->clock->now();
        $elapsed = $now->getTimestamp() - $session->lastHeartbeatAt->getTimestamp();
        $credit = $resume ? 0 : max(0, min($elapsed, $this->heartbeatSeconds + $this->graceSeconds));
        if (($remaining = $before->remainingSeconds()) !== null) {
            $credit = min($credit, $remaining);
        }
        $position = max(0, min($positionSeconds, $rec->durationSeconds));
        $this->repo->addWatchTime($session->id, $credit, $position, $now);

        return $this->statusFor($studentId, $rec);
    }

    public function lockedMessage(WatchStatus $s): string
    {
        return match ($s->state) {
            WatchStatus::EXHAUSTED => 'You have used all the watch time for this recording. Ask your tutor if you need more time.',
            WatchStatus::EXPIRED => 'This recording has expired.',
            WatchStatus::NOT_YET => 'This recording is not available yet.',
            WatchStatus::NO_ACCESS => 'You are not enrolled in this class.',
            default => 'This recording is locked.',
        };
    }

    private function recording(string $kind, int $id): RecordingInfo
    {
        if (!in_array($kind, ['lecture', 'pre'], true)) {
            throw HttpException::notFound('Recording not found.');
        }
        return $this->repo->find($kind, $id) ?? throw HttpException::notFound('Recording not found.');
    }
}

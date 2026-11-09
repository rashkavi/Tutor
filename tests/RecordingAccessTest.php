<?php
declare(strict_types=1);

use Enlixo\Core\HttpException;
use Enlixo\Entitlements\EntitlementService;
use Enlixo\Recordings\AccessRule;
use Enlixo\Recordings\RecordingAccessService;
use Enlixo\Recordings\RecordingInfo;
use Enlixo\Recordings\StudentOverride;
use Enlixo\Recordings\WatchStatus;

/** 10-minute recording in class 3 of tutor 1; student 99 enrolled. */
function recFixture(string $plan = 'pro'): array
{
    $repo = new FakeRecordingRepo();
    $repo->recordings['lecture:1'] = new RecordingInfo('lecture', 1, 3, 1, 'Lesson 1', 600);
    $repo->enrolled['99:3'] = true;
    $clock = new FakeClock(new DateTimeImmutable('2026-11-09 10:00:00'));
    $svc = new RecordingAccessService($repo, new EntitlementService(new FakeEntitlementRepo([1 => $plan])), $clock, 15, 5, 3.0, 0.8);
    return [$repo, $clock, $svc];
}

function watch(RecordingAccessService $svc, FakeClock $clock, string $token, int $seconds): WatchStatus
{
    $status = null;
    for ($t = 0; $t < $seconds; $t += 15) {
        $clock->advance(15);
        $status = $svc->heartbeat(99, $token, $t);
    }
    return $status;
}

test('default allowance is 3x duration on PRO', function () {
    [, , $svc] = recFixture();
    $s = $svc->status(99, 'lecture', 1);
    T::eq(1800, $s->allowedSeconds);
    T::eq(WatchStatus::AVAILABLE, $s->state);
});

test('START plan has no limit (feature not in package)', function () {
    [, , $svc] = recFixture('start');
    T::eq(null, $svc->status(99, 'lecture', 1)->allowedSeconds);
});

test('heartbeats add watch time, warn at 80%, lock at 100%', function () {
    [, $clock, $svc] = recFixture();
    $token = $svc->start(99, 'lecture', 1)['session_token'];
    $s = watch($svc, $clock, $token, 1440); // 24 min = 80%
    T::eq(1440, $s->usedSeconds);
    T::eq(WatchStatus::WARNING, $s->state);
    $s = watch($svc, $clock, $token, 400);
    T::eq(1800, $s->usedSeconds, 'never credited above the allowance');
    T::eq(WatchStatus::EXHAUSTED, $s->state);
    $e = T::throws(HttpException::class, fn () => $svc->start(99, 'lecture', 1));
    T::eq('recording_locked', $e?->errorCode);
});

test('long gaps are capped; resume does not count paused time', function () {
    [, $clock, $svc] = recFixture();
    $token = $svc->start(99, 'lecture', 1)['session_token'];
    $clock->advance(3600);
    T::eq(20, $svc->heartbeat(99, $token, 0)->usedSeconds, 'capped at interval + grace');
    $clock->advance(600);
    T::eq(20, $svc->heartbeat(99, $token, 0, true)->usedSeconds, 'resume credits nothing');
    $clock->advance(15);
    T::eq(35, $svc->heartbeat(99, $token, 15)->usedSeconds);
});

test('fixed minutes, overrides and expiry', function () {
    [$repo, $clock, $svc] = recFixture();
    $repo->rules['lecture:1'] = new AccessRule(AccessRule::FIXED_MINUTES, null, 20, null, new DateTimeImmutable('2026-11-10 00:00:00'));
    T::eq(1200, $svc->status(99, 'lecture', 1)->allowedSeconds);
    $repo->overrides['99:lecture:1'] = new StudentOverride(10);
    T::eq(1800, $svc->status(99, 'lecture', 1)->allowedSeconds, 'plus 10 extra minutes');
    $clock->advance(86400);
    T::eq(WatchStatus::EXPIRED, $svc->status(99, 'lecture', 1)->state);
});

test('unlimited mode and not-enrolled student', function () {
    [$repo, , $svc] = recFixture();
    $repo->rules['lecture:1'] = new AccessRule(AccessRule::UNLIMITED);
    T::eq(null, $svc->status(99, 'lecture', 1)->remainingSeconds());
    T::eq(WatchStatus::NO_ACCESS, $svc->status(100, 'lecture', 1)->state);
});

test('another student cannot use my session token', function () {
    [$repo, , $svc] = recFixture();
    $repo->enrolled['100:3'] = true;
    $token = $svc->start(99, 'lecture', 1)['session_token'];
    T::throws(HttpException::class, fn () => $svc->heartbeat(100, $token, 0));
});

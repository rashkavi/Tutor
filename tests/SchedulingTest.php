<?php
declare(strict_types=1);

use Enlixo\Core\Crypto;
use Enlixo\Scheduling\ScheduleService;
use Enlixo\Scheduling\WeeklySlot;
use Enlixo\Scheduling\Zoom\ZoomWebhookVerifier;

function slot(int $id, int $tutor, int $weekday, string $start, string $end, int $type = WeeklySlot::ONLINE, string $name = 'Grade 11 Maths'): WeeklySlot
{
    return new WeeklySlot($id, 10 + $id, $tutor, $name, $weekday, $start, $end, $type, true, new DateTimeImmutable('2026-11-01'), null);
}

test('weekly slot dates and overlap', function () {
    $s = slot(1, 1, 6, '08:00:00', '10:00:00'); // Saturday
    $dates = array_map(fn ($d) => $d->format('Y-m-d'), $s->datesBetween(new DateTimeImmutable('2026-11-09'), 14));
    T::eq(['2026-11-14', '2026-11-21'], $dates);
    T::eq(120, $s->durationMinutes());
    T::true(WeeklySlot::timesOverlap('08:00', '10:00', '09:30', '11:00'));
    T::eq(false, WeeklySlot::timesOverlap('08:00', '10:00', '10:00', '11:00'), 'back-to-back is fine');
});

test('generate creates sessions + Zoom meetings, idempotent, detects clashes', function () {
    $repo = new FakeTimetableRepo();
    $repo->slots = [
        slot(1, 1, 6, '08:00:00', '10:00:00'),
        slot(2, 1, 6, '09:00:00', '11:00:00', WeeklySlot::ONLINE, 'Grade 10 Science'), // clashes with slot 1
        slot(3, 2, 6, '08:00:00', '10:00:00', WeeklySlot::PHYSICAL, 'Hall class'),     // other tutor, physical
    ];
    $zoom = new FakeMeetingProvider([1]);
    $crypto = new Crypto(base64_encode(str_repeat('k', 32)));
    $svc = new ScheduleService($repo, $zoom, $crypto, new FakeClock(new DateTimeImmutable('2026-11-09 01:00')));

    $r = $svc->generate(2);
    T::eq(4, $r['created'], '2 Saturdays x (slot 1 + slot 3)');
    T::eq(2, $r['meetings'], 'only online sessions get Zoom');
    T::eq(2, count($r['conflicts']));
    [$meeting, $enc] = $repo->meetings[1];
    T::eq($meeting->startUrl, $crypto->decrypt($enc), 'start URL stored encrypted');
    T::true(!str_contains($enc, 'zak='), 'not plain text');

    $again = $svc->generate(2);
    T::eq(0, $again['created']);
    T::eq(4, $again['skipped_existing']);
});

test('online class without Zoom connected is reported, not fatal', function () {
    $repo = new FakeTimetableRepo();
    $repo->slots = [slot(1, 5, 6, '08:00:00', '10:00:00')];
    $svc = new ScheduleService($repo, new FakeMeetingProvider([]), new Crypto(base64_encode(random_bytes(32))), new FakeClock(new DateTimeImmutable('2026-11-09')));
    $r = $svc->generate(1);
    T::eq(1, $r['created']);
    T::eq(1, count($r['errors']));
    T::eq('Zoom not connected', $repo->failures[1]);
});

test('Zoom webhook signature + url validation', function () {
    $v = new ZoomWebhookVerifier('secret');
    $body = '{"event":"meeting.started"}';
    $ts = '1700000000';
    $sig = 'v0=' . hash_hmac('sha256', "v0:$ts:$body", 'secret');
    T::true($v->isValid($sig, $ts, $body, 1700000010));
    T::eq(false, $v->isValid($sig, $ts, $body . ' ', 1700000010), 'tampered body');
    T::eq(false, $v->isValid($sig, $ts, $body, 1700009999), 'replay too old');
    T::eq(hash_hmac('sha256', 'abc', 'secret'), $v->validationResponse('abc')['encryptedToken']);
});

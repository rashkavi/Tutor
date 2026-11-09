<?php
declare(strict_types=1);

use Enlixo\Entitlements\EntitlementService;
use Enlixo\Entitlements\FeatureLockedException;

test('START plan: core features unlocked, PRO features locked', function () {
    $svc = new EntitlementService(new FakeEntitlementRepo([7 => 'start']));
    T::true($svc->has(7, 'timetable.manage'), 'timetable on START');
    T::true($svc->has(7, 'zoom.tutor_integration'), 'own Zoom on START');
    T::eq(false, $svc->has(7, 'recordings.controlled_access'), 'controlled access is PRO');
    T::eq(false, $svc->has(7, 'comms.whatsapp'), 'WhatsApp is GROW+');
});

test('require() throws 402 feature_locked naming the cheapest plan', function () {
    $svc = new EntitlementService(new FakeEntitlementRepo([7 => 'start']));
    $e = T::throws(FeatureLockedException::class, fn () => $svc->require(7, 'comms.sms'));
    T::eq(402, $e?->status);
    T::eq('grow', $e?->details['required_plan']);
});

test('add-on unlocks a feature on any plan', function () {
    $svc = new EntitlementService(new FakeEntitlementRepo([7 => 'start'], [7 => ['zoom.enviiz_account']]));
    T::true($svc->has(7, 'zoom.enviiz_account'));
});

test('student limit', function () {
    $ent = (new EntitlementService(new FakeEntitlementRepo([7 => 'start'], [], 49)))->for(7);
    T::true($ent->canAddStudents(1), '49 + 1 <= 50');
    T::eq(false, $ent->canAddStudents(2), '49 + 2 > 50');
});

test('toArray lists every feature with lock state', function () {
    $arr = (new EntitlementService(new FakeEntitlementRepo([7 => 'grow'])))->for(7)->toArray();
    $byKey = array_column($arr['features'], null, 'key');
    T::eq(true, $byKey['comms.whatsapp']['unlocked']);
    T::eq('pro', $byKey['payments.arrears']['required_plan']);
    T::eq(null, $byKey['zoom.enviiz_account']['required_plan'], 'add-on only');
    T::eq('GROW', $arr['plan']['name']);
});

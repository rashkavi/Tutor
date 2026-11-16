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
    $ent = (new EntitlementService(new FakeEntitlementRepo([7 => 'start'], [], 149)))->for(7);
    T::true($ent->canAddStudents(1), '149 + 1 <= 150 (STARTER)');
    T::eq(false, $ent->canAddStudents(2), '149 + 2 > 150');
});

test('toArray lists every feature with lock state', function () {
    $arr = (new EntitlementService(new FakeEntitlementRepo([7 => 'grow'])))->for(7)->toArray();
    $byKey = array_column($arr['features'], null, 'key');
    T::eq(true, $byKey['comms.whatsapp']['unlocked']);
    T::eq(true, $byKey['payments.arrears']['unlocked'], 'GROW has every feature since November 2026');
    T::eq('grow', $byKey['payments.arrears']['required_plan'] ?? 'grow');
    T::eq(null, $byKey['zoom.enviiz_account']['required_plan'], 'add-on only');
    T::eq('GROW', $arr['plan']['name']);
});

// ---- November 2026 plan matrix: FREE / STARTER / GROW (docs/PLANS.md) ----

test('FREE: the basics only; parent login, Zoom auto-scheduling, QR attendance, posted tutes are STARTER', function () {
    $svc = new EntitlementService(new FakeEntitlementRepo([7 => 'free']));
    T::true($svc->has(7, 'classes.setup'), 'classes on FREE');
    T::true($svc->has(7, 'attendance.manual'), 'manual attendance on FREE');
    foreach (['parents.login', 'zoom.tutor_integration', 'attendance.qr', 'tutes.courier', 'exams.mcq'] as $k) {
        T::eq(false, $svc->has(7, $k), "$k is not on FREE");
        T::eq('start', \Enlixo\Catalog\FeatureCatalog::lowestPlanFor($k), "$k comes with STARTER");
    }
});

test('limits: FREE has 3 classes and monthly caps, STARTER lifts them, GROW has no message cap', function () {
    $repo = new FakeEntitlementRepo([7 => 'free', 8 => 'start', 9 => 'grow']);
    $repo->used = ['classes' => 3, 'tutes.month' => 1, 'recordings.month' => 0, 'messages.month' => 99];
    $svc = new EntitlementService($repo);
    T::eq(false, $svc->for(7)->within('classes'), 'a 4th class is over FREE');
    T::true($svc->for(7)->within('tutes.month', 3), '1 + 3 = 4 tutes fits');
    T::eq(false, $svc->for(7)->within('messages.month', 2), '99 + 2 > 100');
    T::true($svc->for(8)->within('classes', 50), 'STARTER: unlimited classes');
    T::eq(false, $svc->for(8)->within('messages.month', 1000), 'STARTER: 1,000 messages');
    T::true($svc->for(9)->within('messages.month', 100000), 'GROW: unlimited messages');
});

test('requireWithin throws 402 limit_reached naming the plan that lifts it', function () {
    $repo = new FakeEntitlementRepo([7 => 'free']);
    $repo->used = ['tutes.month' => 4];
    $e = T::throws(\Enlixo\Entitlements\LimitReachedException::class, fn () => (new EntitlementService($repo))->requireWithin(7, 'tutes.month'));
    T::eq(402, $e?->status);
    T::eq('limit_reached', $e?->errorCode);
    T::eq('start', $e?->details['required_plan']);
    T::true(str_contains((string) $e?->getMessage(), 'STARTER gives you unlimited tutes'), 'message names STARTER');
});

test('next plan: FREE -> STARTER -> GROW -> CUSTOM -> none', function () {
    T::eq('start', \Enlixo\Catalog\FeatureCatalog::nextPlan('free'));
    T::eq('grow', \Enlixo\Catalog\FeatureCatalog::nextPlan('start'));
    T::eq('pro', \Enlixo\Catalog\FeatureCatalog::nextPlan('grow'));
    T::eq(null, \Enlixo\Catalog\FeatureCatalog::nextPlan('pro'));
    T::eq('grow', \Enlixo\Catalog\FeatureCatalog::lowestPlanForLimit('messages.month', 1001));
});

test('a running preview gives the preview plan, the paid plan stays the base', function () {
    $repo = new FakeEntitlementRepo([7 => 'free']);
    $repo->previewRow = ['plan' => 'grow', 'until' => '2099-01-01'];
    $ent = (new EntitlementService($repo))->for(7);
    T::eq('grow', $ent->planKey);
    T::eq('free', $ent->paidPlanKey());
    T::true($ent->has('comms.whatsapp'), 'GROW feature while trying GROW');
    $arr = $ent->toArray();
    T::eq('free', $arr['paid_plan']['key']);
    T::eq('start', $arr['next_plan']['key'], 'the next plan follows the paid plan');
    T::true(in_array('Parent Login', $arr['next_plan']['adds'], true), 'STARTER adds Parent Login');
});

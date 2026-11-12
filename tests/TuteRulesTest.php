<?php
declare(strict_types=1);

use Enlixo\Tutes\TuteRules;

$pdf = ['name' => 'unit5.pdf', 'size' => 1000, 'error' => 0, 'mime' => 'application/pdf'];

test('tutes: a downloadable tute needs a name and a PDF or Word file', function () use ($pdf) {
    [, $e] = TuteRules::validateTute(['title' => '', 'type' => 'download'], [], '2026-11-12');
    T::true(isset($e['title']), 'name required');
    T::true(isset($e['files']), 'file required');
    [$t, $e] = TuteRules::validateTute(['title' => '  Unit 5  notes ', 'type' => 'download'], [$pdf], '2026-11-12');
    T::eq([], $e);
    T::eq('Unit 5 notes', $t['title']);
    T::eq(TuteRules::TYPE_DOWNLOAD, $t['type']);
    T::eq(null, $t['payment_month'], 'everyone gets it');
    [, $e] = TuteRules::validateTute(['title' => 'x', 'type' => 'download'], [['mime' => 'text/plain'] + $pdf], '2026-11-12');
    T::true(isset($e['files']), 'a text file named .pdf is refused');
    [, $e] = TuteRules::validateTute(['title' => 'x', 'type' => 'download'], [['size' => 11 * 1024 * 1024] + $pdf], '2026-11-12');
    T::true(isset($e['files']), 'over 10 MB is refused');
});

test('tutes: "paid for a month" needs a real month; "everyone" drops the month', function () use ($pdf) {
    [, $e] = TuteRules::validateTute(['title' => 'x', 'type' => 'download', 'who' => 'paid', 'payment_month' => '2026-13'], [$pdf], '2026-11-12');
    T::true(isset($e['payment_month']));
    [$t, $e] = TuteRules::validateTute(['title' => 'x', 'type' => 'download', 'who' => 'paid', 'payment_month' => '2026-11'], [$pdf], '2026-11-12');
    T::eq([], $e);
    T::eq('2026-11', $t['payment_month']);
    [$t] = TuteRules::validateTute(['title' => 'x', 'type' => 'download', 'who' => 'all', 'payment_month' => '2026-11'], [$pdf], '2026-11-12');
    T::eq(null, $t['payment_month']);
});

test('tutes: a courier tute lists what is in the parcel and keeps the courier and post date', function () {
    [, $e] = TuteRules::validateTute(['title' => 'Unit 5', 'type' => 'courier', 'items' => ['  ', '']], [], '2026-11-12');
    T::true(isset($e['items']), 'at least one item');
    [$t, $e] = TuteRules::validateTute(['title' => 'Unit 5', 'type' => 'courier', 'items' => ['Book', ' Paper 3 '], 'courier_name' => 'Domex', 'dispatch_on' => '2026-11-15'], [], '2026-11-12');
    T::eq([], $e);
    T::eq(['Book', 'Paper 3'], $t['items']);
    T::eq('Domex', $t['courier_name']);
    T::eq('2026-11-15', $t['dispatch_on']);
    [, $e] = TuteRules::validateTute(['title' => 'Unit 5', 'type' => 'courier', 'items' => ['Book'], 'dispatch_on' => '2026-02-30'], [], '2026-11-12');
    T::true(isset($e['dispatch_on']), '30 February is not a date');
});

test('tutes: delivery changes are checked and tracking numbers are tidied', function () {
    [, $e] = TuteRules::validateDeliveryUpdate(['ids' => [], 'status' => 'dispatched']);
    T::true(isset($e['ids']));
    [, $e] = TuteRules::validateDeliveryUpdate(['ids' => [1], 'status' => 'lost']);
    T::true(isset($e['status']));
    [, $e] = TuteRules::validateDeliveryUpdate(['ids' => [1], 'tracking_no' => 'BA<script>']);
    T::true(isset($e['tracking_no']));
    [$u, $e] = TuteRules::validateDeliveryUpdate(['ids' => ['3', 3, 0, -1], 'status' => 'dispatched', 'tracking_no' => ' ba123456789lk ']);
    T::eq([], $e);
    T::eq([3], $u['ids']);
    T::eq('BA123456789LK', $u['set']['tracking_no']);
});

test('tutes: marking a step fills in or clears its dates', function () {
    $s = TuteRules::withStepDates(['status' => 'dispatched'], '2026-11-12');
    T::eq('2026-11-12', $s['dispatched_on_if_empty']);
    T::eq(null, $s['delivered_on']);
    $s = TuteRules::withStepDates(['status' => 'delivered'], '2026-11-12');
    T::eq('2026-11-12', $s['delivered_on']);
    $s = TuteRules::withStepDates(['status' => 'pending'], '2026-11-12');
    T::eq(null, $s['dispatched_on']);
    T::eq(null, $s['delivered_on']);
});

test('tutes: month options and tracking links', function () {
    $m = TuteRules::monthOptions('2026-11-12');
    T::eq(['2027-01', '2026-12', '2026-11', '2026-10', '2026-09', '2026-08'], array_column($m, 'value'));
    T::eq('November 2026', TuteRules::monthLabel('2026-11'));
    T::eq(null, TuteRules::monthLabel('nope'));
    T::true(str_contains((string) TuteRules::trackingUrl('Sri Lanka Post (Speed Post)'), 'slpost.gov.lk'));
    T::eq(null, TuteRules::trackingUrl('Hand delivery'));
});

<?php
declare(strict_types=1);

use Enlixo\Platform\Lifecycle;
use Enlixo\Platform\PlatformBilling;

$rules = ['trial_days' => 7, 'invoice_days_before' => 7, 'grace_days' => 7];
$sub = static fn (array $over) => array_merge(['status' => 'active', 'trial_ends_at' => null, 'period_end' => null, 'cancel_at_period_end' => 0, 'grace_until' => null], $over);
$grow = ['payment_plan' => 'Grow', 'per_month_amount' => 6000, 'annual_discount' => 1000, 'extra_pack_price' => 1000];
$pro = ['payment_plan' => 'Pro', 'per_month_amount' => 10000, 'annual_discount' => 2000, 'extra_pack_price' => null];

test('Billing: invoice lines for monthly, yearly, packs, add-ons and a custom price', function () use ($grow, $pro) {
    $zoom = ['name' => 'ENVIIZ Zoom Account', 'price' => 1800.0, 'charge_model' => 'Monthly'];
    $sms = ['name' => 'ENVIIZ SMS Service', 'price' => null, 'charge_model' => 'Pay as you go'];
    $card = ['name' => 'ID cards', 'price' => 15000.0, 'charge_model' => 'One-time'];
    $m = Lifecycle::periodLines($grow, 'monthly', null, 2, [$zoom, $sms, $card]);
    T::eq([6000.0, 2000.0, 1800.0], array_column($m, 'amount'), 'plan + 2 packs + monthly add-on; usage and one-time add-ons are not on the period invoice');
    T::eq('GROW plan, 1 month', $m[0]['text']);
    $y = Lifecycle::periodLines($grow, 'yearly', null, 1, [$zoom]);
    T::eq([60000.0, 12000.0, 21600.0], array_column($y, 'amount'), 'yearly = 12 × (price - yearly discount), packs and add-ons × 12');
    T::eq([4500.0], array_column(Lifecycle::periodLines($grow, 'monthly', 4500.0, 0, []), 'amount'), 'custom price replaces the plan price');
    T::eq(1, count(Lifecycle::periodLines($pro, 'monthly', null, 3, [])), 'PRO sells no packs');
    T::eq(['subtotal' => 7800.0, 'tax' => 0.0, 'total' => 7800.0], Lifecycle::total([['amount' => 6000.0], ['amount' => 1800.0]], false, 18));
    T::eq(['subtotal' => 1000.0, 'tax' => 180.0, 'total' => 1180.0], Lifecycle::total([['amount' => 1000.0]], true, 18), 'VAT only when switched on');
});

test('Billing: periods and the upgrade difference', function () {
    T::eq('2026-12-18', Lifecycle::periodEnd('2026-11-18', 'monthly'));
    T::eq('2027-11-18', Lifecycle::periodEnd('2026-11-18', 'yearly'));
    T::eq('2027-02-28', Lifecycle::periodEnd('2027-01-31', 'monthly'), '31 January + 1 month stops at the end of February');
    T::eq('2029-02-28', Lifecycle::periodEnd('2028-02-29', 'yearly'));
    T::eq(-3, Lifecycle::daysBetween('2026-11-18', '2026-11-15'));
    T::eq(2967.74, Lifecycle::upgradeDifference(6000, 10000, '2026-12-18', '2027-01-18', '2026-12-26'), 'difference for the 23 of 31 days left');
    T::eq(0.0, Lifecycle::upgradeDifference(10000, 6000, '2026-12-18', '2027-01-18', '2026-12-26'), 'never negative');
    T::eq(4000.0, Lifecycle::upgradeDifference(6000, 10000, '2026-11-19', '2026-12-19', '2026-11-12'), 'paid ahead of the trial end: the full difference, not more');
});

test('Billing: a free trial gets its first invoice 3 days before the end, then overdue, then suspended', function () use ($rules, $sub) {
    $t = $sub(['status' => 'trial', 'trial_ends_at' => '2026-11-18']);
    T::eq(['issue' => false, 'status' => 'trial', 'reason' => null], Lifecycle::step($t, false, null, $rules, '2026-11-11'), 'no bill on day 0 of a 7-day trial');
    T::eq(['issue' => true, 'status' => 'trial', 'reason' => null], Lifecycle::step($t, false, null, $rules, '2026-11-15'), 'invoice 3 days before; still a trial');
    T::eq('trial', Lifecycle::step($t, true, '2026-11-18', $rules, '2026-11-17')['status']);
    T::eq('overdue', Lifecycle::step($t, true, '2026-11-18', $rules, '2026-11-18')['status'], 'trial over and not paid');
    $s = Lifecycle::step($t, true, '2026-11-18', $rules, '2026-11-25');
    T::eq('suspended', $s['status'], 'grace of 7 days over');
    T::eq('Not paid within 7 days of the due date', $s['reason']);
});

test('Billing: an active tutor gets the renewal invoice 7 days before, then Payment Due and Overdue', function () use ($rules, $sub) {
    $a = $sub(['period_end' => '2026-12-18']);
    T::eq(['issue' => false, 'status' => 'active', 'reason' => null], Lifecycle::step($a, false, null, $rules, '2026-12-10'));
    T::eq(['issue' => true, 'status' => 'due', 'reason' => null], Lifecycle::step($a, false, null, $rules, '2026-12-11'));
    T::eq('due', Lifecycle::step($sub(['status' => 'due', 'period_end' => '2026-12-18']), true, '2026-12-18', $rules, '2026-12-17')['status']);
    T::eq('overdue', Lifecycle::step($sub(['status' => 'due', 'period_end' => '2026-12-18']), true, '2026-12-18', $rules, '2026-12-18')['status']);
    T::eq('active', Lifecycle::step($sub(['status' => 'due', 'period_end' => '2027-01-18']), true, null, $rules, '2026-12-20')['status'], 'nothing left to pay');
    T::eq('overdue', Lifecycle::step($sub(['status' => 'overdue', 'period_end' => '2026-12-18', 'grace_until' => '2027-01-05']), true, '2026-12-18', $rules, '2026-12-30')['status'], 'admin reactivation gives time');
    T::eq('suspended', Lifecycle::step($sub(['status' => 'overdue', 'period_end' => '2026-12-18', 'grace_until' => '2027-01-05']), true, '2026-12-18', $rules, '2027-01-05')['status']);
    T::eq('suspended', Lifecycle::step($sub(['status' => 'suspended', 'period_end' => '2026-12-18']), true, null, $rules, '2027-02-01')['status'], 'only a payment or the admin ends a suspension');
});

test('Billing: complimentary and cancel at the end of the period', function () use ($rules, $sub) {
    $c = $sub(['status' => 'complimentary', 'period_end' => '2027-02-12']);
    T::eq(['issue' => true, 'status' => 'complimentary', 'reason' => null], Lifecycle::step($c, false, null, $rules, '2027-02-05'), 'invoice before the free period ends');
    T::eq('overdue', Lifecycle::step($c, true, '2027-02-12', $rules, '2027-02-12')['status']);
    $x = $sub(['period_end' => '2026-12-18', 'cancel_at_period_end' => 1]);
    T::eq(['issue' => false, 'status' => 'active', 'reason' => null], Lifecycle::step($x, false, null, $rules, '2026-12-11'), 'no renewal invoice when cancelling');
    T::eq('cancelled', Lifecycle::step($x, false, null, $rules, '2026-12-18')['status']);
});

test('Billing: Sri Lankan mobile numbers', function () {
    T::eq('0771234567', PlatformBilling::phone('0771234567'));
    T::eq('0771234567', PlatformBilling::phone('+94 77 123 4567'));
    T::eq('0771234567', PlatformBilling::phone('0094771234567'));
    T::eq('0771234567', PlatformBilling::phone('771234567'));
    T::eq(null, PlatformBilling::phone('0112345678'), 'landline');
    T::eq(null, PlatformBilling::phone('12345'));
});

<?php
declare(strict_types=1);

namespace Enlixo\Platform;

/**
 * The tutor subscription rules (docs/ADMIN-PLAN.md section 2), with no database: prices, periods and what the
 * daily job must do next. Dates are 'Y-m-d'. A period ends on the first day it does NOT cover (= the renewal / due day).
 *
 *   trial ──(invoice N days before the trial ends)──> trial ──(trial over, not paid)──> overdue
 *   active ──(invoice N days before period end)──> due ──(period over, not paid)──> overdue ──(grace over)──> suspended
 *   any unpaid state ──(invoice paid)──> active          cancel at period end ──(period over)──> cancelled
 */
final class Lifecycle
{
    public const STATUSES = [
        'trial' => 'Trial', 'active' => 'Active', 'due' => 'Payment Due', 'overdue' => 'Overdue',
        'suspended' => 'Suspended', 'cancelled' => 'Cancelled', 'complimentary' => 'Complimentary',
    ];
    public const PACK_SIZE = 50;

    /** Monthly price of the plan for this tutor (custom price wins). */
    public static function monthlyPrice(array $plan, ?float $customPrice): float
    {
        return $customPrice !== null && $customPrice > 0 ? $customPrice : (float) $plan['per_month_amount'];
    }

    /**
     * Invoice lines for one billing period.
     * @param array $plan payment_plans row (payment_plan, per_month_amount, annual_discount, extra_pack_price)
     * @param list<array{name:string, price:?float, charge_model:string}> $addons monthly add-ons the tutor has
     * @return list<array{text:string, amount:float}>
     */
    public static function periodLines(array $plan, string $cycle, ?float $customPrice, int $packs, array $addons): array
    {
        $yearly = $cycle === 'yearly';
        $months = $yearly ? 12 : 1;
        $monthly = self::monthlyPrice($plan, $customPrice);
        $planAmount = $yearly ? max(0.0, $monthly - (float) $plan['annual_discount']) * 12 : $monthly;
        $name = strtoupper((string) $plan['payment_plan']);
        $lines = [['text' => sprintf('%s plan, %s%s', $name, $yearly ? '12 months' : '1 month', $customPrice ? ' (custom price)' : ''), 'amount' => round($planAmount, 2)]];
        if ($packs > 0 && $plan['extra_pack_price'] !== null && (float) $plan['extra_pack_price'] > 0) {
            $lines[] = ['text' => sprintf('Extra students, %d × %d', $packs, self::PACK_SIZE), 'amount' => round($packs * (float) $plan['extra_pack_price'] * $months, 2)];
        }
        foreach ($addons as $a) {
            if ($a['price'] !== null && (float) $a['price'] > 0 && $a['charge_model'] !== 'One-time' && !in_array($a['charge_model'], ['Pay as you go', 'Per conversation'], true)) {
                $lines[] = ['text' => $a['name'], 'amount' => round((float) $a['price'] * $months, 2)];
            }
        }
        return $lines;
    }

    /** @param list<array{amount:float}> $lines */
    public static function total(array $lines, bool $taxOn, float $taxRate): array
    {
        $sub = round(array_sum(array_column($lines, 'amount')), 2);
        $tax = $taxOn ? round($sub * $taxRate / 100, 2) : 0.0;
        return ['subtotal' => $sub, 'tax' => $tax, 'total' => round($sub + $tax, 2)];
    }

    /** First day after a period of one cycle that starts on $from. */
    public static function periodEnd(string $from, string $cycle): string
    {
        $d = new \DateTimeImmutable($from);
        $target = $d->modify('first day of this month')->modify($cycle === 'yearly' ? '+1 year' : '+1 month');
        $day = min((int) $d->format('j'), (int) $target->format('t')); // 31 Jan -> 28 Feb, never 3 Mar
        return $target->setDate((int) $target->format('Y'), (int) $target->format('n'), $day)->format('Y-m-d');
    }

    public static function addDays(string $date, int $days): string
    {
        return (new \DateTimeImmutable($date))->modify(($days >= 0 ? '+' : '') . $days . ' days')->format('Y-m-d');
    }

    public static function daysBetween(string $from, string $to): int
    {
        return (int) (new \DateTimeImmutable($from))->diff(new \DateTimeImmutable($to))->format('%r%a');
    }

    /** Price difference for an upgrade today: the new monthly price minus the old one, for the days left in the period. */
    public static function upgradeDifference(float $oldPeriodAmount, float $newPeriodAmount, string $periodStart, string $periodEnd, string $today): float
    {
        $len = max(1, self::daysBetween($periodStart, $periodEnd));
        $left = max(0, self::daysBetween($today, $periodEnd));
        return max(0.0, round(($newPeriodAmount - $oldPeriodAmount) * $left / $len, 2));
    }

    /** The day the tutor must have paid by: the trial end for a trial, else the end of the paid period. */
    public static function renewalDate(array $sub): ?string
    {
        $status = (string) $sub['status'];
        if ($status === 'trial' || ($sub['period_end'] === null && $sub['trial_ends_at'] !== null)) {
            return $sub['trial_ends_at'];
        }
        return $sub['period_end'];
    }

    /**
     * What the daily job does for one subscription today.
     * @param array $sub status, trial_ends_at, period_end, cancel_at_period_end
     * @param bool $renewalInvoiced an invoice for the period starting on the renewal date exists (open, part or paid)
     * @param ?string $unpaidDueOn due day of the oldest unpaid (open / part) invoice, or null
     * @return array{issue:bool, status:string, reason:?string}
     */
    public static function step(array $sub, bool $renewalInvoiced, ?string $unpaidDueOn, array $rules, string $today): array
    {
        $status = (string) $sub['status'];
        $renewal = self::renewalDate($sub);
        $out = ['issue' => false, 'status' => $status, 'reason' => null];
        if (in_array($status, ['cancelled', 'suspended'], true) || $renewal === null) {
            return $out;
        }
        if ((int) ($sub['cancel_at_period_end'] ?? 0) === 1) {
            if ($today >= $renewal) {
                $out['status'] = 'cancelled';
                $out['reason'] = 'Cancelled at the end of the period';
            }
            return $out;
        }
        // 1. The renewal invoice, N days before the renewal day (a trial gets it at most 3 days before it ends,
        //    so a 7-day trial does not start with a bill)
        $before = max(0, (int) $rules['invoice_days_before']);
        if ($status === 'trial') {
            $before = min($before, 3);
        }
        if (!$renewalInvoiced && $today >= self::addDays($renewal, -$before)) {
            $out['issue'] = true;
            $unpaidDueOn = $unpaidDueOn === null ? $renewal : min($unpaidDueOn, $renewal);
        }
        // 2. Status from the unpaid invoice (the admin's reactivation gives time until grace_until)
        if ($unpaidDueOn !== null) {
            $suspendOn = self::addDays($unpaidDueOn, max(0, (int) $rules['grace_days']));
            if (!empty($sub['grace_until']) && $sub['grace_until'] > $suspendOn) {
                $suspendOn = $sub['grace_until'];
            }
            if ($today >= $suspendOn) {
                $out['status'] = 'suspended';
                $out['reason'] = sprintf('Not paid within %d days of the due date', (int) $rules['grace_days']);
            } elseif ($today >= $unpaidDueOn) {
                $out['status'] = 'overdue';
            } elseif ($status === 'active') {
                $out['status'] = 'due';
            }
        } elseif ($status === 'due' || $status === 'overdue') {
            $out['status'] = 'active'; // nothing left to pay
        }
        return $out;
    }
}

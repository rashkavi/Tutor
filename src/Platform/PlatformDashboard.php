<?php
declare(strict_types=1);

namespace Enlixo\Platform;

use Enlixo\Core\Database;

/** Numbers for admin/dashboard.php, from the same lists the other admin pages show (so they always agree). */
final class PlatformDashboard
{
    public function __construct(
        private readonly Database $db,
        private readonly PlatformBilling $billing,
        private readonly PlatformCatalog $catalog,
        private readonly string $today,
    ) {
    }

    public function overview(): array
    {
        $this->billing->tick(); // the dashboard is never older than today, even before the daily job is set up
        $T = $this->billing->tutors();
        $invoices = $this->billing->invoices();
        $payments = $this->billing->payments();
        $plans = [];
        foreach ($this->catalog->planRows() as $p) {
            $plans[$p['plan_key']] = $p;
        }

        $months = [];
        $first = new \DateTimeImmutable(substr($this->today, 0, 7) . '-01');
        for ($i = 11; $i >= 0; $i--) {
            $months[] = $first->modify("-$i months");
        }
        $keys = array_map(static fn ($d) => $d->format('Y-m'), $months);
        $labels = array_map(static fn ($d) => $d->format('M'), $months);
        $month = substr($this->today, 0, 7);
        $byMonth = static function (array $rows, string $dateKey, ?callable $value = null) use ($keys): array {
            $out = array_fill_keys($keys, 0);
            foreach ($rows as $r) {
                $m = substr((string) $r[$dateKey], 0, 7);
                if (isset($out[$m])) {
                    $out[$m] += $value ? $value($r) : 1;
                }
            }
            return array_values($out);
        };
        $last6 = static fn (array $values) => array_map(static fn ($l, $v) => ['label' => $l, 'value' => $v], array_slice($labels, -6), array_slice($values, -6));

        $paying = array_values(array_filter($T, static fn ($t) => in_array($t['status'], ['active', 'due', 'overdue'], true)));
        $trials = array_values(array_filter($T, static fn ($t) => $t['status'] === 'trial'));
        $endingSoon = array_values(array_filter($trials, fn ($t) => $t['trial_ends'] !== null && Lifecycle::daysBetween($this->today, $t['trial_ends']) <= 3));
        $mrrOf = static fn (array $t) => $t['cycle'] === 'yearly' ? max(0, $t['price'] - (float) ($plans[$t['plan']]['annual_discount'] ?? 0)) : $t['price'];
        $mrr = array_sum(array_map($mrrOf, $paying));
        $mrrBefore = array_sum(array_map($mrrOf, array_filter($paying, static fn ($t) => substr($t['joined'], 0, 7) < $month)));

        $approved = array_values(array_filter($payments, static fn ($p) => $p['status'] === 'approved'));
        $waiting = array_values(array_filter($payments, static fn ($p) => $p['status'] === 'waiting'));
        $collected = $byMonth($approved, 'date', static fn ($p) => $p['amount']);
        $invoiced = $byMonth(array_filter($invoices, static fn ($i) => $i['status'] !== 'void'), 'issued', static fn ($i) => $i['total']);
        $overdue = array_values(array_filter($invoices, static fn ($i) => $i['status'] === 'overdue'));
        $openThisMonth = array_sum(array_map(static fn ($i) => $i['total'] - $i['paid'], array_filter($invoices, static fn ($i) => in_array($i['status'], ['open', 'part'], true) && substr($i['due'], 0, 7) <= $month)));

        $logs = $this->db->all("SELECT action, created_at FROM platform_billing_logs WHERE action IN ('added_trial','trial_converted','status_cancelled') AND created_at >= :f", ['f' => $months[0]->format('Y-m-d')]);
        $of = static fn (string $a) => array_values(array_filter($logs, static fn ($l) => $l['action'] === $a));
        $joined = $byMonth($T, 'joined');
        $trialsStarted = $byMonth($of('added_trial'), 'created_at');
        $requests = (int) $this->db->value('SELECT COUNT(*) FROM requested_users WHERE request_status_request_status_id = 1');

        return [
            'stats' => [
                'paying' => ['value' => count($paying), 'new' => $joined[11], 'bars' => $last6($joined)],
                'trials' => ['value' => count($trials), 'ending' => count($endingSoon), 'bars' => $last6($trialsStarted)],
                'mrr' => ['value' => round($mrr, 2), 'before' => round($mrrBefore, 2), 'bars' => $last6($invoiced)],
                'collected' => ['value' => $collected[11], 'expected' => $collected[11] + array_sum(array_column($waiting, 'amount')) + $openThisMonth, 'bars' => $last6($collected)],
                'overdue' => ['value' => array_sum(array_map(static fn ($i) => $i['total'] - $i['paid'], $overdue)), 'tutors' => count(array_unique(array_column($overdue, 'tutor_id')))],
                'slips' => ['value' => count($waiting), 'amount' => array_sum(array_column($waiting, 'amount'))],
            ],
            'charts' => [
                'revenue' => ['labels' => $labels, 'collected' => $collected, 'invoiced' => $invoiced],
                'plans' => array_map(static fn ($p) => ['label' => strtoupper((string) $p['payment_plan']), 'value' => count(array_filter($paying, static fn ($t) => $t['plan'] === $p['plan_key']))], array_values($plans)),
                'trials' => ['labels' => array_slice($labels, -6), 'started' => array_slice($trialsStarted, -6), 'converted' => array_slice($byMonth($of('trial_converted'), 'created_at'), -6)],
                'growth' => ['labels' => array_slice($labels, -6), 'joined' => array_slice($joined, -6), 'cancelled' => array_slice($byMonth($of('status_cancelled'), 'created_at'), -6)],
            ],
            'todo' => [
                ['icon' => 'receipt', 'tone' => '', 'label' => 'Payment slips to check', 'count' => count($waiting), 'href' => 'tutor-payments?status=waiting'],
                ['icon' => 'hourglass', 'tone' => 'orange', 'label' => 'Trials ending in 3 days', 'count' => count($endingSoon), 'href' => 'tutors?status=trial'],
                ['icon' => 'warning', 'tone' => 'red', 'label' => 'Overdue invoices', 'count' => count($overdue), 'href' => 'invoices?status=overdue'],
                ['icon' => 'user-add', 'tone' => 'purple', 'label' => 'Tutor sign-up requests', 'count' => $requests, 'href' => 'requested-users'],
            ],
            'recent' => array_slice(array_values(array_filter($payments, static fn ($p) => $p['status'] !== 'rejected')), 0, 6),
            'near_limit' => array_slice(array_values(array_filter($T, static fn ($t) => $t['limit'] && $t['students'] / $t['limit'] >= 0.85 && $t['status'] !== 'cancelled')), 0, 5),
            'trials_list' => array_slice($trials, 0, 5),
        ];
    }
}

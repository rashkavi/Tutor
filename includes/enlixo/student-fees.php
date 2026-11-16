<?php
/**
 * Student fees (portal Payments and Class Payments), laid out like the ENLIXO V3 student portal Payments page:
 * a balance card with a "paid" ring and a bar of how the fee is covered, the next payment to make, and a timeline of
 * every fee (one group per class) with All / To Pay / Being Checked / Paid tabs. Phone first: everything stacks.
 * Read only; paying goes to pay-payment, the details to payment-details.
 */
require_once __DIR__ . '/student-learning.php';
require_once __DIR__ . '/icons.php';

if (!function_exists('sf_fees')) {
    /** every fee row of the student (one class when $classId > 0), oldest due first, with the money worked out */
    function sf_fees(int $studentId, int $classId = 0): array
    {
        $rows = [];
        $q = Database::search(
            "SELECT p.student_has_payment_id id, p.amount, p.discount, p.discount_type_discount_type_id discount_type, p.due_date,
                    p.refference, p.date_paid, p.payment_status_payment_status_id status, c.class_id, c.class_name,
                    (SELECT COALESCE(SUM(x.amount), 0) FROM student_has_penalty_payments x WHERE x.student_has_payments_student_has_payment_id = p.student_has_payment_id) penalty
               FROM student_has_payments p
               JOIN students_has_classes h ON h.students_has_class_id = p.students_has_class_students_has_class_id
               JOIN classes c ON c.class_id = h.classes_class_id
              WHERE h.students_student_id = '" . (int) $studentId . "'" . ($classId > 0 ? " AND c.class_id = '" . (int) $classId . "'" : '') . "
              ORDER BY p.due_date, p.student_has_payment_id"
        );
        $today = date('Y-m-d');
        while ($r = $q->fetch_assoc()) {
            $amount = (float) $r['amount'];
            $off = (int) $r['discount_type'] === 3 ? $amount * (float) $r['discount'] / 100 : ((int) $r['discount_type'] === 2 ? (float) $r['discount'] : 0.0);
            $off = min($amount, max(0.0, $off));
            $penalty = (float) $r['penalty'];
            $payable = max(0.0, $amount - $off + $penalty);
            $status = (int) $r['status'];
            $due = $r['due_date'] ? substr((string) $r['due_date'], 0, 10) : null;
            $days = $due ? (int) floor((strtotime($due) - strtotime($today)) / 86400) : null;
            // state: paid / waiting (slip sent, being checked) / rejected (slip not accepted) / overdue / due
            $state = match ($status) {
                3 => 'paid',
                2 => 'waiting',
                4 => 'rejected',
                default => ($days !== null && $days < 0 ? 'overdue' : 'due'),
            };
            $rows[] = $r + [
                'off' => $off, 'payable' => $payable, 'state' => $state, 'days' => $days, 'due' => $due,
                'paid' => $status === 3 ? $payable : 0.0,
                'checking' => $status === 2 ? $payable : 0.0,
                'balance' => in_array($status, [2, 3], true) ? 0.0 : $payable,
            ];
        }
        return $rows;
    }

    function sf_money(float $v): string
    {
        return 'LKR ' . number_format($v, 2);
    }

    function sf_due_text(array $p): string
    {
        if ($p['state'] === 'paid') {
            return 'Paid' . ($p['date_paid'] ? ' on ' . date('d M Y', strtotime((string) $p['date_paid'])) : '');
        }
        if ($p['state'] === 'waiting') {
            return 'Slip sent, your tutor is checking it';
        }
        if ($p['state'] === 'rejected') {
            return 'Your slip was not accepted';
        }
        $d = $p['days'];
        if ($d === null) {
            return 'No due date';
        }
        return match (true) {
            $d < -1 => abs($d) . ' days overdue',
            $d === -1 => '1 day overdue',
            $d === 0 => 'Due today',
            $d === 1 => 'Due tomorrow',
            default => "Due in {$d} days",
        };
    }

    /** [label, badge class] of a row */
    function sf_chip(array $p): array
    {
        return match ($p['state']) {
            'paid' => ['Paid', 'is-good'],
            'waiting' => ['Being checked', 'is-purple'],
            'rejected' => ['Slip rejected', 'is-bad'],
            'overdue' => ['Overdue', 'is-bad'],
            default => ['To pay', 'is-warn'],
        };
    }

    /** the whole page body: overview, next payment, timeline */
    function sf_render(array $rows, string $emptyText): void
    {
        $e = 'sl_e';
        if (!$rows) { ?>
            <div class="ac-card">
                <div class="empty-state">
                    <div class="empty-state-icon"><?= enlixo_icon('wallet', 28) ?></div>
                    <div class="empty-state-title">No fees yet</div>
                    <div class="empty-state-text"><?= $e($emptyText) ?></div>
                </div>
            </div>
        <?php return;
        }
        $sum = fn (string $k) => array_sum(array_column($rows, $k));
        $paid = $sum('paid');
        $checking = $sum('checking');
        $balance = $sum('balance');
        $saved = $sum('off');
        $payable = $sum('payable');
        $overdue = array_sum(array_map(fn ($p) => in_array($p['state'], ['overdue', 'rejected'], true) ? $p['balance'] : 0, $rows));
        $pct = $payable > 0 ? (int) min(100, round($paid / $payable * 100)) : 0;
        $whole = max(0.01, $paid + $checking + $balance + $saved);
        $share = fn (float $v) => round(max(0, min(100, $v / $whole * 100)), 2);

        // next payment: the first one with something to pay (rejected and overdue come first by due date)
        $next = null;
        foreach ($rows as $p) {
            if ($p['balance'] > 0.004) { $next = $p; break; }
        }
        $counts = ['all' => count($rows), 'topay' => 0, 'waiting' => 0, 'paid' => 0];
        $groups = [];
        foreach ($rows as $p) {
            $groups[(int) $p['class_id']][] = $p;
            if ($p['balance'] > 0.004) $counts['topay']++;
            if ($p['state'] === 'waiting') $counts['waiting']++;
            if ($p['state'] === 'paid') $counts['paid']++;
        }
        ?>
        <div class="sf-overview">
            <div class="ac-card sf-balance">
                <div class="sf-balance-top">
                    <div class="min-w-0">
                        <span class="sf-label"><?= enlixo_icon('wallet', 18) ?>Balance to pay</span>
                        <div class="sf-balance-value"><?= $e(sf_money($balance)) ?></div>
                        <span class="sf-note">
                            <?php if ($overdue > 0.004) { ?>
                                <strong><?= $e(sf_money($overdue)) ?> is overdue.</strong> Please pay it as soon as you can.
                            <?php } elseif ($balance <= 0.004) { ?>
                                Everything is paid<?= $checking > 0 ? ' or sent for checking' : '' ?>. Thank you!
                            <?php } else { ?>
                                Nothing overdue. Total <?= $e(sf_money($payable)) ?> after discounts.
                            <?php } ?>
                        </span>
                    </div>
                    <div class="sf-ring" style="--pct: <?= $pct ?>" role="img" aria-label="<?= $pct ?>% paid"><span><strong><?= $pct ?>%</strong><small>paid</small></span></div>
                </div>
                <div class="sf-bar" role="img" aria-label="How your fees are covered">
                    <span class="is-paid" style="width: <?= $share($paid) ?>%"></span>
                    <span class="is-waiting" style="width: <?= $share($checking) ?>%"></span>
                    <span class="is-saved" style="width: <?= $share($saved) ?>%"></span>
                </div>
                <div class="sf-legend">
                    <div class="is-paid"><span>Paid</span><strong><?= $e(sf_money($paid)) ?></strong></div>
                    <div class="is-waiting"><span>Being checked</span><strong><?= $e(sf_money($checking)) ?></strong></div>
                    <div class="is-saved"><span>Discounts</span><strong><?= $e(sf_money($saved)) ?></strong></div>
                    <div class="is-left"><span>Still to pay</span><strong><?= $e(sf_money($balance)) ?></strong></div>
                </div>
            </div>
            <?php if ($next) {
                $late = in_array($next['state'], ['overdue', 'rejected'], true); ?>
                <div class="ac-card sf-next <?= $late ? 'is-late' : '' ?>" id="sfNext">
                    <span class="sf-label"><?= enlixo_icon($late ? 'warning' : 'calendar', 16) ?><?= $next['state'] === 'rejected' ? 'Pay again' : ($late ? 'Overdue payment' : 'Next payment') ?></span>
                    <span class="sf-next-title"><?= $e($next['class_name']) ?></span>
                    <span class="sf-next-meta"><?= $next['due'] ? $e(date('F Y', strtotime($next['due']))) . ' fee · ' : '' ?>Ref <?= $e($next['refference'] ?: '#' . $next['id']) ?></span>
                    <div class="sf-next-amount"><?= $e(sf_money($next['balance'])) ?></div>
                    <span class="sf-countdown"><?= enlixo_icon('clock', 14) ?><?= $e(sf_due_text($next)) ?><?= $next['due'] ? ' · ' . $e(date('d M Y', strtotime($next['due']))) : '' ?></span>
                    <div class="sf-next-actions">
                        <?php if (in_array((int) $next['status'], [1, 4], true)) { ?>
                            <a class="custom-btn" href="pay-payment?payment-id=<?= (int) $next['id'] ?>"><?= enlixo_icon('upload', 16) ?>Pay Now</a>
                        <?php } ?>
                        <a class="custom-outline-btn" href="payment-details?payment-id=<?= (int) $next['id'] ?>">Details</a>
                    </div>
                </div>
            <?php } else { ?>
                <div class="ac-card sf-next is-clear">
                    <span class="sf-label"><?= enlixo_icon('check', 16) ?>All caught up</span>
                    <span class="sf-next-title">Nothing left to pay</span>
                    <span class="sf-next-meta">Slips you sent recently may still be checked by your tutor.</span>
                </div>
            <?php } ?>
        </div>

        <div class="ac-card sf-timeline-card" data-sf-list>
            <div class="sf-head">
                <div>
                    <h2 class="ac-card-title m-0">Payment Timeline</h2>
                    <span class="text-13 sub-text">Every class fee, in the order it is due</span>
                </div>
                <div class="ac-tabs" role="tablist" aria-label="Show payments">
                    <?php foreach (['all' => 'All', 'topay' => 'To Pay', 'waiting' => 'Being Checked', 'paid' => 'Paid'] as $k => $label) { ?>
                        <button type="button" class="ac-tab <?= $k === 'all' ? 'is-active' : '' ?>" data-filter="<?= $k ?>" role="tab" aria-selected="<?= $k === 'all' ? 'true' : 'false' ?>"><?= $label ?> <span class="ac-tab-count"><?= $counts[$k] ?></span></button>
                    <?php } ?>
                </div>
            </div>
            <?php foreach ($groups as $items) {
                $donePaid = count(array_filter($items, fn ($p) => $p['state'] === 'paid')); ?>
                <div class="sf-group">
                    <div class="sf-group-head">
                        <div class="min-w-0">
                            <span class="sf-group-name"><?= $e($items[0]['class_name']) ?></span>
                            <span class="sf-group-meta"><?= $donePaid ?> of <?= count($items) ?> paid</span>
                        </div>
                        <div class="sf-steps" aria-hidden="true"><?php foreach ($items as $p) { ?><i class="is-<?= $p['state'] ?>"></i><?php } ?></div>
                    </div>
                    <?php foreach ($items as $p) {
                        [$chip, $chipClass] = sf_chip($p);
                        $f = ['all'];
                        if ($p['balance'] > 0.004) $f[] = 'topay';
                        if ($p['state'] === 'waiting') $f[] = 'waiting';
                        if ($p['state'] === 'paid') $f[] = 'paid'; ?>
                        <div class="sf-inst is-<?= $p['state'] ?>" data-filters="<?= implode(' ', $f) ?>">
                            <div class="sf-date">
                                <?php if ($p['due']) { ?><strong><?= date('d', strtotime($p['due'])) ?></strong><span><?= date('M', strtotime($p['due'])) ?></span><?php } else { echo enlixo_icon('calendar', 20); } ?>
                            </div>
                            <div class="sf-inst-body">
                                <div class="sf-inst-name"><?= $p['due'] ? $e(date('F Y', strtotime($p['due']))) : 'Class fee' ?> <span class="ac-badge <?= $chipClass ?>"><?= $e($chip) ?></span></div>
                                <div class="sf-inst-when"><strong><?= $e(sf_due_text($p)) ?></strong> · Ref <?= $e($p['refference'] ?: '#' . $p['id']) ?></div>
                                <div class="sf-inst-math">
                                    <span>Fee <?= $e(number_format((float) $p['amount'], 2)) ?></span>
                                    <?php if ($p['off'] > 0.004) { ?><span class="is-off">Discount − <?= $e(number_format($p['off'], 2)) ?></span><?php } ?>
                                    <?php if ((float) $p['penalty'] > 0.004) { ?><span class="is-add">Late fee + <?= $e(number_format((float) $p['penalty'], 2)) ?></span><?php } ?>
                                    <span>You pay <?= $e(number_format($p['payable'], 2)) ?></span>
                                </div>
                            </div>
                            <div class="sf-inst-end">
                                <div class="sf-inst-amount"><?= $e(sf_money($p['balance'] > 0.004 ? $p['balance'] : $p['payable'])) ?></div>
                                <div class="sf-inst-actions">
                                    <?php if (in_array((int) $p['status'], [1, 4], true)) { ?>
                                        <a class="custom-btn table-btn" href="pay-payment?payment-id=<?= (int) $p['id'] ?>">Pay</a>
                                    <?php } ?>
                                    <a class="custom-outline-btn table-btn" href="payment-details?payment-id=<?= (int) $p['id'] ?>">Details</a>
                                </div>
                            </div>
                        </div>
                    <?php } ?>
                </div>
            <?php } ?>
            <div class="empty-state d-none" data-sf-empty>
                <div class="empty-state-icon"><?= enlixo_icon('inbox', 28) ?></div>
                <div class="empty-state-title">Nothing here</div>
                <div class="empty-state-text">No payments match this tab.</div>
            </div>
        </div>
        <script>
            (() => {
                const list = document.querySelector('[data-sf-list]');
                if (!list) return;
                const tabs = [...list.querySelectorAll('.ac-tab')];
                tabs.forEach((t) => t.addEventListener('click', () => {
                    tabs.forEach((x) => { x.classList.toggle('is-active', x === t); x.setAttribute('aria-selected', x === t ? 'true' : 'false'); });
                    let shown = 0;
                    list.querySelectorAll('.sf-group').forEach((g) => {
                        let inGroup = 0;
                        g.querySelectorAll('.sf-inst').forEach((r) => { const on = r.dataset.filters.split(' ').includes(t.dataset.filter); r.classList.toggle('d-none', !on); inGroup += on; });
                        g.classList.toggle('d-none', !inGroup);
                        shown += inGroup;
                    });
                    list.querySelector('[data-sf-empty]').classList.toggle('d-none', shown > 0);
                }));
            })();
        </script>
        <?php
    }
}

<?php
declare(strict_types=1);

namespace Enlixo\Platform;

use Enlixo\Core\Database;
use Enlixo\Core\HttpException;

/**
 * Tutor subscriptions, invoices and payments: the one place that changes them (admin pages, the tutor's
 * My Subscription page and the daily job all come here). Rules without a database: Lifecycle.
 *
 * Tables: users_has_payment_plans (subscription, latest row per tutor), platform_invoices (+ lines),
 * users_has_payment_plans_payments (+ users_payments_slips), tutor_addons, platform_billing_logs.
 * Payment status ids (legacy payment_status): 2 waiting for approval, 3 accepted, 4 rejected.
 */
final class PlatformBilling
{
    private const WAITING = 2;
    private const ACCEPTED = 3;
    private const REJECTED = 4;
    private const TUTOR_ROLE = 2;
    private const SLIP_TYPES = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'application/pdf' => 'pdf'];
    private const SLIP_MAX = 5 * 1024 * 1024;
    public const METHODS = ['Bank transfer' => 1, 'Bank deposit' => 2, 'Cash' => 3, 'Card' => 4, 'Cheque' => 5];
    public const REJECT_REASONS = [
        'The slip amount does not match the invoice', 'The slip is not clear', 'The money has not reached our account', 'The slip was used before', 'Other',
    ];

    public function __construct(
        private readonly Database $db,
        private readonly PlatformCatalog $catalog,
        private readonly PlatformSettings $settings,
        private readonly string $slipDir,
        private readonly string $today, // 'Y-m-d', Asia/Colombo
    ) {
    }

    // ================================================================ reading

    /** Latest subscription row of every tutor (or one), with the user and plan. */
    private function subRows(?int $tutorId = null): array
    {
        return $this->db->all(
            'SELECT s.*, u.user_id, u.title_title_id, t.title, u.user_fname, u.user_lname, u.user_email, u.user_mobile, u.user_whatsapp,
                    u.user_reg_date, u.status_status_id AS user_status, p.plan_key, p.payment_plan
               FROM users u
               JOIN users_has_payment_plans s ON s.users_has_payment_plan_id =
                    (SELECT MAX(x.users_has_payment_plan_id) FROM users_has_payment_plans x WHERE x.users_user_id = u.user_id)
               JOIN payment_plans p ON p.payment_plan_id = s.payment_plans_payment_plan_id
               LEFT JOIN title t ON t.title_id = u.title_title_id
              WHERE u.user_roles_user_role_id = :role' . ($tutorId ? ' AND u.user_id = :id' : '') . '
              ORDER BY u.user_id DESC',
            $tutorId ? ['role' => self::TUTOR_ROLE, 'id' => $tutorId] : ['role' => self::TUTOR_ROLE],
        );
    }

    private function subRow(int $tutorId): array
    {
        return $this->subRows($tutorId)[0] ?? throw HttpException::notFound('Tutor not found.');
    }

    /** @return array<int,int> tutor id => active students */
    private function studentCounts(?int $tutorId = null): array
    {
        $rows = $this->db->all(
            'SELECT c.tutor_id, COUNT(DISTINCT shc.students_student_id) AS n FROM students_has_classes shc
               JOIN classes c ON c.class_id = shc.classes_class_id
              WHERE shc.status_status_id = 1' . ($tutorId ? ' AND c.tutor_id = :t' : '') . ' GROUP BY c.tutor_id',
            $tutorId ? ['t' => $tutorId] : [],
        );
        return array_column($rows, 'n', 'tutor_id');
    }

    /** @return array<int, list<array>> tutor id => add-ons that are on now */
    private function tutorAddons(?int $tutorId = null): array
    {
        $out = [];
        foreach ($this->db->all(
            'SELECT ta.tutor_id, a.addon_key, a.name, a.price, a.charge_model FROM tutor_addons ta JOIN addons a ON a.addon_id = ta.addons_addon_id
              WHERE ta.starts_at <= NOW() AND (ta.ends_at IS NULL OR ta.ends_at > NOW())' . ($tutorId ? ' AND ta.tutor_id = :t' : '') . ' ORDER BY a.addon_id',
            $tutorId ? ['t' => $tutorId] : [],
        ) as $r) {
            $out[(int) $r['tutor_id']][] = $r;
        }
        return $out;
    }

    /** @return array<int,float> tutor id => what is still to pay on open invoices */
    private function balances(?int $tutorId = null): array
    {
        $rows = $this->db->all(
            "SELECT users_user_id, SUM(total - paid) AS b FROM platform_invoices WHERE status IN ('open','part')" . ($tutorId ? ' AND users_user_id = :t' : '') . ' GROUP BY users_user_id',
            $tutorId ? ['t' => $tutorId] : [],
        );
        return array_map('floatval', array_column($rows, 'b', 'users_user_id'));
    }

    private function lastLogins(?int $tutorId = null): array
    {
        $rows = $this->db->all('SELECT users_user_id, MAX(last_active) AS l FROM users_devices' . ($tutorId ? ' WHERE users_user_id = :t' : '') . ' GROUP BY users_user_id', $tutorId ? ['t' => $tutorId] : []);
        return array_column($rows, 'l', 'users_user_id');
    }

    private function tutorDto(array $s, int $students, array $addons, float $balance, ?string $lastLogin): array
    {
        $rules = $this->settings->rules();
        $plan = $this->catalog->planById((int) $s['payment_plans_payment_plan_id']);
        $status = (string) ($s['status'] ?: 'trial');
        $limit = $status === 'trial' ? (int) $rules['trial_student_cap']
            : ($plan['student_limit'] === null ? null : (int) $plan['student_limit'] + (int) $s['extra_packs'] * Lifecycle::PACK_SIZE);
        $custom = $s['custom_price'] !== null ? (float) $s['custom_price'] : null;
        $name = trim($s['user_fname'] . ' ' . $s['user_lname']);
        return [
            'id' => (int) $s['user_id'], 'title' => $s['title'] ? rtrim((string) $s['title'], '.') . '.' : '', 'first_name' => $s['user_fname'], 'last_name' => $s['user_lname'], 'name' => $name,
            'brand' => (string) ($s['institute_name'] ?? ''), 'subject' => (string) ($s['main_subject'] ?? ''), 'note' => $s['admin_note'],
            'email' => $s['user_email'], 'mobile' => (string) $s['user_mobile'], 'whatsapp' => (string) ($s['user_whatsapp'] ?? ''),
            'joined' => substr((string) $s['user_reg_date'], 0, 10),
            'plan' => $s['plan_key'], 'plan_name' => strtoupper((string) $s['payment_plan']), 'cycle' => $s['billing_cycle'],
            'status' => $status, 'status_label' => Lifecycle::STATUSES[$status] ?? $status, 'status_reason' => $s['status_reason'],
            'trial_ends' => $s['trial_ends_at'], 'period_start' => $s['period_start'],
            'period_end' => $status === 'trial' ? $s['trial_ends_at'] : ($s['period_end'] ?? $s['trial_ends_at']),
            'cancel_at_period_end' => (bool) $s['cancel_at_period_end'],
            'next_plan' => $s['next_plan_id'] ? $this->catalog->planById((int) $s['next_plan_id'])['plan_key'] ?? null : null,
            'students' => $students, 'limit' => $limit, 'extra_packs' => (int) $s['extra_packs'],
            'price' => Lifecycle::monthlyPrice($plan, $custom), 'custom_price' => $custom !== null ? ($s['custom_price_reason'] ?: 'Custom price') : null,
            'addons' => array_column($addons, 'addon_key'), 'addon_names' => array_column($addons, 'name'),
            'balance' => round($balance, 2), 'last_login' => $lastLogin ? substr($lastLogin, 0, 10) : null,
            'trial_extended' => (bool) $s['trial_extended'],
        ];
    }

    /** @return list<array> every tutor as the admin lists show them */
    public function tutors(): array
    {
        $students = $this->studentCounts();
        $addons = $this->tutorAddons();
        $bal = $this->balances();
        $logins = $this->lastLogins();
        return array_map(fn (array $s) => $this->tutorDto($s, (int) ($students[$s['user_id']] ?? 0), $addons[(int) $s['user_id']] ?? [], (float) ($bal[$s['user_id']] ?? 0), $logins[$s['user_id']] ?? null), $this->subRows());
    }

    public function tutor(int $id): array
    {
        $s = $this->subRow($id);
        return $this->tutorDto($s, (int) ($this->studentCounts($id)[$id] ?? 0), $this->tutorAddons($id)[$id] ?? [], (float) ($this->balances($id)[$id] ?? 0), $this->lastLogins($id)[$id] ?? null);
    }

    /** Invoices (newest first) for everyone or one tutor. */
    public function invoices(?int $tutorId = null, ?int $invoiceId = null): array
    {
        $where = [];
        $p = [];
        if ($tutorId) {
            $where[] = 'i.users_user_id = :t';
            $p['t'] = $tutorId;
        }
        if ($invoiceId) {
            $where[] = 'i.platform_invoice_id = :i';
            $p['i'] = $invoiceId;
        }
        $rows = $this->db->all(
            'SELECT i.*, u.user_fname, u.user_lname, s.institute_name FROM platform_invoices i
               JOIN users u ON u.user_id = i.users_user_id
               JOIN users_has_payment_plans s ON s.users_has_payment_plan_id = i.users_has_payment_plan_id'
            . ($where ? ' WHERE ' . implode(' AND ', $where) : '') . ' ORDER BY i.issued_on DESC, i.platform_invoice_id DESC',
            $p,
        );
        $lines = [];
        if ($rows) {
            $ids = array_map('intval', array_column($rows, 'platform_invoice_id'));
            foreach ($this->db->all('SELECT platform_invoice_id, description, amount FROM platform_invoice_lines WHERE platform_invoice_id IN (' . implode(',', $ids) . ') ORDER BY sort_order, platform_invoice_line_id') as $l) {
                $lines[(int) $l['platform_invoice_id']][] = ['text' => $l['description'], 'amount' => (float) $l['amount']];
            }
        }
        return array_map(function (array $i) use ($lines) {
            $status = $i['status'];
            if (in_array($status, ['open', 'part'], true) && $i['due_on'] < $this->today) {
                $status = 'overdue';
            }
            return [
                'id' => (int) $i['platform_invoice_id'], 'number' => $i['invoice_number'], 'tutor_id' => (int) $i['users_user_id'],
                'tutor' => trim($i['user_fname'] . ' ' . $i['user_lname']), 'brand' => (string) ($i['institute_name'] ?? ''),
                'kind' => $i['kind'], 'plan' => $i['plan_name'], 'cycle' => $i['billing_cycle'],
                'period_from' => $i['period_from'], 'period_to' => $i['period_to'] ? Lifecycle::addDays($i['period_to'], -1) : null,
                'issued' => $i['issued_on'], 'due' => $i['due_on'], 'lines' => $lines[(int) $i['platform_invoice_id']] ?? [],
                'subtotal' => (float) $i['subtotal'], 'tax' => (float) $i['tax'], 'total' => (float) $i['total'], 'paid' => (float) $i['paid'],
                'status' => $status, 'void_reason' => $i['void_reason'],
            ];
        }, $rows);
    }

    /** Payments (newest first): slips uploaded by tutors and payments recorded by the admin. */
    public function payments(?int $tutorId = null, ?int $paymentId = null): array
    {
        $where = ['pay.payment_status_payment_status_id IN (2,3,4)'];
        $p = [];
        if ($tutorId) {
            $where[] = 's.users_user_id = :t';
            $p['t'] = $tutorId;
        }
        if ($paymentId) {
            $where[] = 'pay.users_has_payment_plans_payment_id = :id';
            $p['id'] = $paymentId;
        }
        $rows = $this->db->all(
            'SELECT pay.*, s.users_user_id, u.user_fname, u.user_lname, i.invoice_number, pm.payment_method,
                    CONCAT(c.user_fname, \' \', c.user_lname) AS checker, (SELECT COUNT(*) FROM users_payments_slips sl WHERE sl.users_has_payment_plans_payment_id = pay.users_has_payment_plans_payment_id) AS slips
               FROM users_has_payment_plans_payments pay
               JOIN users_has_payment_plans s ON s.users_has_payment_plan_id = pay.users_has_payment_plans_users_has_payment_plan_id
               JOIN users u ON u.user_id = s.users_user_id
               LEFT JOIN platform_invoices i ON i.platform_invoice_id = pay.platform_invoice_id
               LEFT JOIN payment_method pm ON pm.payment_method_id = pay.payment_method_payment_method_id
               LEFT JOIN users c ON c.user_id = pay.checked_by
              WHERE ' . implode(' AND ', $where) . '
              ORDER BY COALESCE(pay.date_paid, pay.date_schedule) DESC, pay.users_has_payment_plans_payment_id DESC',
            $p,
        );
        return array_map(static fn (array $r) => [
            'id' => (int) $r['users_has_payment_plans_payment_id'], 'ref' => $r['refference'] !== '0' ? $r['refference'] : 'PAY-' . $r['users_has_payment_plans_payment_id'],
            'tutor_id' => (int) $r['users_user_id'], 'tutor' => trim($r['user_fname'] . ' ' . $r['user_lname']),
            'invoice' => $r['invoice_number'] ?? 'Old subscription payment', 'invoice_id' => $r['platform_invoice_id'] ? (int) $r['platform_invoice_id'] : null,
            'amount' => (float) $r['paid_amount'], 'method' => array_search((int) $r['payment_method_payment_method_id'], self::METHODS, true) ?: ($r['payment_method'] ?? 'Bank transfer'),
            'date' => substr((string) ($r['date_paid'] ?? $r['date_schedule']), 0, 10), 'reference' => $r['bank_reference'],
            'status' => [2 => 'waiting', 3 => 'approved', 4 => 'rejected'][(int) $r['payment_status_payment_status_id']],
            'slip' => (int) $r['slips'] > 0, 'recorded' => $r['recorded_by'] !== null,
            'checked_by' => $r['checked_by'] ? trim((string) $r['checker']) : ($r['recorded_by'] !== null ? 'Recorded by ENLIXO' : null),
            'checked_at' => $r['checked_at'], 'reason' => $r['reject_reason'],
        ], $rows);
    }

    public function history(int $tutorId): array
    {
        return array_map(static fn (array $r) => [
            'when' => substr((string) $r['created_at'], 0, 16), 'by' => $r['done_by'] === null ? 'ENLIXO (automatic)' : trim((string) $r['by_name']),
            'what' => $r['summary'], 'reason' => $r['reason'], 'action' => $r['action'],
        ], $this->db->all(
            'SELECT l.*, CONCAT(u.user_fname, \' \', u.user_lname) AS by_name FROM platform_billing_logs l LEFT JOIN users u ON u.user_id = l.done_by
              WHERE l.users_user_id = :t ORDER BY l.created_at DESC, l.platform_billing_log_id DESC LIMIT 200',
            ['t' => $tutorId],
        ));
    }

    /** Everything Tutor details (admin) and My Subscription (tutor) show. */
    public function account(int $tutorId, bool $forTutor): array
    {
        $this->tickTutor($tutorId);
        $t = $this->tutor($tutorId);
        $plans = $this->catalog->plans();
        $data = [
            'tutor' => $t,
            'plan' => current(array_filter($plans, static fn ($p) => $p['key'] === $t['plan'])),
            'plans' => $plans,
            'invoices' => $this->invoices($tutorId),
            'payments' => $this->payments($tutorId),
            'addons' => $this->catalog->addons(),
            'rules' => $this->publicRules(),
        ];
        if ($forTutor) {
            $data['banks'] = array_values(array_filter($this->settings->banks(), static fn ($b) => !empty($b['on'])));
            unset($data['tutor']['note']);
        } else {
            $data['history'] = $this->history($tutorId);
        }
        return $data;
    }

    public function publicRules(): array
    {
        $r = $this->settings->rules();
        $r['reminders'] = 'On the tutor\'s pages from the day the invoice is made; e-mail when you press Send Reminder';
        return $r;
    }

    /** Status of the tutor's subscription for the access gate, or null (no row, or migration 007 not run yet). */
    public function accessStatus(int $tutorId): ?string
    {
        try {
            $v = $this->db->value('SELECT status FROM users_has_payment_plans WHERE users_user_id = :t ORDER BY users_has_payment_plan_id DESC LIMIT 1', ['t' => $tutorId]);
            return $v === null ? null : (string) $v;
        } catch (\PDOException) {
            return null;
        }
    }

    // ================================================================ daily job

    /** Runs the life cycle for every tutor. @return array{invoices:int, changed:int} */
    public function tick(): array
    {
        $n = ['invoices' => 0, 'changed' => 0];
        foreach ($this->db->all('SELECT DISTINCT users_user_id FROM users_has_payment_plans') as $r) {
            $res = $this->tickTutor((int) $r['users_user_id']);
            $n['invoices'] += $res['issued'] ? 1 : 0;
            $n['changed'] += $res['changed'] ? 1 : 0;
        }
        return $n;
    }

    /** @return array{issued:bool, changed:bool} */
    public function tickTutor(int $tutorId): array
    {
        return $this->db->transaction(function () use ($tutorId) {
            $s = $this->db->one('SELECT * FROM users_has_payment_plans WHERE users_user_id = :t ORDER BY users_has_payment_plan_id DESC LIMIT 1 FOR UPDATE', ['t' => $tutorId]);
            if ($s === null) {
                return ['issued' => false, 'changed' => false];
            }
            $s['status'] = $s['status'] ?: 'trial';
            $rules = $this->settings->rules();
            $renewal = Lifecycle::renewalDate($s);
            $invoiced = $renewal !== null && (bool) $this->db->value(
                "SELECT 1 FROM platform_invoices WHERE users_has_payment_plan_id = :s AND kind = 'period' AND period_from = :f AND status <> 'void'",
                ['s' => $s['users_has_payment_plan_id'], 'f' => $renewal],
            );
            $unpaid = $this->db->value("SELECT MIN(due_on) FROM platform_invoices WHERE users_has_payment_plan_id = :s AND status IN ('open','part')", ['s' => $s['users_has_payment_plan_id']]);
            $step = Lifecycle::step($s, $invoiced, $unpaid, $rules, $this->today);
            if ($step['issue']) {
                $this->issuePeriodInvoice($s, $renewal, null);
            }
            $changed = $step['status'] !== $s['status'];
            if ($changed) {
                $this->setStatus($s, $step['status'], $step['reason'], null, $step['status'] === 'suspended' ? 'Suspended automatically' : 'Status changed to ' . Lifecycle::STATUSES[$step['status']]);
            }
            return ['issued' => $step['issue'], 'changed' => $changed];
        });
    }

    private function setStatus(array $s, string $status, ?string $reason, ?int $by, string $summary, ?string $logReason = null): void
    {
        $this->db->execute(
            'UPDATE users_has_payment_plans SET status = :st, status_reason = :r, status_changed_at = NOW() WHERE users_has_payment_plan_id = :id',
            ['st' => $status, 'r' => $reason, 'id' => $s['users_has_payment_plan_id']],
        );
        $this->log((int) $s['users_user_id'], 'status_' . $status, $summary, $logReason ?? ($by === null ? $reason : null), ['status' => $s['status']], ['status' => $status], $by);
    }

    // ================================================================ invoices

    /** The invoice for the billing period that starts on $from (plan, packs, monthly add-ons). */
    private function issuePeriodInvoice(array $s, string $from, ?int $by): int
    {
        if ($s['next_plan_id']) { // a downgrade starts with the next period
            $this->db->execute('UPDATE users_has_payment_plans SET payment_plans_payment_plan_id = next_plan_id, next_plan_id = NULL WHERE users_has_payment_plan_id = :id', ['id' => $s['users_has_payment_plan_id']]);
            $this->log((int) $s['users_user_id'], 'plan', 'Plan changed to ' . strtoupper((string) $this->catalog->planById((int) $s['next_plan_id'])['payment_plan']) . ' for the new period', null, null, null, null);
            $s['payment_plans_payment_plan_id'] = $s['next_plan_id'];
        }
        $plan = $this->catalog->planById((int) $s['payment_plans_payment_plan_id']);
        $lines = Lifecycle::periodLines($plan, $s['billing_cycle'], $s['custom_price'] !== null ? (float) $s['custom_price'] : null, (int) $s['extra_packs'], $this->tutorAddons((int) $s['users_user_id'])[(int) $s['users_user_id']] ?? []);
        return $this->createInvoice($s, 'period', $from, Lifecycle::periodEnd($from, $s['billing_cycle']), $from, $lines, $by);
    }

    private function createInvoice(array $s, string $kind, ?string $from, ?string $to, string $due, array $lines, ?int $by): int
    {
        $rules = $this->settings->rules();
        $sum = Lifecycle::total($lines, (bool) $rules['tax_on'], (float) $rules['tax_rate']);
        $prefix = $rules['invoice_prefix'] . '-' . substr($this->today, 0, 4) . '-';
        $last = (string) ($this->db->value('SELECT invoice_number FROM platform_invoices WHERE invoice_number LIKE :p ORDER BY invoice_number DESC LIMIT 1 FOR UPDATE', ['p' => $prefix . '%']) ?? '');
        $number = $prefix . str_pad((string) ((int) substr($last, strlen($prefix)) + 1), 5, '0', STR_PAD_LEFT);
        $plan = $this->catalog->planById((int) $s['payment_plans_payment_plan_id']);
        $id = $this->db->insert(
            'INSERT INTO platform_invoices (invoice_number, users_user_id, users_has_payment_plan_id, kind, plan_name, billing_cycle, period_from, period_to, issued_on, due_on, subtotal, tax, total, status, created_by, created_at)
             VALUES (:n, :u, :s, :k, :pl, :c, :f, :t, :i, :d, :sub, :tax, :tot, :st, :by, NOW())',
            ['n' => $number, 'u' => $s['users_user_id'], 's' => $s['users_has_payment_plan_id'], 'k' => $kind, 'pl' => strtoupper((string) $plan['payment_plan']),
                'c' => $s['billing_cycle'], 'f' => $from, 't' => $to, 'i' => $this->today, 'd' => $due, 'sub' => $sum['subtotal'], 'tax' => $sum['tax'], 'tot' => $sum['total'],
                'st' => $sum['total'] > 0 ? 'open' : 'paid', 'by' => $by],
        );
        foreach ($lines as $i => $l) {
            $this->db->execute('INSERT INTO platform_invoice_lines (platform_invoice_id, description, amount, sort_order) VALUES (:i, :d, :a, :o)', ['i' => $id, 'd' => $l['text'], 'a' => $l['amount'], 'o' => $i]);
        }
        $this->log((int) $s['users_user_id'], 'invoice', sprintf('Invoice %s made for LKR %s, due %s', $number, number_format($sum['total']), $due), null, null, ['invoice' => $number, 'total' => $sum['total']], $by);
        return $id;
    }

    /** Voids open invoices that nothing was paid on and no slip is waiting for (plan changed, trial extended, cancelled). */
    private function voidUnpaid(array $s, string $reason, ?int $by, ?string $kind = 'period'): void
    {
        foreach ($this->db->all(
            "SELECT i.platform_invoice_id FROM platform_invoices i WHERE i.users_has_payment_plan_id = :s AND i.status = 'open' AND i.paid = 0" . ($kind ? ' AND i.kind = :k' : '') . '
               AND NOT EXISTS (SELECT 1 FROM users_has_payment_plans_payments p WHERE p.platform_invoice_id = i.platform_invoice_id AND p.payment_status_payment_status_id = 2)',
            $kind ? ['s' => $s['users_has_payment_plan_id'], 'k' => $kind] : ['s' => $s['users_has_payment_plan_id']],
        ) as $r) {
            $this->db->execute("UPDATE platform_invoices SET status = 'void', void_reason = :r WHERE platform_invoice_id = :i", ['r' => $reason, 'i' => $r['platform_invoice_id']]);
        }
    }

    public function voidInvoice(int $invoiceId, string $reason, int $by): void
    {
        $reason = trim($reason);
        if ($reason === '' || mb_strlen($reason) > 200) {
            throw new HttpException(422, 'validation_failed', 'Give a reason.', ['reason' => 'Give a reason, up to 200 characters. It is kept with the invoice.']);
        }
        $this->db->transaction(function () use ($invoiceId, $reason, $by) {
            $i = $this->db->one('SELECT * FROM platform_invoices WHERE platform_invoice_id = :i FOR UPDATE', ['i' => $invoiceId]) ?? throw HttpException::notFound('Invoice not found.');
            if ($i['status'] !== 'open' || (float) $i['paid'] > 0) {
                throw new HttpException(409, 'conflict', 'Only an open invoice with nothing paid can be void. Paid money is never removed from an invoice.');
            }
            if ($this->db->value('SELECT 1 FROM users_has_payment_plans_payments WHERE platform_invoice_id = :i AND payment_status_payment_status_id = 2', ['i' => $invoiceId])) {
                throw new HttpException(409, 'conflict', 'A slip for this invoice is waiting. Approve or reject it first.');
            }
            $this->db->execute("UPDATE platform_invoices SET status = 'void', void_reason = :r WHERE platform_invoice_id = :i", ['r' => $reason, 'i' => $invoiceId]);
            $this->log((int) $i['users_user_id'], 'void', 'Invoice ' . $i['invoice_number'] . ' void', $reason, ['status' => 'open'], ['status' => 'void'], $by);
        });
        $this->tickTutor((int) $this->db->value('SELECT users_user_id FROM platform_invoices WHERE platform_invoice_id = :i', ['i' => $invoiceId]));
        $this->settle((int) $this->db->value('SELECT users_user_id FROM platform_invoices WHERE platform_invoice_id = :i', ['i' => $invoiceId]), $by);
    }

    // ================================================================ payments

    /** The tutor uploads the bank slip for an open invoice. */
    public function uploadSlip(int $tutorId, array $in, ?array $file): array
    {
        $e = [];
        $date = $this->dateOrError($in['date'] ?? '', 'date', $e, true);
        $ref = trim((string) ($in['reference'] ?? ''));
        if (mb_strlen($ref) > 60) {
            $e['reference'] = 'Up to 60 characters.';
        }
        $slip = $this->checkSlip($file, $e);
        $invoice = $this->db->one("SELECT * FROM platform_invoices WHERE platform_invoice_id = :i AND users_user_id = :t", ['i' => (int) ($in['invoice'] ?? 0), 't' => $tutorId]);
        if ($invoice === null || !in_array($invoice['status'], ['open', 'part'], true)) {
            throw new HttpException(409, 'conflict', 'This invoice is not open any more. Reload the page.');
        }
        if ($e) {
            throw new HttpException(422, 'validation_failed', 'Please check the highlighted fields.', $e);
        }
        if ($this->db->value('SELECT 1 FROM users_has_payment_plans_payments WHERE platform_invoice_id = :i AND payment_status_payment_status_id = 2', ['i' => $invoice['platform_invoice_id']])) {
            throw new HttpException(409, 'conflict', 'A slip for this invoice is already waiting to be checked.');
        }
        $id = $this->db->transaction(function () use ($invoice, $date, $ref, $slip) {
            $id = $this->insertPayment($invoice, (float) $invoice['total'] - (float) $invoice['paid'], $date, self::METHODS['Bank transfer'], $ref, self::WAITING, null);
            if (!is_dir($this->slipDir)) {
                mkdir($this->slipDir, 0775, true);
            }
            $name = 'EN_' . bin2hex(random_bytes(8)) . '.' . $slip['ext'];
            if (!move_uploaded_file($slip['tmp'], $this->slipDir . '/' . $name) && !rename($slip['tmp'], $this->slipDir . '/' . $name)) {
                throw new HttpException(500, 'upload_failed', 'The slip could not be saved. Please try again.');
            }
            $this->db->execute(
                'INSERT INTO users_payments_slips (users_has_payment_plans_payment_id, slip_hash, refference, slip) VALUES (:p, :h, :r, :s)',
                ['p' => $id, 'h' => $slip['hash'], 'r' => strtoupper(substr($slip['hash'], 0, 10)), 's' => $name],
            );
            $this->log((int) $invoice['users_user_id'], 'slip', sprintf('Bank slip uploaded for %s (LKR %s)', $invoice['invoice_number'], number_format((float) $invoice['total'] - (float) $invoice['paid'])), null, null, null, (int) $invoice['users_user_id']);
            return $id;
        });
        return $this->payments(null, $id)[0];
    }

    /** The admin records money received (cash, cheque, a transfer seen in the bank). */
    public function recordPayment(int $invoiceId, array $in, int $by): array
    {
        $e = [];
        $date = $this->dateOrError($in['date'] ?? '', 'date', $e, true);
        $amount = is_numeric($in['amount'] ?? null) ? round((float) $in['amount'], 2) : 0.0;
        $method = self::METHODS[$in['method'] ?? ''] ?? null;
        $ref = trim((string) ($in['reference'] ?? ''));
        $invoice = $this->db->one('SELECT * FROM platform_invoices WHERE platform_invoice_id = :i', ['i' => $invoiceId]) ?? throw HttpException::notFound('Invoice not found.');
        if (!in_array($invoice['status'], ['open', 'part'], true)) {
            throw new HttpException(409, 'conflict', 'This invoice is not open.');
        }
        $left = (float) $invoice['total'] - (float) $invoice['paid'];
        if ($amount <= 0 || $amount > $left + 0.001) {
            $e['amount'] = sprintf('Enter the amount received, up to LKR %s.', number_format($left, 2));
        }
        if ($method === null) {
            $e['method'] = 'Choose how it was paid.';
        }
        if ($method === self::METHODS['Cheque'] && $ref === '') {
            $e['reference'] = 'Enter the cheque number.';
        }
        if (mb_strlen($ref) > 60) {
            $e['reference'] = 'Up to 60 characters.';
        }
        if ($e) {
            throw new HttpException(422, 'validation_failed', 'Please check the highlighted fields.', $e);
        }
        $id = $this->db->transaction(function () use ($invoice, $amount, $date, $method, $ref, $by) {
            $id = $this->insertPayment($invoice, $amount, $date, $method, $ref, self::ACCEPTED, $by);
            $this->db->execute('UPDATE users_has_payment_plans_payments SET checked_by = :b, checked_at = NOW() WHERE users_has_payment_plans_payment_id = :id', ['b' => $by, 'id' => $id]);
            $this->log((int) $invoice['users_user_id'], 'payment', sprintf('Payment of LKR %s recorded for %s (%s)', number_format($amount), $invoice['invoice_number'], array_search($method, self::METHODS, true)), null, null, ['amount' => $amount], $by);
            $this->applyToInvoice((int) $invoice['platform_invoice_id'], $amount, $by);
            return $id;
        });
        $this->settle((int) $invoice['users_user_id'], $by);
        return $this->payments(null, $id)[0];
    }

    public function approve(int $paymentId, int $by): array
    {
        $tutor = $this->db->transaction(function () use ($paymentId, $by) {
            $p = $this->lockWaiting($paymentId);
            $this->db->execute(
                'UPDATE users_has_payment_plans_payments SET payment_status_payment_status_id = 3, checked_by = :b, checked_at = NOW() WHERE users_has_payment_plans_payment_id = :id',
                ['b' => $by, 'id' => $paymentId],
            );
            if (!$p['platform_invoice_id']) { // a slip from the old Billing page: it paid for its own from / to
                $s = $this->db->one('SELECT * FROM users_has_payment_plans WHERE users_has_payment_plan_id = :s FOR UPDATE', ['s' => $p['users_has_payment_plans_users_has_payment_plan_id']]);
                $to = substr((string) $p['to'], 0, 10);
                if ($s['period_end'] === null || $to > $s['period_end']) {
                    $this->db->execute('UPDATE users_has_payment_plans SET period_start = :f, period_end = :t WHERE users_has_payment_plan_id = :s', ['f' => substr((string) $p['from'], 0, 10), 't' => $to, 's' => $s['users_has_payment_plan_id']]);
                }
                $this->log((int) $s['users_user_id'], 'approve', sprintf('Payment %s approved (LKR %s, old Billing page): the plan runs until %s', $p['refference'], number_format((float) $p['paid_amount']), Lifecycle::addDays($to, -1)), null, ['status' => 'waiting'], ['status' => 'approved'], $by);
                return (int) $s['users_user_id'];
            }
            $inv = $this->db->one('SELECT invoice_number, users_user_id FROM platform_invoices WHERE platform_invoice_id = :i', ['i' => $p['platform_invoice_id']]);
            $this->log((int) $inv['users_user_id'], 'approve', sprintf('Payment %s approved (LKR %s for %s)', $p['refference'], number_format((float) $p['paid_amount']), $inv['invoice_number']), null, ['status' => 'waiting'], ['status' => 'approved'], $by);
            $this->applyToInvoice((int) $p['platform_invoice_id'], (float) $p['paid_amount'], $by);
            return (int) $inv['users_user_id'];
        });
        $this->settle($tutor, $by);
        return $this->payments(null, $paymentId)[0];
    }

    public function reject(int $paymentId, string $reason, string $note, int $by): array
    {
        $reason = trim($reason);
        $note = trim($note);
        if (!in_array($reason, self::REJECT_REASONS, true)) {
            throw new HttpException(422, 'validation_failed', 'Choose a reason.', ['reason' => 'Choose why, so the tutor knows what to fix.']);
        }
        if ($reason === 'Other' && $note === '') {
            throw new HttpException(422, 'validation_failed', 'Add a note.', ['note' => 'Say what is wrong with the slip.']);
        }
        $text = mb_substr($reason . ($note !== '' ? '. ' . $note : ''), 0, 250);
        $this->db->transaction(function () use ($paymentId, $text, $by) {
            $p = $this->lockWaiting($paymentId);
            $this->db->execute(
                'UPDATE users_has_payment_plans_payments SET payment_status_payment_status_id = 4, checked_by = :b, checked_at = NOW(), reject_reason = :r WHERE users_has_payment_plans_payment_id = :id',
                ['b' => $by, 'r' => $text, 'id' => $paymentId],
            );
            $tutor = (int) $this->db->value('SELECT users_user_id FROM users_has_payment_plans WHERE users_has_payment_plan_id = :s', ['s' => $p['users_has_payment_plans_users_has_payment_plan_id']]);
            $this->log($tutor, 'reject', sprintf('Payment %s rejected', $p['refference']), $text, ['status' => 'waiting'], ['status' => 'rejected'], $by);
        });
        return $this->payments(null, $paymentId)[0];
    }

    /** @return array{path:string, name:string, type:string} the newest slip of a payment */
    public function slipFile(int $paymentId, ?int $tutorId = null): array
    {
        $r = $this->db->one(
            'SELECT sl.slip, s.users_user_id FROM users_payments_slips sl
               JOIN users_has_payment_plans_payments p ON p.users_has_payment_plans_payment_id = sl.users_has_payment_plans_payment_id
               JOIN users_has_payment_plans s ON s.users_has_payment_plan_id = p.users_has_payment_plans_users_has_payment_plan_id
              WHERE sl.users_has_payment_plans_payment_id = :p ORDER BY sl.users_payments_slip_id DESC LIMIT 1',
            ['p' => $paymentId],
        );
        if ($r === null || ($tutorId !== null && (int) $r['users_user_id'] !== $tutorId)) {
            throw HttpException::notFound('No slip for this payment.');
        }
        $name = basename((string) $r['slip']);
        $path = $this->slipDir . '/' . $name;
        if (!is_file($path)) {
            throw HttpException::notFound('The slip file is missing.');
        }
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        return ['path' => $path, 'name' => $name, 'type' => ['pdf' => 'application/pdf', 'png' => 'image/png'][$ext] ?? 'image/jpeg'];
    }

    private function lockWaiting(int $paymentId): array
    {
        $p = $this->db->one('SELECT * FROM users_has_payment_plans_payments WHERE users_has_payment_plans_payment_id = :id FOR UPDATE', ['id' => $paymentId]) ?? throw HttpException::notFound('Payment not found.');
        if ((int) $p['payment_status_payment_status_id'] !== self::WAITING) {
            throw new HttpException(409, 'conflict', 'This payment was already checked. Reload the page.');
        }
        return $p;
    }

    private function insertPayment(array $invoice, float $amount, string $date, int $method, string $ref, int $status, ?int $by): int
    {
        $from = $invoice['period_from'] ?? $this->today;
        $to = $invoice['period_to'] ?? $from;
        $code = '';
        for ($i = 0; $i < 8; $i++) {
            $code .= '0123456789ABCDEFGHJKLMNPQRSTUVWXYZ'[random_int(0, 33)];
        }
        return $this->db->insert(
            'INSERT INTO users_has_payment_plans_payments (users_has_payment_plans_users_has_payment_plan_id, refference, `from`, `to`, date_schedule, date_paid, paid_amount, discount_amount,
                    payment_method_payment_method_id, payment_status_payment_status_id, platform_invoice_id, bank_reference, recorded_by)
             VALUES (:s, :r, :f, :t, NOW(), :d, :a, 0, :m, :st, :i, :ref, :by)',
            ['s' => $invoice['users_has_payment_plan_id'], 'r' => $code, 'f' => $from, 't' => $to, 'd' => $date . ' ' . date('H:i:s'), 'a' => $amount,
                'm' => $method, 'st' => $status, 'i' => $invoice['platform_invoice_id'], 'ref' => $ref === '' ? null : $ref, 'by' => $by],
        );
    }

    /** Adds money to an invoice; a paid period invoice starts the new period. */
    private function applyToInvoice(int $invoiceId, float $amount, ?int $by): void
    {
        $i = $this->db->one('SELECT * FROM platform_invoices WHERE platform_invoice_id = :i FOR UPDATE', ['i' => $invoiceId]);
        $paid = round((float) $i['paid'] + $amount, 2);
        $full = $paid >= (float) $i['total'] - 0.001;
        $this->db->execute(
            'UPDATE platform_invoices SET paid = :p, status = :s, paid_at = IF(:full = 1, NOW(), paid_at) WHERE platform_invoice_id = :i',
            ['p' => $paid, 's' => $full ? 'paid' : 'part', 'full' => $full ? 1 : 0, 'i' => $invoiceId],
        );
        if ($full && $i['kind'] === 'period' && $i['period_from']) {
            $s = $this->db->one('SELECT * FROM users_has_payment_plans WHERE users_has_payment_plan_id = :s FOR UPDATE', ['s' => $i['users_has_payment_plan_id']]);
            $wasTrial = ($s['status'] ?: 'trial') === 'trial' || $s['period_start'] === null;
            if ($s['period_end'] === null || $i['period_to'] > $s['period_end']) { // period_start..period_end = the period paid last
                $this->db->execute('UPDATE users_has_payment_plans SET period_start = :f, period_end = :t WHERE users_has_payment_plan_id = :s', ['f' => $i['period_from'], 't' => $i['period_to'], 's' => $s['users_has_payment_plan_id']]);
            }
            $this->log((int) $s['users_user_id'], $wasTrial ? 'trial_converted' : 'renewed', sprintf('Invoice %s paid: the plan runs until %s', $i['invoice_number'], Lifecycle::addDays($i['period_to'], -1)), null, ['period_end' => $s['period_end']], ['period_end' => $i['period_to']], $by);
        }
    }

    /** After money came in or an invoice was void: nothing left to pay = Active again (also after a suspension). */
    private function settle(int $tutorId, ?int $by): void
    {
        $this->db->transaction(function () use ($tutorId, $by) {
            $s = $this->db->one('SELECT * FROM users_has_payment_plans WHERE users_user_id = :t ORDER BY users_has_payment_plan_id DESC LIMIT 1 FOR UPDATE', ['t' => $tutorId]);
            $status = $s['status'] ?: 'trial';
            $open = $this->db->value("SELECT COUNT(*) FROM platform_invoices WHERE users_has_payment_plan_id = :s AND status IN ('open','part')", ['s' => $s['users_has_payment_plan_id']]);
            $paidPeriod = $s['period_end'] !== null && $s['period_end'] > $this->today;
            if ((int) $open === 0 && $paidPeriod && in_array($status, ['trial', 'due', 'overdue', 'suspended'], true)) {
                $this->db->execute('UPDATE users_has_payment_plans SET grace_until = NULL WHERE users_has_payment_plan_id = :s', ['s' => $s['users_has_payment_plan_id']]);
                $this->setStatus($s, 'active', null, $by, $status === 'suspended' ? 'Reactivated: everything is paid' : 'Status changed to Active: everything is paid');
            }
        });
        $this->tickTutor($tutorId);
    }

    // ================================================================ adding a tutor

    public function addTutor(array $in, int $by): int
    {
        $rules = $this->settings->rules();
        $e = [];
        $str = static function (string $k, int $max, bool $req, string $msg) use ($in, &$e): string {
            $v = trim((string) ($in[$k] ?? ''));
            if ($req && $v === '') {
                $e[$k] = $msg;
            } elseif (mb_strlen($v) > $max) {
                $e[$k] = "Up to $max characters.";
            }
            return $v;
        };
        $title = (int) ($in['title'] ?? 0);
        if (!$this->db->value('SELECT 1 FROM title WHERE title_id = :t', ['t' => $title])) {
            $e['title'] = 'Choose a title.';
        }
        $first = $str('first_name', 45, true, 'Enter the first name.');
        $last = $str('last_name', 45, true, 'Enter the last name.');
        $email = strtolower($str('email', 100, true, 'Enter the e-mail address.'));
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $e['email'] = 'Enter a valid e-mail address, for example ruwan@gmail.com.';
        } elseif ($email !== '' && $this->db->value('SELECT 1 FROM users WHERE LOWER(user_email) = :e', ['e' => $email])) {
            $e['email'] = 'Someone already signs in with this e-mail address.';
        }
        $mobile = self::phone($str('mobile', 15, true, 'Enter the mobile number.'));
        if (($in['mobile'] ?? '') !== '' && $mobile === null) {
            $e['mobile'] = 'Enter a Sri Lankan mobile number, 10 digits, for example 0771234567.';
        } elseif ($mobile !== null && $this->db->value('SELECT 1 FROM users WHERE user_mobile IN (:a, :b)', ['a' => $mobile, 'b' => '+94' . substr($mobile, 1)])) {
            $e['mobile'] = 'Another account already uses this mobile number.';
        }
        $waRaw = $str('whatsapp', 15, false, '');
        $wa = $waRaw === '' ? $mobile : self::phone($waRaw);
        if ($waRaw !== '' && $wa === null) {
            $e['whatsapp'] = 'Enter a Sri Lankan mobile number, 10 digits, for example 0771234567.';
        } elseif ($wa !== null && $this->db->value('SELECT 1 FROM users WHERE user_whatsapp IN (:a, :b)', ['a' => $wa, 'b' => '+94' . substr($wa, 1)])) {
            $e[$waRaw === '' ? 'mobile' : 'whatsapp'] = 'Another account already uses this number for WhatsApp.';
        }
        $brand = $str('brand', 100, false, '');
        $subject = $str('subject', 60, false, '');
        $note = $str('note', 200, false, '');
        $plan = $this->catalog->planByKey((string) ($in['plan'] ?? '')) ?? null;
        if ($plan === null) {
            $e['plan'] = 'Choose a plan.';
        }
        $cycle = ($in['cycle'] ?? '') === 'yearly' ? 'yearly' : 'monthly';
        $packs = (int) ($in['packs'] ?? 0);
        if ($packs < 0 || $packs > 20) {
            $e['packs'] = 'Enter 0 to 20 packs.';
        } elseif ($packs > 0 && $plan !== null && $plan['extra_pack_price'] === null) {
            $packs = 0;
        }
        $custom = trim((string) ($in['custom_price'] ?? ''));
        $customReason = $str('custom_price_reason', 150, false, '');
        if ($custom !== '') {
            if (!is_numeric($custom) || (float) $custom <= 0) {
                $e['custom_price'] = 'Enter the monthly price in LKR, numbers only, for example 4500.';
            }
            if ($customReason === '') {
                $e['custom_price_reason'] = 'Say why this tutor gets a different price.';
            }
        }
        $offered = array_column(array_filter($this->catalog->addons(), static fn ($a) => $a['on']), 'id', 'key');
        $addonIds = [];
        foreach ((array) ($in['addons'] ?? []) as $k) {
            if (isset($offered[$k])) {
                $addonIds[] = $offered[$k];
            }
        }
        $start = (string) ($in['start'] ?? 'trial');
        $trialDays = 0;
        $startDate = $this->today;
        $paidNow = 'no';
        if ($start === 'trial') {
            $trialDays = (int) ($in['trial_days'] ?? $rules['trial_days']);
            if ($trialDays < 1 || $trialDays > 60) {
                $e['trial_days'] = 'Enter 1 to 60 days.';
            }
        } elseif ($start === 'paid') {
            $startDate = $this->dateOrError($in['start_date'] ?? '', 'start_date', $e, false) ?? $this->today;
            if ($startDate < Lifecycle::addDays($this->today, -31)) {
                $e['start_date'] = 'Choose a day from the last month or later.';
            }
            $paidNow = in_array($in['paid_now'] ?? 'no', ['no', 'bank', 'cash'], true) ? $in['paid_now'] : 'no';
        } elseif ($start === 'comp') {
            $until = $this->dateOrError($in['comp_until'] ?? '', 'comp_until', $e, false);
            if ($until !== null && $until <= $this->today) {
                $e['comp_until'] = 'Choose a day after today.';
            }
            if (trim((string) ($in['comp_reason'] ?? '')) === '') {
                $e['comp_reason'] = 'Say why the access is free.';
            }
        } else {
            $e['start'] = 'Choose how the tutor starts.';
        }
        if ($e) {
            throw new HttpException(422, 'validation_failed', 'Please check the highlighted fields.', $e);
        }

        $id = $this->db->transaction(function () use ($in, $by, $title, $first, $last, $email, $mobile, $wa, $brand, $subject, $note, $plan, $cycle, $packs, $custom, $customReason, $addonIds, $start, $trialDays, $startDate, $rules) {
            $uid = $this->db->insert(
                'INSERT INTO users (title_title_id, user_fname, user_lname, user_email, user_mobile, user_whatsapp, user_reg_date, status_status_id, user_roles_user_role_id, added_by)
                 VALUES (:t, :f, :l, :e, :m, :w, NOW(), 1, :role, :by)',
                ['t' => $title, 'f' => $first, 'l' => $last, 'e' => $email, 'm' => $mobile, 'w' => $wa, 'role' => self::TUTOR_ROLE, 'by' => $by],
            );
            $status = ['trial' => 'trial', 'paid' => 'due', 'comp' => 'complimentary'][$start];
            $sid = $this->db->insert(
                'INSERT INTO users_has_payment_plans (users_user_id, payment_plans_payment_plan_id, added_by, date_added, status, billing_cycle, trial_ends_at, period_start, period_end,
                        custom_price, custom_price_reason, extra_packs, status_reason, institute_name, main_subject, admin_note, status_changed_at)
                 VALUES (:u, :p, :by, NOW(), :st, :c, :te, :ps, :pe, :cp, :cpr, :x, :sr, :b, :sub, :n, NOW())',
                ['u' => $uid, 'p' => $plan['payment_plan_id'], 'by' => $by, 'st' => $status, 'c' => $cycle,
                    'te' => $start === 'trial' ? Lifecycle::addDays($this->today, $trialDays) : ($start === 'paid' ? $startDate : null),
                    'ps' => $start === 'comp' ? $this->today : null, 'pe' => $start === 'comp' ? Lifecycle::addDays((string) $in['comp_until'], 1) : null,
                    'cp' => $custom === '' ? null : round((float) $custom, 2), 'cpr' => $custom === '' ? null : $customReason, 'x' => $packs,
                    'sr' => $start === 'comp' ? mb_substr(trim((string) $in['comp_reason']), 0, 200) : null,
                    'b' => $brand ?: null, 'sub' => $subject ?: null, 'n' => $note ?: null],
            );
            foreach ($addonIds as $a) {
                $this->db->execute('INSERT INTO tutor_addons (tutor_id, addons_addon_id, starts_at) VALUES (:t, :a, NOW())', ['t' => $uid, 'a' => $a]);
            }
            $what = match ($start) {
                'trial' => sprintf('Added with a %d-day free trial on %s (then %s, %s)', $trialDays, strtoupper((string) $rules['trial_plan']), strtoupper((string) $plan['payment_plan']), $cycle),
                'paid' => sprintf('Added on %s, %s, starting %s', strtoupper((string) $plan['payment_plan']), $cycle, $startDate),
                default => sprintf('Added with free %s access until %s', strtoupper((string) $plan['payment_plan']), $in['comp_until']),
            };
            $this->log($uid, $start === 'trial' ? 'added_trial' : 'added', $what, $start === 'comp' ? trim((string) $in['comp_reason']) : ($custom !== '' ? 'Custom price: ' . $customReason : null), null, null, $by);
            if ($start === 'paid') {
                $s = $this->db->one('SELECT * FROM users_has_payment_plans WHERE users_has_payment_plan_id = :s', ['s' => $sid]);
                $this->issuePeriodInvoice($s, $startDate, $by);
            }
            return $uid;
        });
        if ($start === 'paid' && $paidNow !== 'no') {
            $inv = $this->db->one("SELECT platform_invoice_id, total FROM platform_invoices WHERE users_user_id = :u AND status = 'open' ORDER BY platform_invoice_id LIMIT 1", ['u' => $id]);
            if ($inv) {
                $this->recordPayment((int) $inv['platform_invoice_id'], ['amount' => $inv['total'], 'date' => $this->today, 'method' => $paidNow === 'cash' ? 'Cash' : 'Bank transfer', 'reference' => (string) ($in['reference'] ?? '')], $by);
            }
        }
        $this->tickTutor($id);
        return $id;
    }

    // ================================================================ plan actions (admin; plan change also by the tutor)

    public function action(int $tutorId, string $action, array $in, int $by, bool $byTutor = false): string
    {
        $reason = trim((string) ($in['reason'] ?? ''));
        if (in_array($action, ['extend', 'suspend', 'reactivate', 'cancel', 'price'], true) && ($reason === '' || mb_strlen($reason) > 200)) {
            throw new HttpException(422, 'validation_failed', 'Give a reason.', ['reason' => 'Say why, up to 200 characters. It is kept in the tutor\'s history.']);
        }
        $msg = $this->db->transaction(function () use ($tutorId, $action, $in, $by, $reason, $byTutor) {
            $s = $this->db->one('SELECT * FROM users_has_payment_plans WHERE users_user_id = :t ORDER BY users_has_payment_plan_id DESC LIMIT 1 FOR UPDATE', ['t' => $tutorId]) ?? throw HttpException::notFound('Tutor not found.');
            $s['status'] = $s['status'] ?: 'trial';
            $id = ['id' => $s['users_has_payment_plan_id']];
            switch ($action) {
                case 'extend':
                    $days = (int) ($in['days'] ?? 0);
                    if ($s['status'] !== 'trial' && !($s['period_start'] === null && $s['status'] === 'overdue')) {
                        throw new HttpException(409, 'conflict', 'Only a free trial can be extended.');
                    }
                    if ($days < 1 || $days > 30) {
                        throw new HttpException(422, 'validation_failed', 'Enter 1 to 30 days.', ['days' => 'Enter 1 to 30 days.']);
                    }
                    $base = max((string) $s['trial_ends_at'], $this->today);
                    $this->voidUnpaid($s, 'Trial extended', $by);
                    $this->db->execute("UPDATE users_has_payment_plans SET trial_ends_at = :d, trial_extended = 1, status = 'trial', status_reason = NULL WHERE users_has_payment_plan_id = :id", ['d' => Lifecycle::addDays($base, $days)] + $id);
                    $this->log($tutorId, 'extend', sprintf('Trial extended by %d days, now ends %s', $days, Lifecycle::addDays($base, $days)), $reason, ['trial_ends' => $s['trial_ends_at']], ['trial_ends' => Lifecycle::addDays($base, $days)], $by);
                    return 'Trial extended.';
                case 'plan':
                    return $this->changePlan($s, (string) ($in['plan'] ?? ''), $byTutor ? $s['billing_cycle'] : (($in['cycle'] ?? '') === 'yearly' ? 'yearly' : 'monthly'), $by, $byTutor);
                case 'packs':
                    $packs = (int) ($in['packs'] ?? -1);
                    if ($packs < 0 || $packs > 20) {
                        throw new HttpException(422, 'validation_failed', 'Enter 0 to 20 packs.', ['packs' => 'Enter 0 to 20 packs.']);
                    }
                    $this->db->execute('UPDATE users_has_payment_plans SET extra_packs = :x WHERE users_has_payment_plan_id = :id', ['x' => $packs] + $id);
                    $this->log($tutorId, 'packs', sprintf('Extra students set to %d × 50 (from the next invoice)', $packs), null, ['packs' => (int) $s['extra_packs']], ['packs' => $packs], $by);
                    return 'Saved.';
                case 'price':
                    $p = trim((string) ($in['price'] ?? ''));
                    if ($p !== '' && (!is_numeric($p) || (float) $p <= 0)) {
                        throw new HttpException(422, 'validation_failed', 'Enter a price.', ['price' => 'Enter the monthly price in LKR, numbers only, or leave it empty.']);
                    }
                    $this->db->execute('UPDATE users_has_payment_plans SET custom_price = :p, custom_price_reason = :r WHERE users_has_payment_plan_id = :id', ['p' => $p === '' ? null : round((float) $p, 2), 'r' => $p === '' ? null : mb_substr($reason, 0, 150)] + $id);
                    $this->log($tutorId, 'price', $p === '' ? 'Back to the plan price (from the next invoice)' : sprintf('Custom price LKR %s a month (from the next invoice)', number_format((float) $p)), $reason, ['custom_price' => $s['custom_price']], ['custom_price' => $p === '' ? null : (float) $p], $by);
                    return 'Saved.';
                case 'suspend':
                    if (in_array($s['status'], ['suspended', 'cancelled'], true)) {
                        throw new HttpException(409, 'conflict', 'This tutor is already ' . $s['status'] . '.');
                    }
                    $this->setStatus($s, 'suspended', 'Admin: ' . $reason, $by, 'Suspended by ENLIXO', $reason);
                    return 'Suspended.';
                case 'reactivate':
                    if (!in_array($s['status'], ['suspended', 'cancelled'], true)) {
                        throw new HttpException(409, 'conflict', 'This tutor is not suspended.');
                    }
                    $grace = Lifecycle::addDays($this->today, max(1, (int) $this->settings->rules()['grace_days']));
                    $back = $s['period_end'] !== null && $s['period_end'] > $this->today ? 'active' : ($s['period_start'] === null && $s['trial_ends_at'] > $this->today ? 'trial' : 'overdue');
                    $this->db->execute('UPDATE users_has_payment_plans SET grace_until = :g, cancel_at_period_end = 0 WHERE users_has_payment_plan_id = :id', ['g' => $grace] + $id);
                    $this->setStatus($s, $back, null, $by, 'Reactivated by ENLIXO', $reason);
                    return 'Reactivated.';
                case 'cancel':
                    if ($s['status'] === 'cancelled') {
                        throw new HttpException(409, 'conflict', 'The subscription is already cancelled.');
                    }
                    $now = ($in['when'] ?? 'end') === 'now' || !in_array($s['status'], ['active', 'due', 'complimentary'], true);
                    $this->voidUnpaid($s, 'Subscription cancelled', $by, null);
                    if ($now) {
                        $this->setStatus($s, 'cancelled', 'Admin: ' . $reason, $by, 'Subscription cancelled now', $reason);
                    } else {
                        $this->db->execute('UPDATE users_has_payment_plans SET cancel_at_period_end = 1 WHERE users_has_payment_plan_id = :id', $id);
                        $this->log($tutorId, 'cancel', 'Subscription ends at the end of the period (' . Lifecycle::addDays((string) $s['period_end'], -1) . ')', $reason, null, null, $by);
                    }
                    return 'Cancelled.';
                case 'remind':
                    $open = $this->db->one("SELECT invoice_number, total - paid AS left_to_pay, due_on FROM platform_invoices WHERE users_has_payment_plan_id = :id AND status IN ('open','part') ORDER BY due_on LIMIT 1", $id);
                    if ($open === null) {
                        throw new HttpException(409, 'conflict', 'Nothing is open for this tutor.');
                    }
                    $this->log($tutorId, 'remind', sprintf('Payment reminder for %s (LKR %s, due %s)', $open['invoice_number'], number_format((float) $open['left_to_pay']), $open['due_on']), null, null, null, $by);
                    return 'remind:' . json_encode($open);
            }
            throw HttpException::notFound('Unknown action.');
        });
        $this->tickTutor($tutorId);
        return $msg;
    }

    private function changePlan(array $s, string $key, string $cycle, int $by, bool $byTutor): string
    {
        $new = $this->catalog->planByKey($key) ?? throw new HttpException(422, 'validation_failed', 'Choose a plan.', ['plan' => 'Choose a plan.']);
        $old = $this->catalog->planById((int) $s['payment_plans_payment_plan_id']);
        $tutorId = (int) $s['users_user_id'];
        $id = ['id' => $s['users_has_payment_plan_id']];
        if ((int) $new['payment_plan_id'] === (int) $old['payment_plan_id'] && $cycle === $s['billing_cycle']) {
            throw new HttpException(422, 'validation_failed', 'Nothing changed.', ['plan' => 'Choose another plan or billing.']);
        }
        $students = (int) ($this->studentCounts($tutorId)[$tutorId] ?? 0);
        $paidPeriod = $s['period_start'] !== null && $s['period_end'] !== null && $s['period_end'] > $this->today && in_array($s['status'], ['active', 'due'], true);
        $label = strtoupper((string) $new['payment_plan']);
        if (!$paidPeriod) { // trial, complimentary, or nothing paid yet: switch now, the open invoice is made again with the new price
            $this->voidUnpaid($s, 'Plan changed', $by);
            $this->db->execute('UPDATE users_has_payment_plans SET payment_plans_payment_plan_id = :p, billing_cycle = :c, next_plan_id = NULL WHERE users_has_payment_plan_id = :id', ['p' => $new['payment_plan_id'], 'c' => $cycle] + $id);
            $this->log($tutorId, 'plan', sprintf('Plan changed to %s, %s%s', $label, $cycle, $byTutor ? ' (by the tutor)' : ''), null, ['plan' => $old['plan_key']], ['plan' => $new['plan_key']], $by);
            return "$label is your plan now.";
        }
        $limit = $new['student_limit'] === null ? PHP_INT_MAX : (int) $new['student_limit'] + (int) $s['extra_packs'] * Lifecycle::PACK_SIZE;
        if ((float) $new['per_month_amount'] < (float) $old['per_month_amount']) { // downgrade: next renewal, only if the students fit
            if ($students > $limit) {
                throw new HttpException(422, 'validation_failed', sprintf('%s allows %d students and %d are active. Make some students inactive first.', $label, $limit, $students), ['plan' => sprintf('Too many active students for %s.', $label)]);
            }
            $this->db->execute('UPDATE users_has_payment_plans SET next_plan_id = :p, billing_cycle = :c WHERE users_has_payment_plan_id = :id', ['p' => $new['payment_plan_id'], 'c' => $cycle] + $id);
            $this->log($tutorId, 'plan', sprintf('Moves to %s at the next renewal (%s)%s', $label, $s['period_end'], $byTutor ? ', asked by the tutor' : ''), null, ['plan' => $old['plan_key']], ['next_plan' => $new['plan_key']], $by);
            return "You move to $label at your next renewal.";
        }
        // upgrade: now, with an invoice for the difference for the days left in the paid period
        $custom = $s['custom_price'] !== null ? (float) $s['custom_price'] : null;
        $oldAmt = array_sum(array_column(Lifecycle::periodLines($old, $s['billing_cycle'], $custom, (int) $s['extra_packs'], []), 'amount'));
        $newAmt = array_sum(array_column(Lifecycle::periodLines($new, $s['billing_cycle'], null, (int) $s['extra_packs'], []), 'amount'));
        $diff = Lifecycle::upgradeDifference($oldAmt, $newAmt, $s['period_start'], $s['period_end'], $this->today);
        $this->db->execute('UPDATE users_has_payment_plans SET payment_plans_payment_plan_id = :p, billing_cycle = :c, custom_price = NULL, custom_price_reason = NULL, next_plan_id = NULL WHERE users_has_payment_plan_id = :id', ['p' => $new['payment_plan_id'], 'c' => $cycle] + $id);
        $s['payment_plans_payment_plan_id'] = $new['payment_plan_id'];
        if ($diff > 0) {
            $this->createInvoice($s, 'upgrade', $this->today, $s['period_end'], Lifecycle::addDays($this->today, 7), [['text' => sprintf('Upgrade %s → %s for the %d days left in this period', strtoupper((string) $old['payment_plan']), $label, Lifecycle::daysBetween($this->today, $s['period_end'])), 'amount' => $diff]], $by);
        }
        $this->log($tutorId, 'plan', sprintf('Upgraded to %s%s', $label, $byTutor ? ' by the tutor' : ''), null, ['plan' => $old['plan_key']], ['plan' => $new['plan_key'], 'difference' => $diff], $by);
        return $diff > 0 ? sprintf('%s is on. Pay LKR %s for the days left this period within 7 days.', $label, number_format($diff)) : "$label is on.";
    }

    // ================================================================ settings

    public function saveRules(array $in, int $by): array
    {
        [$old, $new] = $this->settings->saveRules($in, $this->catalog->planKeys(), $by);
        $this->log(null, 'rules', 'Trial & Billing Rules changed', null, $old, $new, $by);
        return $this->publicRules();
    }

    public function saveBank(?int $id, array $in, int $by): array
    {
        $banks = $this->settings->banks();
        if (isset($in['on']) && count($in) === 1 && $id !== null) { // the switch alone
            foreach ($banks as &$b) {
                if ((int) $b['id'] === $id) {
                    $b['on'] = filter_var($in['on'], FILTER_VALIDATE_BOOL);
                }
            }
            unset($b);
        } else {
            $e = [];
            $row = [];
            foreach (['bank' => [60, 'Enter the bank name.'], 'branch' => [60, 'Enter the branch.'], 'name' => [100, 'Enter the name on the account.'], 'number' => [40, 'Enter the account number.']] as $k => [$max, $msg]) {
                $row[$k] = trim((string) ($in[$k] ?? ''));
                if ($row[$k] === '' || mb_strlen($row[$k]) > $max) {
                    $e[$k] = $row[$k] === '' ? $msg : "Up to $max characters.";
                }
            }
            if ($e) {
                throw new HttpException(422, 'validation_failed', 'Please check the highlighted fields.', $e);
            }
            if ($id === null) {
                $banks[] = ['id' => max([0, ...array_column($banks, 'id')]) + 1, ...$row, 'on' => true];
            } else {
                foreach ($banks as &$b) {
                    if ((int) $b['id'] === $id) {
                        $b = ['id' => $id, ...$row, 'on' => $b['on'] ?? true];
                    }
                }
                unset($b);
            }
        }
        $this->settings->saveBanks($banks, $by);
        $this->log(null, 'banks', 'Bank accounts changed', null, null, null, $by);
        return $this->settings->banks();
    }

    public function deleteBank(int $id, int $by): array
    {
        $this->settings->saveBanks(array_values(array_filter($this->settings->banks(), static fn ($b) => (int) $b['id'] !== $id)), $by);
        $this->log(null, 'banks', 'Bank account removed', null, null, null, $by);
        return $this->settings->banks();
    }

    // ================================================================ helpers

    public function log(?int $tutorId, string $action, string $summary, ?string $reason, ?array $before, ?array $after, ?int $by): void
    {
        $this->db->execute(
            'INSERT INTO platform_billing_logs (users_user_id, action, summary, reason, before_json, after_json, done_by, created_at) VALUES (:t, :a, :s, :r, :b, :af, :by, NOW())',
            ['t' => $tutorId, 'a' => $action, 's' => mb_substr($summary, 0, 250), 'r' => $reason === null ? null : mb_substr($reason, 0, 250),
                'b' => $before === null ? null : json_encode($before), 'af' => $after === null ? null : json_encode($after), 'by' => $by],
        );
    }

    /** 0771234567 / +94 77 123 4567 / 94771234567 → 0771234567, or null. */
    public static function phone(string $raw): ?string
    {
        $d = preg_replace('/\D+/', '', $raw);
        if (str_starts_with($d, '0094')) {
            $d = substr($d, 4);
        } elseif (str_starts_with($d, '94') && strlen($d) === 11) {
            $d = substr($d, 2);
        }
        if (strlen($d) === 9 && $d[0] === '7') {
            $d = '0' . $d;
        }
        return preg_match('/^07\d{8}$/', $d) ? $d : null;
    }

    private function dateOrError(mixed $v, string $key, array &$e, bool $notFuture): ?string
    {
        $v = trim((string) $v);
        $d = \DateTimeImmutable::createFromFormat('!Y-m-d', $v);
        if (!$d || $d->format('Y-m-d') !== $v) {
            $e[$key] = 'Choose a date.';
            return null;
        }
        if ($notFuture && $v > $this->today) {
            $e[$key] = 'The day it was paid cannot be in the future.';
            return null;
        }
        return $v;
    }

    /** @return array{tmp:string, ext:string, hash:string}|null */
    private function checkSlip(?array $file, array &$e): ?array
    {
        if ($file === null || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            $e['slip'] = 'Choose the photo or PDF of your bank slip.';
            return null;
        }
        if ($file['error'] !== UPLOAD_ERR_OK || !is_file($file['tmp_name'])) {
            $e['slip'] = 'The file did not upload. Please try again.';
            return null;
        }
        if ($file['size'] > self::SLIP_MAX) {
            $e['slip'] = 'The file is larger than 5 MB. Take a smaller photo or scan.';
            return null;
        }
        $type = (new \finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
        if (!isset(self::SLIP_TYPES[$type])) {
            $e['slip'] = 'Use a JPG, PNG or PDF file.';
            return null;
        }
        $hash = hash_file('sha256', $file['tmp_name']);
        if ($this->db->value('SELECT 1 FROM users_payments_slips WHERE slip_hash = :h', ['h' => $hash])) {
            $e['slip'] = 'This slip was uploaded before. Upload the slip of this payment.';
            return null;
        }
        return ['tmp' => $file['tmp_name'], 'ext' => self::SLIP_TYPES[$type], 'hash' => $hash];
    }
}

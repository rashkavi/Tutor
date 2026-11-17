<?php
declare(strict_types=1);

namespace Enlixo\Fees;

use Enlixo\Core\Database;
use Enlixo\Core\HttpException;

/**
 * Student Fees on the real tables (step B of docs/FEES-PLAN.md; the pages were written against FeesMock first).
 * A fee = one `student_has_payments` row (one class month of one student). Its status:
 *   payment_status 1 Awaiting Payment -> unpaid (overdue after the due date), 2 Awaiting Approval -> waiting (a slip is
 *   uploaded, student_has_payments_slips), 3 Accepted -> paid, 4 Rejected -> rejected; nothing left to pay -> free.
 * net = amount - discount (discount_type 2 fixed, 3 percent) + late fees (student_has_payments_penalties).
 * A receipt = the fees paid together: every fee of it gets the log line "Receipt RC-00012" (student_has_payment_logs),
 * the number is made from the first fee's id, so no new table is needed. Fee Settings are kept in tutor_fee_settings.
 * The tutor's data only: every query goes through classes.tutor_id.
 */
final class TutorFees
{
    public const REJECT_REASONS = ['The amount on the slip is not the class fee', 'The slip is not clear, please upload a clear photo', 'The money has not reached my account yet', 'This slip was used before', 'Other'];
    private const METHOD_NAMES = [1 => 'Bank slip', 2 => 'Bank deposit', 3 => 'Cash', 4 => 'Card', 5 => 'Cheque'];
    private const METHOD_IDS = ['Bank slip' => 1, 'Bank deposit' => 2, 'Cash' => 3, 'Card' => 4, 'Cheque' => 5];

    private ?array $fees = null;
    private array $classes = [];
    private array $students = [];
    private array $enrolments = [];
    private array $logs = [];

    public function __construct(private readonly Database $db, private readonly int $tutorId, private readonly int $actorId, private readonly string $today)
    {
    }

    // ---------------------------------------------------------------- loading
    private function load(): void
    {
        if ($this->fees !== null) {
            return;
        }
        $t = ['t' => $this->tutorId];
        foreach ($this->db->all(
            'SELECT c.class_id, c.class_name,
                    (SELECT p.amount FROM class_payment_plans p WHERE p.classes_class_id = c.class_id ORDER BY p.class_payment_plan_id DESC LIMIT 1) fee,
                    (SELECT p.due_date FROM class_payment_plans p WHERE p.classes_class_id = c.class_id ORDER BY p.class_payment_plan_id DESC LIMIT 1) due_day
               FROM classes c WHERE c.tutor_id = :t ORDER BY c.class_name', $t) as $c) {
            $this->classes[(int) $c['class_id']] = ['id' => (int) $c['class_id'], 'name' => (string) $c['class_name'], 'short' => self::shortName((string) $c['class_name']),
                'fee' => (float) ($c['fee'] ?? 0), 'due_day' => max(1, (int) ($c['due_day'] ?? 10))];
        }
        foreach ($this->db->all(
            'SELECT e.students_has_class_id, e.student_class_id, e.status_status_id, e.added_at, e.classes_class_id,
                    s.student_id, s.full_name, s.mobile, s.date_added, ld.whatsapp, ld.email
               FROM students_has_classes e
               JOIN classes c ON c.class_id = e.classes_class_id
               JOIN students s ON s.student_id = e.students_student_id
               LEFT JOIN login_details ld ON ld.login_details_id = s.login_details_login_details_id
              WHERE c.tutor_id = :t', $t) as $e) {
            $sid = (int) $e['student_id'];
            $this->students[$sid] ??= ['id' => $sid, 'sid' => $e['student_class_id'] ?: sprintf('ST%05d', $sid), 'name' => trim((string) $e['full_name']),
                'whatsapp' => (string) ($e['whatsapp'] ?: $e['mobile'] ?: ''), 'email' => (string) ($e['email'] ?? ''), 'joined' => substr((string) $e['date_added'], 0, 10)];
            $this->enrolments[(int) $e['students_has_class_id']] = ['id' => (int) $e['students_has_class_id'], 'student' => $sid, 'class' => (int) $e['classes_class_id'],
                'active' => (int) $e['status_status_id'] === 1, 'since' => substr((string) $e['added_at'], 0, 7)];
        }
        $rows = $this->db->all(
            'SELECT p.student_has_payment_id id, p.students_has_class_students_has_class_id enr, p.amount, p.discount, p.discount_type_discount_type_id dtype, p.discount_note,
                    p.due_date, p.date_paid, p.date_added, p.refference, p.payment_method_payment_method_id method, COALESCE(p.payment_status_payment_status_id, 1) st,
                    (SELECT COALESCE(SUM(x.amount), 0) FROM student_has_payments_penalties x WHERE x.student_has_payments_student_has_payment_id = p.student_has_payment_id) penalty
               FROM student_has_payments p
               JOIN students_has_classes e ON e.students_has_class_id = p.students_has_class_students_has_class_id
               JOIN classes c ON c.class_id = e.classes_class_id
              WHERE c.tutor_id = :t ORDER BY p.due_date, p.student_has_payment_id', $t);
        $ids = array_map(static fn ($r) => (int) $r['id'], $rows);
        $slips = [];
        if ($ids) {
            $in = implode(',', $ids);
            foreach ($this->db->all("SELECT student_has_payments_student_has_payment_id pid, slip_name, reference FROM student_has_payments_slips WHERE student_has_payments_student_has_payment_id IN ($in) ORDER BY student_has_payments_slip_id") as $s) {
                $slips[(int) $s['pid']] = $s;
            }
            foreach ($this->db->all("SELECT l.student_has_payments_student_has_payment_id pid, l.date_updated, l.log, l.note, TRIM(CONCAT(u.user_fname, ' ', u.user_lname)) who, u.user_id
                                       FROM student_has_payment_logs l LEFT JOIN users u ON u.user_id = l.users_user_id
                                      WHERE l.student_has_payments_student_has_payment_id IN ($in) ORDER BY l.student_has_payment_log_id") as $l) {
                $this->logs[(int) $l['pid']][] = $l;
            }
        }
        $this->fees = [];
        foreach ($rows as $r) {
            $e = $this->enrolments[(int) $r['enr']] ?? null;
            if ($e === null) {
                continue;
            }
            $id = (int) $r['id'];
            $amount = (float) $r['amount'];
            $discount = match ((int) $r['dtype']) { 2 => (float) $r['discount'], 3 => round($amount * (float) $r['discount'] / 100, 2), default => 0.0 };
            $discount = min($amount, max(0.0, $discount));
            $penalty = (float) $r['penalty'];
            $net = max(0.0, $amount - $discount + $penalty);
            $st = (int) $r['st'];
            $status = $net <= 0 && $st !== 3 ? 'free' : match ($st) { 2 => 'waiting', 3 => 'paid', 4 => 'rejected', default => 'unpaid' };
            $slip = $slips[$id] ?? null;
            $logs = $this->logs[$id] ?? [];
            $receipt = null;
            $checked = null;
            $reason = null;
            foreach ($logs as $l) {
                if (preg_match('/Receipt (RC-\d+)/', (string) $l['log'], $m)) {
                    $receipt = $m[1];
                    $checked = $l['who'] ?: null;
                }
                if (str_starts_with((string) $l['log'], 'Slip rejected')) {
                    $reason = trim((string) ($l['note'] ?? '')) ?: trim(substr((string) $l['log'], 14));
                    $checked = $l['who'] ?: null;
                }
            }
            if ($status === 'paid' && $receipt === null) {
                $receipt = sprintf('RC-%05d', $id);
            }
            $method = $r['method'] !== null ? (self::METHOD_NAMES[(int) $r['method']] ?? null) : null;
            if ($method === null && $slip) {
                $method = 'Bank slip';
            }
            $this->fees[$id] = [
                'id' => $id, 'enrolment' => $e['id'], 'student' => $e['student'], 'class' => $e['class'], 'month' => substr((string) $r['due_date'], 0, 7),
                'amount' => $amount, 'discount' => $discount, 'penalty' => $penalty, 'net' => $net, 'due' => (string) $r['due_date'], 'status' => $status,
                'paid_on' => $r['date_paid'] ? substr((string) $r['date_paid'], 0, 10) : null, 'method' => in_array($status, ['paid', 'waiting', 'rejected'], true) ? $method : null,
                'receipt' => $status === 'paid' ? $receipt : null, 'reason' => $status === 'rejected' ? $reason : null, 'checked_by' => $checked,
                'slip' => $slip ? ['file' => (string) $slip['slip_name'], 'url' => '../assets/uploads/payment-slips/' . rawurlencode((string) $slip['slip_name']),
                    'reference' => (string) ($slip['reference'] ?: $r['refference']), 'uploaded' => substr((string) ($r['date_paid'] ?: $r['date_added']), 0, 16),
                    'amount' => $net, 'bank' => 'Bank'] : ($status === 'waiting' ? ['file' => '', 'url' => null, 'missing' => true, 'reference' => (string) $r['refference'],
                    'uploaded' => substr((string) ($r['date_paid'] ?: $r['date_added']), 0, 16), 'amount' => $net, 'bank' => ''] : null),
                'discount_note' => (string) ($r['discount_note'] ?? ''),
            ];
        }
    }

    private static function shortName(string $name): string
    {
        return mb_strlen($name) > 26 ? mb_substr($name, 0, 25) . '…' : $name;
    }

    private static function label(string $month, bool $long = false): string
    {
        $t = strtotime($month . '-01');
        return $t ? date($long ? 'F Y' : 'M', $t) : $month;
    }

    private function ym(int $back = 0): string
    {
        return date('Y-m', strtotime(substr($this->today, 0, 7) . "-01 -$back month"));
    }

    private function status(array $f): string
    {
        return $f['status'] === 'unpaid' && $f['due'] < $this->today ? 'overdue' : $f['status'];
    }

    private function owed(array $f): bool
    {
        return in_array($f['status'], ['unpaid', 'rejected'], true) && $f['net'] > 0;
    }

    private function active(array $f): bool
    {
        return $this->enrolments[$f['enrolment']]['active'] ?? false;
    }

    private function student(int $id): array
    {
        $s = $this->students[$id] ?? null;
        return $s ? ['id' => $s['id'], 'sid' => $s['sid'], 'name' => $s['name'], 'whatsapp' => $s['whatsapp']] : ['id' => $id, 'sid' => '', 'name' => 'Student', 'whatsapp' => ''];
    }

    private function cls(int $id): array
    {
        $c = $this->classes[$id] ?? ['id' => $id, 'name' => 'Class', 'short' => 'Class', 'fee' => 0, 'due_day' => 10];
        return ['id' => $c['id'], 'name' => $c['name'], 'short' => $c['short']];
    }

    private function fee(array $f): array
    {
        return array_merge($f, ['status' => $this->status($f), 'month_label' => self::label($f['month'], true), 'student' => $this->student($f['student']), 'class' => $this->cls($f['class'])]);
    }

    private function feeOf(int $id): array
    {
        $this->load();
        return $this->fees[$id] ?? throw HttpException::notFound('This fee was not found.');
    }

    /** search, sort and pages like the mock (and the other lists of the app) */
    private static function page(array $rows, array $q, callable $search): array
    {
        $term = mb_strtolower(trim((string) ($q['q'] ?? '')));
        if ($term !== '') {
            $rows = array_values(array_filter($rows, static fn ($r) => str_contains(mb_strtolower($search($r)), $term)));
        }
        if (!empty($q['sort'])) {
            $k = (string) $q['sort'];
            $dir = ($q['dir'] ?? '') === 'desc' ? -1 : 1;
            $v = static fn ($r) => is_array($r[$k] ?? null) ? ($r[$k]['name'] ?? '') : ($r[$k] ?? '');
            usort($rows, static fn ($a, $b) => $dir * ($v($a) <=> $v($b)));
        }
        $per = max(1, min(200, (int) ($q['per_page'] ?? 10)));
        $page = max(1, (int) ($q['page'] ?? 1));
        return ['data' => array_slice($rows, ($page - 1) * $per, $per), 'meta' => ['total' => count($rows), 'page' => $page, 'per_page' => $per, 'pages' => max(1, (int) ceil(count($rows) / $per))]];
    }

    // ---------------------------------------------------------------- reads
    public function overview(string $month, ?int $cls): array
    {
        $this->load();
        $in = fn ($f) => !$cls || $f['class'] === $cls;
        $of = fn (string $k) => array_filter($this->fees, fn ($f) => $f['month'] === $k && $in($f));
        $sum = fn (array $list, array $st) => array_sum(array_map(fn ($f) => in_array($this->status($f), $st, true) ? $f['net'] : 0, $list));
        $due = fn (array $list) => array_sum(array_map(static fn ($f) => $f['status'] === 'free' ? 0 : $f['net'], $list));
        $now = $of($month);
        $months = array_map(fn ($i) => $this->ym(11 - $i), range(0, 11));
        $prev = date('Y-m', strtotime($month . '-01 -1 month'));
        $arrears = array_filter($this->fees, fn ($f) => $this->owed($f) && $this->active($f) && $f['month'] < $month && $in($f));
        $unpaidNow = array_filter($now, fn ($f) => in_array($this->status($f), ['unpaid', 'overdue', 'rejected'], true));
        $free = array_filter($now, static fn ($f) => $f['discount'] > 0);
        $waiting = array_filter($this->fees, fn ($f) => $f['status'] === 'waiting' && $in($f));
        $last6 = array_slice($months, -6);
        $arrOn = $this->has('payments.arrears');
        return [
            'month' => $month, 'month_label' => self::label($month, true), 'is_current' => $month === $this->ym(),
            'months' => array_reverse(array_map(static fn ($k) => ['value' => $k, 'label' => self::label($k, true)], $months)),
            'classes' => array_values(array_map(static fn ($c) => ['id' => $c['id'], 'name' => $c['name'], 'short' => $c['short'], 'fee' => $c['fee'], 'due_day' => $c['due_day']], $this->classes)),
            'stats' => [
                'collected' => ['value' => $sum($now, ['paid']), 'before' => $sum($of($prev), ['paid']), 'bars' => array_map(fn ($k) => ['label' => self::label($k), 'value' => $sum($of($k), ['paid'])], $last6)],
                'to_collect' => ['value' => $sum($now, ['unpaid', 'overdue', 'rejected']), 'students' => count(array_unique(array_column($unpaidNow, 'student'))), 'overdue' => count(array_filter($now, fn ($f) => $this->status($f) === 'overdue'))],
                'slips' => ['value' => count($waiting), 'amount' => array_sum(array_column($waiting, 'net'))],
                'arrears' => ['value' => array_sum(array_column($arrears, 'net')), 'students' => count(array_unique(array_column($arrears, 'student')))],
                'free' => ['value' => array_sum(array_column($free, 'discount')), 'students' => count($free), 'full' => count(array_filter($now, static fn ($f) => $f['status'] === 'free'))],
                'rate' => ['value' => $due($now) ? (int) round($sum($now, ['paid', 'waiting']) / $due($now) * 100) : 0,
                    'bars' => array_map(fn ($k) => ['label' => self::label($k), 'value' => $due($of($k)) ? (int) round($sum($of($k), ['paid', 'waiting']) / $due($of($k)) * 100) : 0], $last6)],
            ],
            'chart' => ['labels' => array_map(static fn ($k) => self::label($k), $months), 'due' => array_map(fn ($k) => $due($of($k)), $months),
                'collected' => array_map(fn ($k) => $sum($of($k), ['paid']), $months), 'waiting' => array_map(fn ($k) => $sum($of($k), ['waiting']), $months)],
            'by_class' => array_values(array_map(function ($c) use ($now, $sum, $due) {
                $list = array_filter($now, static fn ($f) => $f['class'] === $c['id']);
                return ['id' => $c['id'], 'name' => $c['name'], 'short' => $c['short'], 'fee' => $c['fee'], 'due_day' => $c['due_day'], 'due' => $due($list), 'collected' => $sum($list, ['paid']),
                    'waiting' => $sum($list, ['waiting']), 'unpaid' => count(array_filter($list, fn ($f) => in_array($this->status($f), ['unpaid', 'overdue', 'rejected'], true))), 'students' => count($list)];
            }, array_filter($this->classes, static fn ($c) => !$cls || $c['id'] === $cls))),
            'counts' => ['month' => count($now), 'slips' => count($waiting),
                'unpaid' => count(array_unique(array_column(array_filter($this->fees, fn ($f) => $this->owed($f) && $this->active($f) && $f['due'] < $this->today && $in($f) && ($arrOn || $f['month'] === $this->ym())), 'enrolment'))),
                'receipts' => count($this->receiptGroups())],
            'settings' => array_intersect_key($this->settings(), ['methods' => 1, 'late_fee' => 1]),
        ];
    }

    public function list(array $q): array
    {
        $this->load();
        $month = (string) ($q['month'] ?? $this->ym());
        $cls = (int) ($q['class'] ?? 0);
        $order = ['waiting' => 0, 'overdue' => 1, 'rejected' => 2, 'unpaid' => 3, 'paid' => 4, 'free' => 5];
        $rows = array_values(array_filter(array_map(fn ($f) => $this->fee($f), array_filter($this->fees, static fn ($f) => $f['month'] === $month && (!$cls || $f['class'] === $cls))),
            static fn ($f) => (empty($q['status']) || $f['status'] === $q['status'] || ($q['status'] === 'unpaid' && in_array($f['status'], ['unpaid', 'overdue', 'rejected'], true))) && (empty($q['method']) || $f['method'] === $q['method'])));
        usort($rows, static fn ($a, $b) => ($order[$a['status']] <=> $order[$b['status']]) ?: strcmp($a['student']['name'], $b['student']['name']));
        return self::page($rows, $q, static fn ($f) => "{$f['student']['name']} {$f['student']['sid']} {$f['student']['whatsapp']} {$f['class']['name']} " . ($f['receipt'] ?? ''));
    }

    public function slips(array $q): array
    {
        $this->load();
        $cls = (int) ($q['class'] ?? 0);
        $rows = array_values(array_map(fn ($f) => $this->fee($f), array_filter($this->fees, static fn ($f) => $f['status'] === 'waiting' && (!$cls || $f['class'] === $cls))));
        usort($rows, static fn ($a, $b) => strcmp($a['slip']['uploaded'] ?? '', $b['slip']['uploaded'] ?? ''));
        return self::page($rows, $q, static fn ($f) => "{$f['student']['name']} {$f['student']['sid']} {$f['class']['name']} " . ($f['slip']['reference'] ?? ''));
    }

    public function unpaid(array $q): array
    {
        $this->load();
        $thisMonth = !$this->has('payments.arrears');
        $cls = (int) ($q['class'] ?? 0);
        $groups = [];
        foreach ($this->fees as $f) {
            if ($this->owed($f) && $this->active($f) && $f['due'] < $this->today && (!$thisMonth || $f['month'] === $this->ym()) && (!$cls || $f['class'] === $cls)) {
                $groups[$f['enrolment']][] = $this->fee($f);
            }
        }
        $rem = $this->settingsRow()['reminders'] ?? [];
        $rows = [];
        foreach ($groups as $id => $list) {
            $rows[] = ['id' => $id, 'student' => $list[0]['student'], 'class' => $list[0]['class'],
                'months' => array_map(static fn ($f) => ['id' => $f['id'], 'month' => $f['month'], 'label' => self::label($f['month']), 'net' => $f['net'], 'status' => $f['status']], $list),
                'count' => count($list), 'total' => array_sum(array_column($list, 'net')), 'oldest' => $list[0]['due'], 'last_reminder' => $rem[(string) $id] ?? null];
        }
        if (!empty($q['months'])) {
            $rows = array_values(array_filter($rows, static fn ($r) => $q['months'] === '3' ? $r['count'] >= 3 : $r['count'] === (int) $q['months']));
        }
        usort($rows, static fn ($a, $b) => ($b['count'] <=> $a['count']) ?: ($b['total'] <=> $a['total']));
        return self::page($rows, $q, static fn ($r) => "{$r['student']['name']} {$r['student']['sid']} {$r['class']['name']}");
    }

    /** @return array<string, list<array>> receipt number => its paid fees */
    private function receiptGroups(): array
    {
        $g = [];
        foreach ($this->fees as $f) {
            if ($f['status'] === 'paid' && $f['receipt']) {
                $g[$f['receipt']][] = $f;
            }
        }
        return $g;
    }

    private function receiptRow(string $no, array $list): array
    {
        $first = $list[0];
        $s = $this->students[$first['student']] ?? $this->student($first['student']);
        return ['id' => min(array_column($list, 'id')), 'no' => $no, 'enrolment' => $first['enrolment'], 'student' => $s, 'class' => $this->cls($first['class']),
            'fees' => array_column($list, 'id'), 'months' => array_column($list, 'month'), 'month_labels' => array_map(static fn ($m) => self::label($m), array_column($list, 'month')),
            'amount' => array_sum(array_column($list, 'net')), 'method' => $first['method'] ?? 'Cash', 'paid_on' => $first['paid_on'] ?? '', 'by' => $first['checked_by'] ?? 'You',
            'reference' => $first['slip']['reference'] ?? null, 'status' => 'valid'];
    }

    public function receipts(array $q): array
    {
        $this->load();
        $rows = [];
        foreach ($this->receiptGroups() as $no => $list) {
            $r = $this->receiptRow($no, $list);
            if ((!empty($q['class']) && $r['class']['id'] !== (int) $q['class']) || (!empty($q['method']) && $r['method'] !== $q['method']) || (!empty($q['month']) && !str_starts_with($r['paid_on'], (string) $q['month']))) {
                continue;
            }
            $rows[] = $r;
        }
        usort($rows, static fn ($a, $b) => strcmp($b['paid_on'], $a['paid_on']) ?: ($b['id'] <=> $a['id']));
        return self::page($rows, $q, static fn ($r) => "{$r['no']} {$r['student']['name']} {$r['student']['sid']} {$r['class']['name']} " . ($r['reference'] ?? ''));
    }

    public function receipt(int $id): array
    {
        $f = $this->feeOf($id);
        if ($f['status'] !== 'paid' || !$f['receipt']) {
            throw HttpException::notFound('This receipt was not found.');
        }
        $list = $this->receiptGroups()[$f['receipt']];
        $r = $this->receiptRow($f['receipt'], $list);
        $t = $this->db->one('SELECT u.user_fname, u.user_lname, u.user_mobile, s.institute_name FROM users u LEFT JOIN users_has_payment_plans s ON s.users_user_id = u.user_id WHERE u.user_id = :t ORDER BY s.users_has_payment_plan_id DESC LIMIT 1', ['t' => $this->tutorId]) ?? [];
        $set = $this->settings();
        return $r + [
            'lines' => array_map(static fn ($x) => ['text' => self::label($x['month'], true) . ' fee', 'amount' => $x['amount'], 'discount' => $x['discount'], 'penalty' => $x['penalty'], 'net' => $x['net']], $list),
            'tutor' => ['name' => trim(($t['user_fname'] ?? '') . ' ' . ($t['user_lname'] ?? '')), 'brand' => (string) ($t['institute_name'] ?? ''), 'phone' => (string) ($t['user_mobile'] ?? ''), 'address' => ''],
            'footer' => $set['receipts']['footer'] ?? '',
        ];
    }

    public function detail(int $id): array
    {
        $f = $this->feeOf($id);
        $out = $this->fee($f);
        $history = [];
        $history[] = ['when' => substr((string) ($this->db->value('SELECT date_added FROM student_has_payments WHERE student_has_payment_id = :i', ['i' => $id]) ?? ''), 0, 16), 'by' => 'System',
            'what' => self::label($f['month'], true) . ' fee created: LKR ' . number_format($f['amount']) . ($f['discount'] ? ($f['discount'] >= $f['amount'] ? ', free card' : ', discount LKR ' . number_format($f['discount'])) : '')];
        if ($f['penalty']) {
            $history[] = ['when' => $f['due'], 'by' => 'System', 'what' => 'Late fee LKR ' . number_format($f['penalty']) . ' added'];
        }
        foreach ($this->logs[$id] ?? [] as $l) {
            $history[] = ['when' => substr((string) $l['date_updated'], 0, 16), 'by' => $l['who'] ?: 'Student', 'what' => trim($l['log'] . ($l['note'] ? ': ' . $l['note'] : ''))];
        }
        $out['history'] = array_reverse($history);
        $out['receipt_id'] = $f['status'] === 'paid' ? $id : null;
        $out['reasons'] = self::REJECT_REASONS;
        return $out;
    }

    public function studentsList(): array
    {
        $this->load();
        $active = array_unique(array_map(static fn ($e) => $e['student'], array_filter($this->enrolments, static fn ($e) => $e['active'])));
        $rows = array_values(array_map(fn ($id) => $this->student($id), $active));
        usort($rows, static fn ($a, $b) => strcmp($a['name'], $b['name']));
        return $rows;
    }

    public function openFor(int $studentId): array
    {
        $this->load();
        if (!isset($this->students[$studentId])) {
            throw HttpException::notFound('This student is not in your classes.');
        }
        $classes = [];
        foreach ($this->enrolments as $e) {
            if ($e['student'] !== $studentId || !$e['active']) {
                continue;
            }
            $c = $this->classes[$e['class']] ?? null;
            if (!$c) {
                continue;
            }
            $mine = array_filter($this->fees, static fn ($f) => $f['enrolment'] === $e['id']);
            $open = array_values(array_map(fn ($f) => ['id' => $f['id'], 'month' => $f['month'], 'label' => self::label($f['month'], true), 'net' => $f['net'], 'amount' => $f['amount'],
                'discount' => $f['discount'], 'penalty' => $f['penalty'], 'status' => $this->status($f)], array_filter($mine, fn ($f) => $this->owed($f))));
            $lastMonth = $mine ? max(array_column($mine, 'month')) : $this->ym(1);
            $next = date('Y-m', strtotime($lastMonth . '-01 +1 month'));
            $classes[] = ['enrolment' => $e['id'], 'class' => ['id' => $c['id'], 'name' => $c['name']], 'fee' => $c['fee'], 'open' => $open,
                'advance' => ['id' => 'next-' . $e['id'], 'month' => $next, 'label' => self::label($next, true), 'net' => $c['fee'], 'amount' => $c['fee'], 'discount' => 0, 'penalty' => 0, 'status' => 'advance']];
        }
        return ['student' => $this->student($studentId), 'classes' => $classes];
    }

    public function card(int $studentId): array
    {
        $this->load();
        $s = $this->students[$studentId] ?? throw HttpException::notFound('This student is not in your classes.');
        $fees = array_values(array_map(fn ($f) => $this->fee($f), array_filter($this->fees, static fn ($f) => $f['student'] === $studentId)));
        $year = substr($this->today, 0, 4);
        $owed = array_filter($fees, fn ($f) => in_array($f['status'], ['unpaid', 'overdue', 'rejected'], true) && $f['due'] < $this->today);
        $receipts = [];
        foreach ($this->receiptGroups() as $no => $list) {
            if ($list[0]['student'] === $studentId) {
                $receipts[] = $this->receiptRow($no, $list);
            }
        }
        usort($receipts, static fn ($a, $b) => strcmp($b['paid_on'], $a['paid_on']));
        $rem = $this->settingsRow()['reminders'] ?? [];
        $classes = [];
        foreach ($this->enrolments as $e) {
            if ($e['student'] !== $studentId) {
                continue;
            }
            $c = $this->classes[$e['class']] ?? null;
            if (!$c) {
                continue;
            }
            $mine = array_values(array_filter($fees, static fn ($f) => $f['enrolment'] === $e['id']));
            $disc = array_values(array_filter($mine, static fn ($f) => $f['discount'] > 0));
            $last = end($disc) ?: null;
            $classes[] = ['enrolment' => $e['id'], 'id' => $c['id'], 'name' => $c['name'], 'fee' => $c['fee'], 'due_day' => $c['due_day'], 'since' => self::label($e['since'], true),
                'concession' => $last ? ['kind' => $last['discount'] >= $last['amount'] ? 'free' : 'fixed', 'value' => $last['discount'], 'reason' => $last['discount_note'], 'from' => $disc[0]['month'], 'to' => null] : null,
                'months' => array_map(static fn ($f) => ['id' => $f['id'], 'month' => $f['month'], 'label' => self::label($f['month']), 'long' => $f['month_label'], 'status' => $f['status'], 'net' => $f['net'], 'receipt' => $f['receipt'], 'paid_on' => $f['paid_on']], $mine)];
        }
        return [
            'student' => $s,
            'stats' => [
                'paid_year' => array_sum(array_map(static fn ($f) => $f['status'] === 'paid' && str_starts_with($f['month'], $year) ? $f['net'] : 0, $fees)),
                'owed' => array_sum(array_column($owed, 'net')), 'owed_months' => count($owed),
                'waiting' => count(array_filter($fees, static fn ($f) => $f['status'] === 'waiting')),
                'saved' => array_sum(array_column($fees, 'discount')), 'last' => $receipts[0] ?? null,
            ],
            'classes' => $classes, 'receipts' => $receipts,
            'reminders' => array_values(array_filter(array_map(static fn ($c) => isset($rem[(string) $c['enrolment']]) ? ['enrolment' => $c['enrolment'], 'when' => $rem[(string) $c['enrolment']]] : null, $classes))),
        ];
    }

    // ---------------------------------------------------------------- writes
    private function log(int $feeId, string $log, ?string $note = null): void
    {
        $this->db->execute('INSERT INTO student_has_payment_logs (student_has_payments_student_has_payment_id, users_user_id, date_updated, log, note) VALUES (:p, :u, NOW(), :l, :n)',
            ['p' => $feeId, 'u' => $this->actorId, 'l' => mb_substr($log, 0, 100), 'n' => $note]);
    }

    public function decide(int $id, string $action, array $b): array
    {
        $f = $this->feeOf($id);
        if ($f['status'] !== 'waiting') {
            throw HttpException::validation(['reason' => 'This slip was already checked.']);
        }
        if ($action === 'reject') {
            $reason = trim((string) ($b['reason'] ?? ''));
            if ($reason === '') {
                throw HttpException::validation(['reason' => 'Choose why. The student sees it and can upload again.']);
            }
            $note = trim((string) ($b['note'] ?? ''));
            $this->db->transaction(function () use ($id, $reason, $note) {
                $this->db->execute('UPDATE student_has_payments SET payment_status_payment_status_id = 4 WHERE student_has_payment_id = :i', ['i' => $id]);
                $this->log($id, 'Slip rejected', $reason . ($note !== '' ? " ($note)" : ''));
            });
            $this->fees = null;
            return ['fee' => $this->fee($this->feeOf($id))];
        }
        $no = sprintf('RC-%05d', $id);
        $this->db->transaction(function () use ($id, $no) {
            $this->db->execute('UPDATE student_has_payments SET payment_status_payment_status_id = 3, date_paid = COALESCE(date_paid, NOW()), payment_method_payment_method_id = COALESCE(payment_method_payment_method_id, 1) WHERE student_has_payment_id = :i', ['i' => $id]);
            $this->log($id, "Slip approved. Receipt $no");
        });
        $this->fees = null;
        $this->load();
        $next = null;
        foreach ($this->fees as $x) {
            if ($x['status'] === 'waiting') {
                $next = $x['id'];
                break;
            }
        }
        return ['fee' => $this->fee($this->fees[$id]), 'receipt' => $id, 'next' => $next];
    }

    public function record(array $b): array
    {
        $this->load();
        $e = [];
        $student = (int) ($b['student'] ?? 0);
        $items = array_values((array) ($b['items'] ?? []));
        $method = (string) ($b['method'] ?? '');
        $paidOn = (string) ($b['paid_on'] ?? '');
        if (!isset($this->students[$student])) {
            $e['student'] = 'Choose the student who paid.';
        }
        if (!$items) {
            $e['items'] = 'Tick at least one month.';
        }
        if (!isset(self::METHOD_IDS[$method])) {
            $e['method'] = 'Choose how the student paid.';
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $paidOn)) {
            $e['paid_on'] = 'Choose the day the money was received.';
        } elseif ($paidOn > $this->today) {
            $e['paid_on'] = 'The day cannot be in the future.';
        }
        $ref = mb_substr(trim((string) ($b['reference'] ?? '')), 0, 20);
        if ($method === 'Bank deposit' && $ref === '') {
            $e['reference'] = 'Enter the reference on the bank slip, for example 004512.';
        }
        $ids = [];
        $advance = [];
        foreach ($items as $it) {
            if (is_string($it) && preg_match('/^next-(\d+)$/', $it, $m)) {
                $advance[] = (int) $m[1];
            } elseif (isset($this->fees[(int) $it]) && $this->fees[(int) $it]['student'] === $student && $this->owed($this->fees[(int) $it])) {
                $ids[] = (int) $it;
            } elseif ($items) {
                $e['items'] = 'One of the ticked months is already paid or not this student\'s. Reload the page.';
            }
        }
        foreach ($advance as $enr) {
            if (($this->enrolments[$enr]['student'] ?? 0) !== $student) {
                $e['items'] = 'Pay in Advance is for one of this student\'s classes only.';
            }
        }
        if ($e) {
            throw HttpException::validation($e);
        }
        $note = trim((string) ($b['note'] ?? ''));
        $receiptId = $this->db->transaction(function () use ($ids, $advance, $method, $paidOn, $ref, $note) {
            // "Pay in Advance": next month's fee row is made now (same as the monthly job would make it), then paid
            foreach ($advance as $enr) {
                $c = $this->classes[$this->enrolments[$enr]['class']];
                $mine = array_filter($this->fees, static fn ($f) => $f['enrolment'] === $enr);
                $month = date('Y-m', strtotime(($mine ? max(array_column($mine, 'month')) : date('Y-m', strtotime(substr($this->today, 0, 7) . '-01 -1 month'))) . '-01 +1 month'));
                $plan = $this->db->one('SELECT class_payment_plan_id, discount_type_discount_type_id, discount, discount_note, panelty_payment_option_panelty_payment_option_id FROM class_payment_plans WHERE classes_class_id = :c ORDER BY class_payment_plan_id DESC LIMIT 1', ['c' => $c['id']]);
                $ids[] = $this->db->insert(
                    'INSERT INTO student_has_payments (students_has_class_students_has_class_id, class_payment_plans_class_payment_plan_id, amount, discount, discount_type_discount_type_id, discount_note,
                        panelty_payment_option_panelty_payment_option_id, due_date, refference, date_added, payment_status_payment_status_id)
                     VALUES (:e, :p, :a, 0, 1, \'\', :pen, :d, \'\', NOW(), 1)',
                    ['e' => $enr, 'p' => $plan['class_payment_plan_id'] ?? null, 'a' => $c['fee'], 'pen' => (int) ($plan['panelty_payment_option_panelty_payment_option_id'] ?? 1) ?: 1,
                        'd' => sprintf('%s-%02d', $month, min(28, $c['due_day']))],
                );
            }
            $no = sprintf('RC-%05d', min($ids));
            foreach ($ids as $id) {
                $this->db->execute('UPDATE student_has_payments SET payment_status_payment_status_id = 3, date_paid = :d, payment_method_payment_method_id = :m, refference = :r WHERE student_has_payment_id = :i',
                    ['d' => $paidOn . ' ' . date('H:i:s'), 'm' => self::METHOD_IDS[$method], 'r' => $ref, 'i' => $id]);
                $this->log($id, "$method received. Receipt $no", $note !== '' ? $note : null);
            }
            return min($ids);
        });
        return ['receipt' => $receiptId, 'no' => sprintf('RC-%05d', $receiptId)];
    }

    public function concede(array $b): array
    {
        $this->load();
        $e = [];
        $enr = (int) ($b['enrolment'] ?? 0);
        $type = (string) ($b['type'] ?? '');
        $value = (float) ($b['value'] ?? 0);
        $from = (string) ($b['from'] ?? '');
        $to = (string) ($b['to'] ?? '');
        $reason = trim((string) ($b['reason'] ?? ''));
        if (!isset($this->enrolments[$enr])) {
            $e['enrolment'] = 'Choose the class.';
        }
        if (!in_array($type, ['free', 'half', 'percent', 'fixed'], true)) {
            $e['type'] = 'Choose what to give.';
        } elseif ($type === 'percent' && ($value <= 0 || $value > 100)) {
            $e['value'] = 'Enter a percent between 1 and 100, for example 10.';
        } elseif ($type === 'fixed' && $value <= 0) {
            $e['value'] = 'Enter the rupees off every month, for example 500.';
        }
        if (!preg_match('/^\d{4}-\d{2}$/', $from)) {
            $e['from'] = 'Choose the first month.';
        }
        if ($to !== '' && (!preg_match('/^\d{4}-\d{2}$/', $to) || $to < $from)) {
            $e['to'] = 'The last month must be after the first month.';
        }
        if ($reason === '') {
            $e['reason'] = 'Say why. The reason is kept with the student.';
        }
        if ($e) {
            throw HttpException::validation($e);
        }
        [$dtype, $disc] = match ($type) { 'free' => [3, 100.0], 'half' => [3, 50.0], 'percent' => [3, $value], default => [2, $value] };
        $changed = 0;
        $this->db->transaction(function () use ($enr, $from, $to, $dtype, $disc, $reason, &$changed) {
            foreach ($this->fees as $f) {
                if ($f['enrolment'] !== $enr || $f['month'] < $from || ($to !== '' && $f['month'] > $to) || !in_array($f['status'], ['unpaid', 'rejected', 'free'], true)) {
                    continue; // a fee already paid or waiting for a check is not changed
                }
                $this->db->execute('UPDATE student_has_payments SET discount_type_discount_type_id = :t, discount = :d, discount_note = :n WHERE student_has_payment_id = :i',
                    ['t' => $dtype, 'd' => $disc, 'n' => $reason, 'i' => $f['id']]);
                $this->log($f['id'], $dtype === 3 && $disc >= 100 ? 'Free card given' : 'Discount given', $reason);
                $changed++;
            }
        });
        return ['saved' => true, 'changed' => $changed];
    }

    public function remind(array $b, ?callable $send): array
    {
        $this->load();
        $ids = array_values(array_filter(array_map('intval', (array) ($b['enrolments'] ?? [])), fn ($id) => isset($this->enrolments[$id])));
        if (!$ids) {
            throw HttpException::validation(['enrolments' => 'Tick at least one student first.']);
        }
        $channel = (string) ($b['channel'] ?? 'link');
        $row = $this->settingsRow();
        $when = date('Y-m-d H:i');
        $sent = 0;
        foreach ($ids as $id) {
            $e = $this->enrolments[$id];
            $owed = array_filter($this->fees, fn ($f) => $f['enrolment'] === $id && $this->owed($f));
            if ($channel !== 'link' && $send) {
                $s = $this->students[$e['student']];
                $c = $this->classes[$e['class']];
                $months = implode(', ', array_map(static fn ($f) => self::label($f['month'], true), $owed));
                $total = number_format(array_sum(array_column($owed, 'net')));
                $text = "Dear {$s['name']}, your {$c['name']} fee" . ($months ? " for $months (LKR $total)" : '') . ' is not paid yet. Please pay and upload the slip in the ENLIXO app, or pay at the next class. Thank you.';
                if ($send($s, 'Class fee reminder', $text)) {
                    $sent++;
                }
            } else {
                $sent++;
            }
            $row['reminders'][(string) $id] = $when;
        }
        $this->saveRow($row);
        return ['sent' => $sent];
    }

    // ---------------------------------------------------------------- settings
    private function ensureTable(): void
    {
        $this->db->execute('CREATE TABLE IF NOT EXISTS tutor_fee_settings (tutor_id INT NOT NULL PRIMARY KEY, settings LONGTEXT NULL, updated_at DATETIME NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
    }

    private function settingsRow(): array
    {
        $this->ensureTable();
        $raw = $this->db->value('SELECT settings FROM tutor_fee_settings WHERE tutor_id = :t', ['t' => $this->tutorId]);
        $row = is_string($raw) ? (json_decode($raw, true) ?: []) : [];
        return $row + ['settings' => [], 'log' => [], 'reminders' => []];
    }

    private function saveRow(array $row): void
    {
        $this->ensureTable();
        $this->db->execute('INSERT INTO tutor_fee_settings (tutor_id, settings, updated_at) VALUES (:t, :s, NOW()) ON DUPLICATE KEY UPDATE settings = VALUES(settings), updated_at = NOW()',
            ['t' => $this->tutorId, 's' => json_encode($row, JSON_UNESCAPED_UNICODE)]);
    }

    public static function defaults(): array
    {
        return [
            'methods' => ['slip' => true, 'cash' => true, 'card' => false], 'banks' => [],
            'slip_note' => 'Write your Student ID on the slip. Upload a clear photo of the whole slip.',
            'create_day' => 1, 'default_due_day' => 10, 'grace_days' => 5,
            'late_fee' => ['on' => false, 'type' => 'fixed', 'value' => 200, 'after_days' => 5],
            'lock_unpaid' => ['on' => true, 'after_days' => 7],
            'concession_types' => [
                ['id' => 1, 'name' => 'Free card', 'kind' => 'free', 'value' => 100, 'on' => true],
                ['id' => 2, 'name' => 'Half card', 'kind' => 'percent', 'value' => 50, 'on' => true],
                ['id' => 3, 'name' => 'Sibling discount', 'kind' => 'percent', 'value' => 10, 'on' => true],
            ],
            'reminders' => ['before_days' => 2, 'on_due' => true, 'after_days' => 3, 'repeat_days' => 7, 'channel' => 'whatsapp',
                'text' => 'Dear {student}, your {class} fee for {month} (LKR {amount}) is due on {due_date}. Please pay and upload the slip in the ENLIXO app. Thank you, {tutor}.'],
            'receipts' => ['prefix' => 'RC-', 'next' => 1, 'footer' => 'Thank you. Keep this receipt until the end of the year.', 'auto_send' => true],
            'approvers' => ['Owner'],
        ];
    }

    public function settings(): array
    {
        return array_replace_recursive(self::defaults(), $this->settingsRow()['settings']);
    }

    public function settingsPage(): array
    {
        $this->load();
        $counts = array_count_values(array_map(static fn ($e) => $e['class'], array_filter($this->enrolments, static fn ($e) => $e['active'])));
        return ['settings' => $this->settings(),
            'classes' => array_values(array_map(static fn ($c) => $c + ['mode' => '', 'students' => $counts[$c['id']] ?? 0], $this->classes)),
            'log' => $this->settingsRow()['log'], 'reasons' => self::REJECT_REASONS];
    }

    public function saveSettings(array $b, string $by): array
    {
        $row = $this->settingsRow();
        $part = (string) ($b['part'] ?? '');
        if ($part === 'banks' && isset($b['bank'])) {
            $bank = (array) $b['bank'];
            if (trim((string) ($bank['number'] ?? '')) === '') {
                throw HttpException::validation(['number' => 'Enter the account number, for example 8001 2345 6789.']);
            }
            $banks = $this->settings()['banks'];
            $bank['id'] = (int) ($bank['id'] ?? 0) ?: (max([0, ...array_column($banks, 'id')]) + 1);
            $found = false;
            foreach ($banks as $i => $x) {
                if ((int) $x['id'] === $bank['id']) {
                    $banks[$i] = $bank;
                    $found = true;
                }
            }
            if (!$found) {
                $banks[] = $bank;
            }
            $row['settings']['banks'] = array_values($banks);
        }
        if (isset($b['settings']) && is_array($b['settings'])) {
            $allowed = array_intersect_key($b['settings'], self::defaults());
            $row['settings'] = array_replace_recursive($row['settings'], $allowed);
        }
        array_unshift($row['log'], ['when' => date('Y-m-d H:i'), 'by' => $by, 'what' => mb_substr((string) ($b['summary'] ?? 'Settings saved'), 0, 200)]);
        $row['log'] = array_slice($row['log'], 0, 100);
        $this->saveRow($row);
        return ['saved' => true];
    }

    // ---------------------------------------------------------------- plan
    private ?\Closure $has = null;

    public function withPlan(callable $has): self
    {
        $this->has = \Closure::fromCallable($has);
        return $this;
    }

    private function has(string $feature): bool
    {
        return $this->has ? (bool) ($this->has)($feature) : true;
    }
}

<?php
declare(strict_types=1);

namespace Enlixo\Platform;

use Enlixo\Core\Database;
use Enlixo\Core\HttpException;

/**
 * "Try a plan free for 7 days" is a REQUEST: the tutor asks from the Upgrade page, the ENLIXO admin approves it
 * (Admin > Tutors > Trial Requests) and only then does the preview start (PlatformBilling action 'preview').
 * One waiting request per tutor; the same rules as the preview itself are checked when the tutor asks, so they
 * learn at once when a trial is not possible. Table tutor_trial_requests (migration 020).
 */
final class TrialRequests
{
    public const DAYS = 7;

    public function __construct(
        private readonly Database $db,
        private readonly PlatformCatalog $catalog,
        private readonly PlatformBilling $billing,
    ) {
    }

    /** The tutor's waiting request, or null. */
    public function pendingFor(int $tutorId): ?array
    {
        $r = $this->db->one("SELECT request_id, plan_key, created_at FROM tutor_trial_requests WHERE tutor_id = :t AND status = 'pending' ORDER BY request_id DESC LIMIT 1", ['t' => $tutorId]);
        return $r === null ? null : ['id' => (int) $r['request_id'], 'plan' => (string) $r['plan_key'], 'at' => (string) $r['created_at']];
    }

    /** The tutor's last decided request (to show "rejected: reason" on the Upgrade page), or null. */
    public function lastDecidedFor(int $tutorId): ?array
    {
        $r = $this->db->one("SELECT plan_key, status, decision_note, decided_at FROM tutor_trial_requests WHERE tutor_id = :t AND status IN ('approved','rejected') ORDER BY request_id DESC LIMIT 1", ['t' => $tutorId]);
        return $r === null ? null : ['plan' => (string) $r['plan_key'], 'status' => (string) $r['status'], 'note' => (string) ($r['decision_note'] ?? ''), 'at' => (string) $r['decided_at']];
    }

    public function request(int $tutorId, string $planKey, string $note, string $now): array
    {
        $note = trim($note);
        if (mb_strlen($note) > 500) {
            throw new HttpException(422, 'validation_failed', 'Keep the note under 500 characters.', ['note' => 'Up to 500 characters.']);
        }
        $plan = $this->catalog->planByKey($planKey) ?? throw new HttpException(422, 'validation_failed', 'Choose a plan.', ['plan' => 'Choose the plan to try.']);
        $s = $this->db->one('SELECT * FROM users_has_payment_plans WHERE users_user_id = :t ORDER BY users_has_payment_plan_id DESC LIMIT 1', ['t' => $tutorId])
            ?? throw HttpException::notFound('Your subscription was not found.');
        $current = $this->catalog->planById((int) $s['payment_plans_payment_plan_id']);
        if ($plan['plan_key'] === 'pro' || (int) $plan['sort_order'] <= (int) ($current['sort_order'] ?? 0)) {
            throw new HttpException(422, 'validation_failed', 'Choose a bigger plan than yours.', ['plan' => 'Only STARTER or GROW can be tried.']);
        }
        if (!empty($s['preview_used_at'])) {
            throw new HttpException(409, 'conflict', 'You have already tried a plan. Upgrade to keep the features, or talk to ENLIXO.');
        }
        $status = (string) ($s['status'] ?: 'trial');
        if ($status === 'trial' || in_array($status, ['suspended', 'cancelled'], true)) {
            throw new HttpException(409, 'conflict', $status === 'trial' ? 'You are on a free trial already.' : 'Renew your subscription first.');
        }
        if ($this->pendingFor($tutorId) !== null) {
            throw new HttpException(409, 'conflict', 'Your trial request is already with the ENLIXO team. We will let you know soon.');
        }
        $id = $this->db->insert(
            "INSERT INTO tutor_trial_requests (tutor_id, plan_key, note, status, days, created_at) VALUES (:t, :p, :n, 'pending', :d, :at)",
            ['t' => $tutorId, 'p' => $plan['plan_key'], 'n' => $note !== '' ? $note : null, 'd' => self::DAYS, 'at' => $now],
        );
        $this->billing->log($tutorId, 'trial_request', sprintf('Asked to try %s free for %d days', strtoupper((string) $plan['payment_plan']), self::DAYS), $note !== '' ? $note : null, null, ['request' => $id, 'plan' => $plan['plan_key']], $tutorId);
        return ['id' => $id, 'message' => sprintf('Your request to try %s is with the ENLIXO team. The trial starts as soon as they approve it.', strtoupper((string) $plan['payment_plan']))];
    }

    public function cancel(int $tutorId, string $now): void
    {
        $p = $this->pendingFor($tutorId) ?? throw new HttpException(409, 'conflict', 'There is no waiting trial request.');
        $this->db->execute("UPDATE tutor_trial_requests SET status = 'cancelled', decided_at = :at, decided_by = :t WHERE request_id = :id", ['at' => $now, 't' => $tutorId, 'id' => $p['id']]);
    }

    /** Every request with the tutor's name and plan, newest first, plus counts (Admin > Trial Requests). */
    public function list(): array
    {
        $rows = $this->db->all(
            "SELECT r.*, CONCAT(u.user_fname, ' ', u.user_lname) AS tutor_name, u.user_email AS email, u.user_mobile AS mobile,
                    pp.plan_key AS current_plan, s.status AS sub_status, s.preview_used_at,
                    CONCAT(a.user_fname, ' ', a.user_lname) AS decided_by_name
               FROM tutor_trial_requests r
               JOIN users u ON u.user_id = r.tutor_id
               LEFT JOIN users_has_payment_plans s ON s.users_has_payment_plan_id = (SELECT MAX(x.users_has_payment_plan_id) FROM users_has_payment_plans x WHERE x.users_user_id = r.tutor_id)
               LEFT JOIN payment_plans pp ON pp.payment_plan_id = s.payment_plans_payment_plan_id
               LEFT JOIN users a ON a.user_id = r.decided_by
              ORDER BY r.status = 'pending' DESC, r.request_id DESC",
        );
        $out = [];
        $counts = ['pending' => 0, 'approved' => 0, 'rejected' => 0, 'cancelled' => 0];
        foreach ($rows as $r) {
            $counts[$r['status']] = ($counts[$r['status']] ?? 0) + 1;
            $out[] = [
                'id' => (int) $r['request_id'], 'tutor_id' => (int) $r['tutor_id'], 'tutor' => trim((string) $r['tutor_name']),
                'email' => (string) ($r['email'] ?? ''), 'mobile' => (string) ($r['mobile'] ?? ''),
                'plan' => (string) $r['plan_key'], 'current_plan' => (string) ($r['current_plan'] ?? ''), 'sub_status' => (string) ($r['sub_status'] ?? ''),
                'note' => (string) ($r['note'] ?? ''), 'status' => (string) $r['status'], 'days' => (int) $r['days'],
                'requested_at' => (string) $r['created_at'], 'decided_at' => (string) ($r['decided_at'] ?? ''),
                'decided_by' => trim((string) ($r['decided_by_name'] ?? '')), 'decision_note' => (string) ($r['decision_note'] ?? ''),
            ];
        }
        return ['data' => $out, 'counts' => $counts];
    }

    /** Approve: the preview starts now for `days` (1 - 14). Reject: a reason is required, the tutor sees it. */
    public function decide(int $requestId, bool $approve, string $note, int $days, int $adminId, string $now): string
    {
        $note = trim($note);
        $r = $this->db->one('SELECT * FROM tutor_trial_requests WHERE request_id = :id', ['id' => $requestId]) ?? throw HttpException::notFound('This request was not found.');
        if ($r['status'] !== 'pending') {
            throw new HttpException(409, 'conflict', 'This request was already ' . $r['status'] . '.');
        }
        if (!$approve && ($note === '' || mb_strlen($note) > 500)) {
            throw new HttpException(422, 'validation_failed', 'Give a reason.', ['note' => 'Say why, up to 500 characters. The tutor sees it.']);
        }
        $msg = 'Request rejected. The tutor sees your reason on the Upgrade page.';
        if ($approve) {
            $days = max(1, min(14, $days ?: self::DAYS));
            // the admin approval is the reason, so the preview is allowed even when the tutor's earlier request used it up
            $msg = $this->billing->action((int) $r['tutor_id'], 'preview', ['plan' => $r['plan_key'], 'days' => $days, 'reason' => 'Trial request #' . $requestId . ' approved' . ($note !== '' ? ': ' . $note : '')], $adminId);
        }
        $this->db->execute(
            'UPDATE tutor_trial_requests SET status = :s, decided_by = :by, decided_at = :at, decision_note = :n, days = :d WHERE request_id = :id',
            ['s' => $approve ? 'approved' : 'rejected', 'by' => $adminId, 'at' => $now, 'n' => $note !== '' ? $note : null, 'd' => $approve ? $days : (int) $r['days'], 'id' => $requestId],
        );
        if (!$approve) {
            $this->billing->log((int) $r['tutor_id'], 'trial_request', 'Trial request rejected', $note, null, ['request' => $requestId], $adminId);
        }
        return $msg;
    }
}

<?php
declare(strict_types=1);

namespace Enlixo\Http\Controllers;

use Enlixo\App;
use Enlixo\Core\AuthContext;
use Enlixo\Core\JsonResponse;
use Enlixo\Core\Request;
use Enlixo\Fees\TutorFees;

/**
 * Student Fees API (tutor/fees.php, fee-details, record-payment, fee-receipt, student-fees, fee-concession,
 * fee-settings). Same contract the pages were built on (assets/js/enlixo/mock/FeesMock.js); data in src/Fees/TutorFees.php.
 * Permissions: payments.view to read, payments.approve to check slips and record payments, fees.manage for free cards /
 * discounts (+ PRO feature fees.advanced) and settings, messages.send for reminders (WhatsApp sending: comms.whatsapp).
 */
final class FeeController
{
    public function __construct(private readonly App $app)
    {
    }

    private function fees(Request $r, string $permission): TutorFees
    {
        $auth = $r->auth();
        $this->app->permissions()->require($auth, $permission);
        $tutor = $auth->tenantId();
        $ent = $this->app->entitlements();
        $today = (new \DateTimeImmutable('now', new \DateTimeZone('Asia/Colombo')))->format('Y-m-d');
        return (new TutorFees($this->app->db(), $tutor, $auth->userId, $today))->withPlan(static fn (string $f) => $ent->has($tutor, $f));
    }

    private static function paged(array $p): JsonResponse
    {
        return JsonResponse::ok($p['data'], $p['meta']);
    }

    public function overview(Request $r): JsonResponse
    {
        $month = (string) ($r->query['month'] ?? '');
        $f = $this->fees($r, 'payments.view');
        return JsonResponse::ok($f->overview(preg_match('/^\d{4}-\d{2}$/', $month) ? $month : date('Y-m'), ((int) ($r->query['class'] ?? 0)) ?: null));
    }

    public function index(Request $r): JsonResponse { return self::paged($this->fees($r, 'payments.view')->list($r->query)); }
    public function slips(Request $r): JsonResponse { return self::paged($this->fees($r, 'payments.view')->slips($r->query)); }
    public function unpaid(Request $r): JsonResponse { return self::paged($this->fees($r, 'payments.view')->unpaid($r->query)); }
    public function receipts(Request $r): JsonResponse { return self::paged($this->fees($r, 'payments.view')->receipts($r->query)); }
    public function receipt(Request $r): JsonResponse { return JsonResponse::ok($this->fees($r, 'payments.view')->receipt((int) $r->param('id'))); }
    public function show(Request $r): JsonResponse { return JsonResponse::ok($this->fees($r, 'payments.view')->detail((int) $r->param('id'))); }
    public function students(Request $r): JsonResponse { return JsonResponse::ok($this->fees($r, 'payments.view')->studentsList()); }
    public function open(Request $r): JsonResponse { return JsonResponse::ok($this->fees($r, 'payments.view')->openFor((int) $r->param('id'))); }
    public function card(Request $r): JsonResponse { return JsonResponse::ok($this->fees($r, 'payments.view')->card((int) $r->param('id'))); }

    public function approve(Request $r): JsonResponse { return JsonResponse::ok($this->fees($r, 'payments.approve')->decide((int) $r->param('id'), 'approve', $r->body)); }
    public function reject(Request $r): JsonResponse { return JsonResponse::ok($this->fees($r, 'payments.approve')->decide((int) $r->param('id'), 'reject', $r->body)); }
    public function record(Request $r): JsonResponse { return JsonResponse::ok($this->fees($r, 'payments.approve')->record($r->body), [], 201); }

    public function concede(Request $r): JsonResponse
    {
        $f = $this->fees($r, 'fees.manage');
        $this->app->entitlements()->require($r->auth()->tenantId(), 'fees.advanced');
        return JsonResponse::ok($f->concede($r->body));
    }

    public function remind(Request $r): JsonResponse
    {
        $f = $this->fees($r, 'messages.send');
        $auth = $r->auth();
        $send = null;
        if (($r->body['channel'] ?? 'link') !== 'link') {
            $this->app->entitlements()->require($auth->tenantId(), 'comms.whatsapp');
            $msg = $this->app->messages();
            $send = static fn (array $s, string $subject, string $text) => (bool) ($msg->notify($auth->tenantId(), $s['whatsapp'] ?: null, $s['email'] ?: null, $s['name'], $subject, $text)['ok'] ?? false);
        }
        return JsonResponse::ok($f->remind($r->body, $send));
    }

    public function settings(Request $r): JsonResponse { return JsonResponse::ok($this->fees($r, 'payments.view')->settingsPage()); }

    public function saveSettings(Request $r): JsonResponse
    {
        $f = $this->fees($r, 'fees.manage');
        return JsonResponse::ok($f->saveSettings($r->body, $this->who($r->auth())));
    }

    private function who(AuthContext $auth): string
    {
        if ($auth->isTutorOwner()) {
            return 'You';
        }
        $u = $this->app->db()->one('SELECT user_fname, user_lname FROM users WHERE user_id = :u', ['u' => $auth->userId]);
        return $u ? trim($u['user_fname'] . ' ' . $u['user_lname']) : 'Staff';
    }
}

<?php
declare(strict_types=1);

namespace Enlixo\Http\Controllers;

use Enlixo\App;
use Enlixo\Core\FileResponse;
use Enlixo\Core\HttpException;
use Enlixo\Core\JsonResponse;
use Enlixo\Core\Request;
use Enlixo\Core\AuthContext;

/**
 * Admin side (platform owner) and the tutor's My Subscription page: /api/v1/admin/* and /api/v1/tutor/subscription*.
 * Admin routes need the admin login (admin/ pages); the tutor routes need the tutor owner (staff never see the bill).
 */
final class PlatformController
{
    public function __construct(private readonly App $app)
    {
    }

    private function admin(Request $r): AuthContext
    {
        $auth = $r->auth();
        if ($auth->kind !== 'admin') {
            throw HttpException::forbidden('Only the ENLIXO admin team can do this.');
        }
        return $auth;
    }

    private function tutorOwner(Request $r): AuthContext
    {
        $auth = $r->auth();
        if (!$auth->isTutorOwner()) {
            throw HttpException::forbidden('Only the tutor who owns this account can see the subscription.');
        }
        return $auth;
    }

    /** Search, filters, sort and pages over a list that is already in memory (a few hundred tutors / invoices). */
    private static function page(array $rows, Request $r, array $filters, callable $search): JsonResponse
    {
        foreach ($filters as $key => $field) {
            $want = (string) ($r->query[$key] ?? '');
            if ($want !== '') {
                $rows = array_values(array_filter($rows, static fn ($x) => strtolower((string) $x[$field]) === strtolower($want)));
            }
        }
        $q = mb_strtolower(trim((string) ($r->query['q'] ?? '')));
        if ($q !== '') {
            $rows = array_values(array_filter($rows, static fn ($x) => str_contains(mb_strtolower($search($x)), $q)));
        }
        $sort = (string) ($r->query['sort'] ?? '');
        if ($sort !== '' && $rows !== [] && array_key_exists($sort, $rows[0])) {
            $dir = ($r->query['dir'] ?? 'asc') === 'desc' ? -1 : 1;
            usort($rows, static fn ($a, $b) => $dir * ($a[$sort] <=> $b[$sort]));
        }
        $per = max(1, min(1000, (int) ($r->query['per_page'] ?? 10)));
        $page = max(1, (int) ($r->query['page'] ?? 1));
        $total = count($rows);
        return JsonResponse::ok(array_slice($rows, ($page - 1) * $per, $per), ['total' => $total, 'page' => $page, 'per_page' => $per, 'pages' => (int) max(1, ceil($total / $per))]);
    }

    // ------------------------------------------------------------ admin

    public function dashboard(Request $r): JsonResponse
    {
        $this->admin($r);
        return JsonResponse::ok($this->app->platformDashboard()->overview());
    }

    public function tutors(Request $r): JsonResponse
    {
        $this->admin($r);
        return self::page($this->app->platformBilling()->tutors(), $r, ['status' => 'status', 'plan' => 'plan', 'cycle' => 'cycle'],
            static fn ($t) => "{$t['name']} {$t['brand']} {$t['email']} {$t['mobile']}");
    }

    public function addTutor(Request $r): JsonResponse
    {
        $auth = $this->admin($r);
        $id = $this->app->platformBilling()->addTutor($r->body, $auth->userId);
        return JsonResponse::ok(['id' => $id], [], 201);
    }

    public function tutor(Request $r): JsonResponse
    {
        $this->admin($r);
        return JsonResponse::ok($this->app->platformBilling()->account((int) $r->param('id'), false));
    }

    /** POST /admin/tutors/{id}/{extend|plan|packs|price|suspend|reactivate|cancel|remind} */
    public function tutorAction(Request $r): JsonResponse
    {
        $auth = $this->admin($r);
        $id = (int) $r->param('id');
        $billing = $this->app->platformBilling();
        $msg = $billing->action($id, $r->param('action'), $r->body, $auth->userId);
        $sent = null;
        if (str_starts_with($msg, 'remind:')) {
            $open = json_decode(substr($msg, 7), true);
            $t = $billing->tutor($id);
            $sent = self::mail($t['email'], $t['name'], 'Payment reminder: ' . $open['invoice_number'], self::reminderHtml($t['name'], $open));
            $msg = $sent ? "Reminder e-mailed to {$t['name']}." : "The e-mail could not be sent. The reminder is in {$t['name']}'s history; call or WhatsApp them.";
        }
        return JsonResponse::ok(['message' => $msg, 'sent' => $sent]);
    }

    public function invoices(Request $r): JsonResponse
    {
        $this->admin($r);
        $rows = $this->app->platformBilling()->invoices(isset($r->query['tutor']) ? (int) $r->query['tutor'] : null);
        foreach ($rows as &$i) {
            $i['plan_key'] = strtolower($i['plan']);
        }
        unset($i);
        return self::page($rows, $r, ['status' => 'status', 'plan' => 'plan_key'], static fn ($i) => "{$i['number']} {$i['tutor']} {$i['brand']}");
    }

    public function recordPayment(Request $r): JsonResponse
    {
        $auth = $this->admin($r);
        return JsonResponse::ok($this->app->platformBilling()->recordPayment((int) $r->param('id'), $r->body, $auth->userId));
    }

    public function voidInvoice(Request $r): JsonResponse
    {
        $auth = $this->admin($r);
        $this->app->platformBilling()->voidInvoice((int) $r->param('id'), (string) $r->input('reason', ''), $auth->userId);
        return JsonResponse::ok(['void' => true]);
    }

    public function payments(Request $r): JsonResponse
    {
        $this->admin($r);
        return self::page($this->app->platformBilling()->payments(isset($r->query['tutor']) ? (int) $r->query['tutor'] : null), $r,
            ['status' => 'status', 'method' => 'method'], static fn ($p) => "{$p['ref']} {$p['tutor']} {$p['invoice']} {$p['reference']}");
    }

    public function approve(Request $r): JsonResponse
    {
        $auth = $this->admin($r);
        return JsonResponse::ok($this->app->platformBilling()->approve((int) $r->param('id'), $auth->userId));
    }

    public function reject(Request $r): JsonResponse
    {
        $auth = $this->admin($r);
        return JsonResponse::ok($this->app->platformBilling()->reject((int) $r->param('id'), (string) $r->input('reason', ''), (string) $r->input('note', ''), $auth->userId));
    }

    public function adminSlip(Request $r): FileResponse
    {
        $this->admin($r);
        $f = $this->app->platformBilling()->slipFile((int) $r->param('id'));
        return new FileResponse((string) file_get_contents($f['path']), $f['name'], $f['type'], true);
    }

    public function plans(Request $r): JsonResponse
    {
        $this->admin($r);
        $counts = [];
        foreach ($this->app->platformBilling()->tutors() as $t) {
            if (in_array($t['status'], ['active', 'due', 'overdue'], true)) {
                $counts[$t['plan']] = ($counts[$t['plan']] ?? 0) + 1;
            }
        }
        return JsonResponse::ok([
            'plans' => $this->app->platformCatalog()->plans($counts), 'addons' => $this->app->platformCatalog()->addons(),
            'rules' => $this->app->platformBilling()->publicRules(), 'banks' => $this->app->platformSettings()->banks(),
        ]);
    }

    public function lookups(Request $r): JsonResponse
    {
        $this->admin($r);
        return JsonResponse::ok([
            'plans' => $this->app->platformCatalog()->plans(), 'addons' => $this->app->platformCatalog()->addons(), 'rules' => $this->app->platformBilling()->publicRules(),
            'titles' => array_map(static fn ($t) => ['id' => (int) $t['title_id'], 'name' => $t['title']], $this->app->db()->all('SELECT title_id, title FROM title ORDER BY title_id')),
        ]);
    }

    public function savePlan(Request $r): JsonResponse
    {
        $auth = $this->admin($r);
        [$before, $after] = $this->app->platformCatalog()->savePlan($r->param('key'), $r->body);
        $this->app->platformBilling()->log(null, 'plan_price', 'Plan ' . strtoupper((string) $after['payment_plan']) . ' changed', null, $before, $after, $auth->userId);
        return JsonResponse::ok(['plans' => $this->app->platformCatalog()->plans()]);
    }

    public function saveAddon(Request $r): JsonResponse
    {
        $auth = $this->admin($r);
        [$before, $after] = $this->app->platformCatalog()->saveAddon($r->param('key'), $r->body);
        $this->app->platformBilling()->log(null, 'addon', 'Add-on ' . $after['name'] . ' changed', null, $before, $after, $auth->userId);
        return JsonResponse::ok(['addons' => $this->app->platformCatalog()->addons()]);
    }

    public function saveRules(Request $r): JsonResponse
    {
        $auth = $this->admin($r);
        return JsonResponse::ok(['rules' => $this->app->platformBilling()->saveRules($r->body, $auth->userId)]);
    }

    public function saveBank(Request $r): JsonResponse
    {
        $auth = $this->admin($r);
        $id = isset($r->routeParams['id']) ? (int) $r->routeParams['id'] : null;
        return JsonResponse::ok(['banks' => $this->app->platformBilling()->saveBank($id, $r->body, $auth->userId)]);
    }

    public function deleteBank(Request $r): JsonResponse
    {
        $auth = $this->admin($r);
        return JsonResponse::ok(['banks' => $this->app->platformBilling()->deleteBank((int) $r->param('id'), $auth->userId)]);
    }

    // ------------------------------------------------------------ tutor

    public function mySubscription(Request $r): JsonResponse
    {
        $auth = $this->tutorOwner($r);
        return JsonResponse::ok($this->app->platformBilling()->account($auth->userId, true));
    }

    /** POST /tutor/subscription/payments (multipart: invoice, date, reference, slip) */
    public function uploadSlip(Request $r): JsonResponse
    {
        $auth = $this->tutorOwner($r);
        $file = $r->files['slip'] ?? null;
        return JsonResponse::ok($this->app->platformBilling()->uploadSlip($auth->userId, $r->body, is_array($file) && !is_array($file['name'] ?? null) ? $file : null), [], 201);
    }

    public function changeMyPlan(Request $r): JsonResponse
    {
        $auth = $this->tutorOwner($r);
        return JsonResponse::ok(['message' => $this->app->platformBilling()->action($auth->userId, 'plan', ['plan' => $r->input('plan')], $auth->userId, true)]);
    }

    public function mySlip(Request $r): FileResponse
    {
        $auth = $this->tutorOwner($r);
        $f = $this->app->platformBilling()->slipFile((int) $r->param('id'), $auth->userId);
        return new FileResponse((string) file_get_contents($f['path']), $f['name'], $f['type'], true);
    }

    // ------------------------------------------------------------ e-mail (the legacy PHPMailer setup)

    private static function mail(string $to, string $name, string $subject, string $html): bool
    {
        $root = dirname(__DIR__, 3);
        try {
            require_once $root . '/includes/Project.php';
            if (!function_exists('sendMail')) {
                $cwd = getcwd();
                chdir($root . '/includes/phpmailer');
                try {
                    require_once 'mail-process.php';
                } finally {
                    chdir($cwd ?: $root);
                }
            }
            ob_start();
            try {
                return (bool) \sendMail($to, $name, $subject, $html);
            } finally {
                ob_end_clean();
            }
        } catch (\Throwable $e) {
            error_log('Reminder e-mail failed: ' . $e->getMessage());
            return false;
        }
    }

    private static function reminderHtml(string $name, array $open): string
    {
        $e = static fn ($s) => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
        return '<div style="font-family:Arial,sans-serif;font-size:15px;color:#1f2937;line-height:1.6">'
            . '<p>Dear ' . $e($name) . ',</p>'
            . '<p>This is a friendly reminder that invoice <b>' . $e($open['invoice_number']) . '</b> for <b>LKR ' . $e(number_format((float) $open['left_to_pay'], 2)) . '</b> is due on <b>' . $e($open['due_on']) . '</b>.</p>'
            . '<p>Sign in to ENLIXO Tutor and open <b>Finance &rarr; My Subscription</b> to see the bank accounts and upload your payment slip.</p>'
            . '<p>Thank you,<br>The ENLIXO team</p></div>';
    }
}

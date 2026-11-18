<?php
declare(strict_types=1);

namespace Enlixo\Http\Controllers;

use Enlixo\App;
use Enlixo\Core\AuthContext;
use Enlixo\Core\HttpException;
use Enlixo\Core\JsonResponse;
use Enlixo\Core\Request;

/**
 * Online tutor sign-up.
 *   GET  /api/v1/public/tutor-signup                 plans, comparison, add-ons, titles, districts (no login)
 *   POST /api/v1/public/tutor-signup                 send a request (no login)
 *   GET  /api/v1/admin/tutor-requests                every request with counts
 *   GET  /api/v1/admin/tutor-requests/{id}           one request with its history
 *   POST /api/v1/admin/tutor-requests/{id}/approve   body = the Add Tutor form → makes the tutor
 *   POST /api/v1/admin/tutor-requests/{id}/reject    {reason}
 *   POST /api/v1/admin/tutor-requests/{id}/reopen
 *   POST /api/v1/admin/tutor-requests/{id}/note      {note, called}
 */
final class TutorRequestController
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

    public function info(Request $r): JsonResponse
    {
        return JsonResponse::ok($this->app->tutorRequests()->signupInfo());
    }

    public function submit(Request $r): JsonResponse
    {
        $now = (new \DateTimeImmutable('now', new \DateTimeZone($this->app->config['app']['timezone'])))->format('Y-m-d H:i:s');
        // JSON, or multipart with `data` (the form as JSON) + `slip` (the bank slip of the first payment)
        $in = isset($r->body['data']) && is_string($r->body['data']) ? (json_decode($r->body['data'], true) ?: []) : $r->body;
        $slip = $r->files['slip'] ?? null;
        $res = $this->app->tutorRequests()->submit($in, $r->ip, $now, is_array($slip) && !is_array($slip['name'] ?? null) ? $slip : null);
        if (isset($res['id'])) {
            $name = trim((string) ($in['name'] ?? $in['first_name'] ?? ''));
            PlatformController::mail(strtolower(trim((string) ($in['email'] ?? ''))), $name, 'We received your ENLIXO Tutor request ' . $res['ref'], self::receivedHtml($name, $res['ref']));
        }
        return JsonResponse::ok(['ref' => $res['ref']], [], 201);
    }

    /** GET /admin/tutor-requests/{id}/slip: the bank slip sent with the sign-up */
    public function slip(Request $r): \Enlixo\Core\FileResponse
    {
        $this->admin($r);
        $f = $this->app->tutorRequests()->slipFile((int) $r->param('id'));
        return new \Enlixo\Core\FileResponse((string) file_get_contents($f['path']), $f['name'], $f['type'], true);
    }

    public function index(Request $r): JsonResponse
    {
        $this->admin($r);
        return JsonResponse::ok($this->app->tutorRequests()->list());
    }

    public function show(Request $r): JsonResponse
    {
        $this->admin($r);
        return JsonResponse::ok($this->app->tutorRequests()->one((int) $r->param('id')));
    }

    public function approve(Request $r): JsonResponse
    {
        $auth = $this->admin($r);
        $id = (int) $r->param('id');
        $tutorId = $this->app->tutorRequests()->approve($id, $r->body, $auth->userId, $this->app->platformBilling());
        $req = $this->app->tutorRequests()->one($id);
        try { // the tutor is made: a mail problem must not turn the answer into an error
            $welcome = $this->app->tutorWelcome()->send($tutorId, PlatformController::baseUrl($r));
        } catch (\Throwable $e) {
            error_log('Tutor welcome mail failed for user ' . $tutorId . ': ' . $e->getMessage());
            $welcome = ['sent' => false, 'link' => '', 'email' => ''];
        }
        return JsonResponse::ok(['id' => $tutorId, 'request' => $req, 'mailed' => $welcome['sent'], 'welcome' => $welcome], [], 201);
    }

    public function reject(Request $r): JsonResponse
    {
        $auth = $this->admin($r);
        $this->app->tutorRequests()->reject((int) $r->param('id'), (string) ($r->body['reason'] ?? ''), $auth->userId);
        return JsonResponse::ok($this->app->tutorRequests()->one((int) $r->param('id')));
    }

    public function note(Request $r): JsonResponse
    {
        $auth = $this->admin($r);
        $this->app->tutorRequests()->note((int) $r->param('id'), (string) ($r->body['note'] ?? ''), !empty($r->body['called']), $auth->userId);
        return JsonResponse::ok($this->app->tutorRequests()->one((int) $r->param('id')));
    }

    public function reopen(Request $r): JsonResponse
    {
        $auth = $this->admin($r);
        $this->app->tutorRequests()->reopen((int) $r->param('id'), $auth->userId);
        return JsonResponse::ok($this->app->tutorRequests()->one((int) $r->param('id')));
    }

    private static function esc(string $s): string
    {
        return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
    }

    private static function receivedHtml(string $name, string $ref): string
    {
        return '<div style="font-family:Arial,sans-serif;font-size:14px;color:#1f2937;line-height:1.6">'
            . '<p>Hi ' . self::esc($name) . ',</p>'
            . '<p>Thank you for your interest in ENLIXO Tutor. We received your request <b>' . self::esc($ref) . '</b>.</p>'
            . '<p>Our team will call or WhatsApp you within one working day to set up your account and start your free trial.</p>'
            . '<p>ENLIXO Tutor</p></div>';
    }

}

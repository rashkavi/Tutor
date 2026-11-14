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
        $res = $this->app->tutorRequests()->submit($r->body, $r->ip, $now);
        if (isset($res['id'])) {
            $name = trim((string) ($r->body['first_name'] ?? ''));
            PlatformController::mail(strtolower(trim((string) $r->body['email'])), $name, 'We received your ENLIXO Tutor request ' . $res['ref'], self::receivedHtml($name, $res['ref']));
        }
        return JsonResponse::ok(['ref' => $res['ref']], [], 201);
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
        $sent = PlatformController::mail((string) $r->body['email'], (string) $r->body['first_name'], 'Welcome to ENLIXO Tutor', self::welcomeHtml((string) $r->body['first_name'], (string) $r->body['email']));
        return JsonResponse::ok(['id' => $tutorId, 'request' => $req, 'mailed' => $sent], [], 201);
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

    private static function welcomeHtml(string $name, string $email): string
    {
        $url = rtrim((string) (class_exists('Project') ? \Project::getBaseUrl() : ''), '/') . '/tutor/';
        return '<div style="font-family:Arial,sans-serif;font-size:14px;color:#1f2937;line-height:1.6">'
            . '<p>Hi ' . self::esc($name) . ',</p>'
            . '<p>Your ENLIXO Tutor account is ready.</p>'
            . '<p>Sign in at <a href="' . self::esc($url) . '">' . self::esc($url) . '</a> with <b>' . self::esc($email) . '</b>. '
            . 'The first time, the sign-in page sends a code to this e-mail address and asks you to set your password.</p>'
            . '<p>ENLIXO Tutor</p></div>';
    }
}

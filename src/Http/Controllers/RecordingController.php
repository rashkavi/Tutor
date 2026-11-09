<?php
declare(strict_types=1);

namespace Enlixo\Http\Controllers;

use Enlixo\App;
use Enlixo\Core\HttpException;
use Enlixo\Core\JsonResponse;
use Enlixo\Core\Request;

/** Student player endpoints: status, start, heartbeat. */
final class RecordingController
{
    public function __construct(private readonly App $app)
    {
    }

    public function status(Request $r): JsonResponse
    {
        $s = $this->app->recordings()->status($this->studentId($r), $r->param('kind'), (int) $r->param('id'));
        return JsonResponse::ok($s->toArray() + ['message' => $s->canPlay() ? null : $this->app->recordings()->lockedMessage($s)]);
    }

    public function start(Request $r): JsonResponse
    {
        return JsonResponse::ok($this->app->recordings()->start($this->studentId($r), $r->param('kind'), (int) $r->param('id'), $r->ip, $r->header('user-agent')));
    }

    public function heartbeat(Request $r): JsonResponse
    {
        $token = (string) $r->input('session_token', '');
        if (!preg_match('/^[a-f0-9]{40}$/', $token)) {
            throw HttpException::validation(['session_token' => 'Invalid session.']);
        }
        $svc = $this->app->recordings();
        $s = $svc->heartbeat($this->studentId($r), $token, (int) $r->input('position', 0), (bool) $r->input('resume', false));
        return JsonResponse::ok($s->toArray() + ['message' => $s->canPlay() ? null : $svc->lockedMessage($s)]);
    }

    private function studentId(Request $r): int
    {
        return $r->auth()->studentId ?? throw HttpException::forbidden('Only students can watch recordings here.');
    }
}

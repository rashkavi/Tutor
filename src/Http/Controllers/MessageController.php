<?php
declare(strict_types=1);

namespace Enlixo\Http\Controllers;

use Enlixo\App;
use Enlixo\Core\JsonResponse;
use Enlixo\Core\Request;
use Enlixo\Messaging\MessageService;

/**
 * Message Center (tutor/message-center.php, tutor/whatsapp-settings.php), permission messages.send.
 *
 *   GET  /api/v1/tutor/messages/setup               classes, e-mail / WhatsApp availability, how WhatsApp is set up
 *   POST /api/v1/tutor/messages/preview             {channel, audience, classes[]} -> who would get it
 *   POST /api/v1/tutor/messages                     {channel, audience, classes[], subject, body} -> the message + its people
 *   GET  /api/v1/tutor/messages?page=               history
 *   GET  /api/v1/tutor/messages/{id}                one message with every person and status
 *   POST /api/v1/tutor/messages/{id}/send           sends the next batch (e-mail, WhatsApp ENLIXO / own number)
 *   POST /api/v1/tutor/messages/{id}/retry          failed ones back to pending
 *   POST /api/v1/tutor/messages/{id}/recipients/{rid}/sent   "From my phone": WhatsApp was opened for this person
 *   POST /api/v1/tutor/whatsapp/mode                {mode: phone | enlixo | own}
 *   POST /api/v1/tutor/whatsapp/connect             own number by hand {phone_number_id, business_account_id, token, template, lang}
 *   POST /api/v1/tutor/whatsapp/embedded            own number with Facebook {code, phone_number_id, business_account_id}
 *   POST /api/v1/tutor/whatsapp/test                {to}
 *   POST /api/v1/tutor/whatsapp/disconnect
 */
final class MessageController
{
    public function __construct(private readonly App $app)
    {
    }

    private function tutor(Request $r): array
    {
        $auth = $r->auth();
        $this->app->permissions()->require($auth, 'messages.send');
        return [$auth->tenantId(), $auth->userId];
    }

    private function svc(): MessageService
    {
        return $this->app->messages();
    }

    public function setup(Request $r): JsonResponse
    {
        [$t] = $this->tutor($r);
        return JsonResponse::ok($this->svc()->setup($t) + ['classes' => $this->svc()->classes($t)]);
    }

    public function preview(Request $r): JsonResponse
    {
        [$t] = $this->tutor($r);
        return JsonResponse::ok($this->svc()->preview($t, $r->body));
    }

    public function store(Request $r): JsonResponse
    {
        [$t, $u] = $this->tutor($r);
        return JsonResponse::ok($this->svc()->create($t, $u, $r->body), [], 201);
    }

    public function index(Request $r): JsonResponse
    {
        [$t] = $this->tutor($r);
        $h = $this->svc()->history($t, max(1, (int) ($r->query['page'] ?? 1)), min(50, max(5, (int) ($r->query['per_page'] ?? 10))));
        return JsonResponse::ok($h['rows'], $h['meta'] + ['totals' => $h['totals']]);
    }

    public function show(Request $r): JsonResponse
    {
        [$t] = $this->tutor($r);
        return JsonResponse::ok($this->svc()->detail($t, (int) $r->param('id')));
    }

    public function send(Request $r): JsonResponse
    {
        [$t] = $this->tutor($r);
        return JsonResponse::ok($this->svc()->sendBatch($t, (int) $r->param('id')));
    }

    public function retry(Request $r): JsonResponse
    {
        [$t] = $this->tutor($r);
        return JsonResponse::ok($this->svc()->retry($t, (int) $r->param('id')));
    }

    public function markSent(Request $r): JsonResponse
    {
        [$t] = $this->tutor($r);
        return JsonResponse::ok($this->svc()->markPhoneSent($t, (int) $r->param('id'), (int) $r->param('rid')));
    }

    public function mode(Request $r): JsonResponse
    {
        [$t] = $this->tutor($r);
        return JsonResponse::ok($this->svc()->setMode($t, (string) ($r->body['mode'] ?? '')));
    }

    public function connect(Request $r): JsonResponse
    {
        [$t] = $this->tutor($r);
        return JsonResponse::ok($this->svc()->connectManual($t, $r->body));
    }

    public function embedded(Request $r): JsonResponse
    {
        [$t] = $this->tutor($r);
        return JsonResponse::ok($this->svc()->connectEmbedded($t, $r->body));
    }

    public function test(Request $r): JsonResponse
    {
        [$t] = $this->tutor($r);
        return JsonResponse::ok($this->svc()->test($t, (string) ($r->body['to'] ?? '')));
    }

    public function disconnect(Request $r): JsonResponse
    {
        [$t] = $this->tutor($r);
        return JsonResponse::ok($this->svc()->disconnect($t));
    }
}

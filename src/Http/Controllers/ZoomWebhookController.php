<?php
declare(strict_types=1);

namespace Enlixo\Http\Controllers;

use Enlixo\App;
use Enlixo\Core\HttpException;
use Enlixo\Core\JsonResponse;
use Enlixo\Core\Request;

/** POST /api/v1/webhooks/zoom */
final class ZoomWebhookController
{
    public function __construct(private readonly App $app)
    {
    }

    public function handle(Request $r): JsonResponse
    {
        $verifier = $this->app->zoomWebhooks();
        if (!$verifier->isValid($r->header('x-zm-signature'), $r->header('x-zm-request-timestamp'), $r->rawBody)) {
            throw new HttpException(401, 'bad_signature', 'Invalid webhook signature.');
        }
        $event = $r->body['event'] ?? '';
        if ($event === 'endpoint.url_validation') {
            return new JsonResponse($verifier->validationResponse((string) ($r->body['payload']['plainToken'] ?? '')));
        }

        // Log first, process later (plan 6.5 / 7.2): attendance + recording.completed.
        $this->app->db()->execute(
            'INSERT INTO integration_webhook_logs (provider, event, payload) VALUES ("zoom", :e, :p)',
            ['e' => mb_substr((string) $event, 0, 80), 'p' => $r->rawBody],
        );
        return JsonResponse::ok(['received' => true]);
    }
}

<?php
declare(strict_types=1);

if (PHP_VERSION_ID < 80100) {
    http_response_code(503);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'error' => ['code' => 'setup_php', 'message' => 'The new pages need PHP 8.1 or newer. This server runs PHP ' . PHP_VERSION . '.', 'details' => new stdClass()]]);
    exit;
}

// Front controller for /api/v1/* (see .htaccess in this folder).
use Enlixo\App;
use Enlixo\Core\HttpException;
use Enlixo\Core\Request;
use Enlixo\Core\Router;
use Enlixo\Http\Auth\LegacySessionAuthenticator;

$root = dirname(__DIR__, 2);
$config = require $root . '/src/bootstrap.php';

$request = Request::fromGlobals();
$app = new App($config, $request->ip);
$router = new Router();

// Webhooks are public (they verify their own signatures) and so is the student application form (/api/v1/public/,
// found by its link token); everything else uses the existing login session.
$authenticator = new LegacySessionAuthenticator($root . '/includes/UserClass.php');
$router->use(static function (Request $r, callable $next) use ($authenticator, $app) {
    if (!str_contains($r->path, '/api/v1/webhooks/')) {
        $r->auth = str_contains($r->path, '/api/v1/public/') ? null : $authenticator->authenticate($r);
        // CSRF: the session cookie rides along on cross-site form posts, but a custom header cannot be sent
        // cross-site without a CORS preflight (which we never allow). ApiClient always sends it.
        if (!in_array($r->method, ['GET', 'HEAD', 'OPTIONS'], true) && $r->header('X-Requested-With') !== 'enlixo') {
            throw new HttpException(403, 'csrf', 'Request blocked. Please reload the page and try again.');
        }
        // A suspended or cancelled tutor can still sign in, see My Subscription and pay; everything else waits.
        if ($r->auth?->kind === 'tutor' && !str_starts_with($r->path, '/api/v1/tutor/subscription') && $r->path !== '/api/v1/me/access') {
            $status = $app->platformBilling()->accessStatus((int) $r->auth->tutorId);
            if ($status === 'suspended' || $status === 'cancelled') {
                throw new HttpException(402, 'subscription_' . $status, $status === 'suspended'
                    ? 'Your ENLIXO subscription is paused because an invoice is not paid. Open Finance → My Subscription to pay and switch everything back on.'
                    : 'Your ENLIXO subscription has ended. Open Finance → My Subscription to start it again.');
            }
        }
    }
    return $next($r);
});

(require $root . '/src/routes.php')($router, $app);
$router->dispatch($request->withPath(preg_replace('#^.*?/api/v1#', '/api/v1', $request->path)))->send();

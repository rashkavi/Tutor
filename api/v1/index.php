<?php
declare(strict_types=1);

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

// Webhooks are public (they verify their own signatures); everything else uses the existing login session.
$authenticator = new LegacySessionAuthenticator($root . '/includes/UserClass.php');
$router->use(static function (Request $r, callable $next) use ($authenticator) {
    if (!str_contains($r->path, '/api/v1/webhooks/')) {
        $r->auth = $authenticator->authenticate($r);
        // CSRF: the session cookie rides along on cross-site form posts, but a custom header cannot be sent
        // cross-site without a CORS preflight (which we never allow). ApiClient always sends it.
        if (!in_array($r->method, ['GET', 'HEAD', 'OPTIONS'], true) && $r->header('X-Requested-With') !== 'enlixo') {
            throw new HttpException(403, 'csrf', 'Request blocked. Please reload the page and try again.');
        }
    }
    return $next($r);
});

(require $root . '/src/routes.php')($router, $app);
$router->dispatch($request->withPath(preg_replace('#^.*?/api/v1#', '/api/v1', $request->path)))->send();

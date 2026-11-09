<?php
declare(strict_types=1);

namespace Enlixo\Core;

/**
 * Minimal router. Patterns like /api/v1/recordings/{kind}/{id}/heartbeat.
 * Handlers are callables (Request): JsonResponse. Middleware wrap handlers.
 */
final class Router
{
    /** @var list<array{method:string, regex:string, names:list<string>, handler:callable}> */
    private array $routes = [];
    /** @var list<callable(Request, callable):JsonResponse> */
    private array $middleware = [];

    public function use(callable $middleware): void
    {
        $this->middleware[] = $middleware;
    }

    public function add(string $method, string $pattern, callable $handler): void
    {
        preg_match_all('/\{(\w+)\}/', $pattern, $m);
        $regex = '#^' . preg_replace('/\{\w+\}/', '([^/]+)', $pattern) . '$#';
        $this->routes[] = ['method' => $method, 'regex' => $regex, 'names' => $m[1], 'handler' => $handler];
    }

    public function get(string $p, callable $h): void { $this->add('GET', $p, $h); }
    public function post(string $p, callable $h): void { $this->add('POST', $p, $h); }
    public function put(string $p, callable $h): void { $this->add('PUT', $p, $h); }
    public function delete(string $p, callable $h): void { $this->add('DELETE', $p, $h); }

    public function dispatch(Request $request): JsonResponse
    {
        try {
            foreach ($this->routes as $route) {
                if ($route['method'] !== $request->method || !preg_match($route['regex'], $request->path, $m)) {
                    continue;
                }
                $request->routeParams = array_combine($route['names'], array_map('urldecode', array_slice($m, 1))) ?: [];
                $handler = $route['handler'];
                foreach (array_reverse($this->middleware) as $mw) {
                    $next = $handler;
                    $handler = static fn (Request $r) => $mw($r, $next);
                }
                return $handler($request);
            }
            throw HttpException::notFound('No such endpoint.');
        } catch (HttpException $e) {
            return JsonResponse::error($e);
        } catch (\PDOException $e) {
            error_log((string) $e);
            return JsonResponse::error(self::setupError($e));
        } catch (\Throwable $e) {
            error_log((string) $e);
            return JsonResponse::error(new HttpException(500, 'server_error', 'Something went wrong. Please try again.'));
        }
    }

    /** Database problems that mean "setup is not finished" get a message that says what to do. */
    private static function setupError(\PDOException $e): HttpException
    {
        $state = (string) ($e->errorInfo[0] ?? $e->getCode());
        $driverCode = (int) ($e->errorInfo[1] ?? 0);
        if (in_array($driverCode, [1044, 1045, 1049, 2002, 2005], true) || $state === '08001' || $state === 'HY000' && str_contains($e->getMessage(), 'connect')) {
            return new HttpException(503, 'setup_db_connection', 'The new pages cannot connect to the database. Check the DB details in config/config.local.php (or includes/connection.php).');
        }
        if ($state === '42S02' || $state === '42S22') {
            return new HttpException(503, 'setup_migration', 'The database needs an update. Run the SQL files in database/migrations/ (001, then 002) on this database.');
        }
        return new HttpException(500, 'server_error', 'Something went wrong. Please try again.');
    }
}

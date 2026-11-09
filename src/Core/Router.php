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
        } catch (\Throwable $e) {
            error_log((string) $e);
            return JsonResponse::error(new HttpException(500, 'server_error', 'Something went wrong. Please try again.'));
        }
    }
}

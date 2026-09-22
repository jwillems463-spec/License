<?php
declare(strict_types=1);

namespace App\Core;

final class Router
{
    /** @var array<int, array{method:string, regex:string, handler:callable|array, middleware:array}> */
    private array $routes = [];
    private array $groupMiddleware = [];

    public function get(string $path, $handler, array $mw = []): void    { $this->add('GET', $path, $handler, $mw); }
    public function post(string $path, $handler, array $mw = []): void   { $this->add('POST', $path, $handler, $mw); }
    public function put(string $path, $handler, array $mw = []): void    { $this->add('PUT', $path, $handler, $mw); }
    public function delete(string $path, $handler, array $mw = []): void { $this->add('DELETE', $path, $handler, $mw); }

    /** Apply middleware to every route registered inside $fn. */
    public function group(array $middleware, callable $fn): void
    {
        $prev = $this->groupMiddleware;
        $this->groupMiddleware = array_merge($prev, $middleware);
        $fn($this);
        $this->groupMiddleware = $prev;
    }

    private function add(string $method, string $path, $handler, array $mw): void
    {
        $regex = preg_replace('#\{(\w+)\}#', '(?P<$1>[^/]+)', rtrim($path, '/') ?: '/');
        $this->routes[] = [
            'method'     => $method,
            'regex'      => '#^' . $regex . '$#',
            'handler'    => $handler,
            'middleware' => array_merge($this->groupMiddleware, $mw),
        ];
    }

    public function dispatch(Request $req): void
    {
        $method = $req->method;
        // Allow PUT/DELETE via POST + _method override for restrictive hosts
        if ($method === 'POST' && ($override = $req->header('X-HTTP-Method-Override'))) {
            $override = strtoupper($override);
            if (in_array($override, ['PUT', 'DELETE'], true)) {
                $method = $override;
            }
        }
        $matchedPath = false;
        foreach ($this->routes as $route) {
            if (!preg_match($route['regex'], $req->path, $m)) {
                continue;
            }
            $matchedPath = true;
            if ($route['method'] !== $method && !($method === 'HEAD' && $route['method'] === 'GET')) {
                continue;
            }
            $req->params = array_filter($m, 'is_string', ARRAY_FILTER_USE_KEY);
            foreach ($route['middleware'] as $mw) {
                $mw($req);
            }
            $handler = $route['handler'];
            if (is_array($handler) && is_string($handler[0])) {
                $handler = [new $handler[0](), $handler[1]];
            }
            $handler($req);
            return;
        }
        if ($matchedPath) {
            throw new HttpException(405, 'Method not allowed.');
        }
        throw new HttpException(404, 'Not found.');
    }
}

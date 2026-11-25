<?php
declare(strict_types=1);

namespace Orixo\Bootstrap;

/**
 * Lightweight path-pattern HTTP router supporting dynamic parameters and middleware guards.
 */
final class Router
{
    /** @var array<int, array{method: string, pattern: string, handler: callable|array, middlewares: array}> */
    private array $routes = [];

    public function add(string $method, string $pattern, callable|array $handler, array $middlewares = []): void
    {
        $this->routes[] = [
            'method' => strtoupper($method),
            'pattern' => $pattern === '/' ? '/' : rtrim($pattern, '/'),
            'handler' => $handler,
            'middlewares' => $middlewares,
        ];
    }

    public function get(string $pattern, callable|array $handler, array $middlewares = []): void
    {
        $this->add('GET', $pattern, $handler, $middlewares);
    }

    public function post(string $pattern, callable|array $handler, array $middlewares = []): void
    {
        $this->add('POST', $pattern, $handler, $middlewares);
    }

    public function patch(string $pattern, callable|array $handler, array $middlewares = []): void
    {
        $this->add('PATCH', $pattern, $handler, $middlewares);
    }

    public function put(string $pattern, callable|array $handler, array $middlewares = []): void
    {
        $this->add('PUT', $pattern, $handler, $middlewares);
    }

    public function delete(string $pattern, callable|array $handler, array $middlewares = []): void
    {
        $this->add('DELETE', $pattern, $handler, $middlewares);
    }

    public function dispatch(string $method, string $uriPath, bool $isApi = false): void
    {
        $method = strtoupper($method);
        $path = parse_url($uriPath, PHP_URL_PATH) ?: '/';
        $path = $path === '/' ? '/' : rtrim($path, '/');

        foreach ($this->routes as $route) {
            if ($route['method'] !== $method) {
                continue;
            }
            $regex = preg_replace('/\{([a-zA-Z_][a-zA-Z0-9_]*)\}/', '(?P<$1>[^/]+)', $route['pattern']);
            $regex = '#^' . $regex . '$#';
            if (!preg_match($regex, $path, $matches)) {
                continue;
            }

            $params = array_filter($matches, static fn ($k) => is_string($k), ARRAY_FILTER_USE_KEY);

            foreach ($route['middlewares'] as $mw) {
                if (is_callable($mw)) {
                    $mw($params);
                } elseif (is_array($mw) && class_exists($mw[0])) {
                    $class = $mw[0];
                    $fn = $mw[1] ?? 'handle';
                    $args = $mw[2] ?? [];
                    (new $class())->$fn($params, ...$args);
                } elseif (is_string($mw) && class_exists($mw)) {
                    (new $mw())->handle($params);
                }
            }

            $handler = $route['handler'];
            if (is_array($handler)) {
                [$class, $fn] = $handler;
                (new $class())->$fn($params);
            } else {
                $handler($params);
            }
            return;
        }

        http_response_code(404);
        if ($isApi) {
            json_response(false, 'API endpoint not found.', null, [], 404);
        }
        render_view('public/404', ['pageTitle' => '404 — Page Not Found | Orixo'], 'layouts/public_layout');
    }
}

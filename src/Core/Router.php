<?php

declare(strict_types=1);

namespace App\Core;

final class Router
{
    /** @var array<int, array{method:string,path:string,handler:array,middleware:array}> */
    private array $routes = [];

    public function get(string $path, array $handler, array $middleware = []): void
    {
        $this->routes[] = ['method' => 'GET', 'path' => $path, 'handler' => $handler, 'middleware' => $middleware];
    }

    public function post(string $path, array $handler, array $middleware = []): void
    {
        $this->routes[] = ['method' => 'POST', 'path' => $path, 'handler' => $handler, 'middleware' => $middleware];
    }

    public function dispatch(string $method, string $uri): void
    {
        $path = parse_url($uri, PHP_URL_PATH) ?: '/';
        $path = rtrim($path, '/');
        if ($path === '') {
            $path = '/';
        }

        foreach ($this->routes as $route) {
            if ($route['method'] !== $method) {
                continue;
            }

            $pattern = $this->toRegex($route['path']);
            if (!preg_match($pattern, $path, $matches)) {
                continue;
            }

            $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);

            // Each middleware entry is [ClassName, arg] or just ClassName.
            foreach ($route['middleware'] as $middleware) {
                [$class, $arg] = is_array($middleware) ? $middleware : [$middleware, null];
                $class::handle($arg);
            }

            [$controllerClass, $action] = $route['handler'];
            (new $controllerClass())->$action($params);
            return;
        }

        http_response_code(404);
        require ROOT_PATH . '/views/errors/404.php';
    }

    private function toRegex(string $path): string
    {
        $pattern = preg_replace('#\{(\w+)\}#', '(?P<$1>[^/]+)', $path);
        return '#^' . $pattern . '$#';
    }
}

<?php

declare(strict_types=1);

namespace App\Core;

final class Router
{
    /** @var array<string, array<string, callable>> */
    private array $routes = [];

    public function get(string $path, callable $handler): void
    {
        $this->routes['GET'][$path] = $handler;
    }

    public function post(string $path, callable $handler): void
    {
        $this->routes['POST'][$path] = $handler;
    }

    public function dispatch(string $method, string $uri): void
    {
        $path = parse_url($uri, PHP_URL_PATH) ?: '/';
        $path = rtrim($path, '/') ?: '/';

        // Exact match first — cheapest, and covers most routes.
        $handler = $this->routes[$method][$path] ?? null;
        if ($handler !== null) {
            $handler();
            return;
        }

        // Then patterned routes: '/article/{id}' captures one path segment.
        foreach ($this->routes[$method] ?? [] as $route => $candidate) {
            if (!str_contains($route, '{')) {
                continue;
            }
            $regex = '#^' . preg_replace('#\\\\\{[a-z_]+\\\\\}#', '([^/]+)', preg_quote($route, '#')) . '$#';
            if (preg_match($regex, $path, $m)) {
                array_shift($m);
                $candidate(...$m);
                return;
            }
        }

        http_response_code(404);
        View::render('errors/404', ['pageTitle' => 'Not found']);
    }
}

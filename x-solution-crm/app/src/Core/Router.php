<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Schlanker Router: Pfadmuster mit {platzhaltern}, GET/POST, Middleware "auth"/"admin".
 */
final class Router
{
    /** @var array<int,array{method:string,regex:string,params:array,handler:array|callable,guard:string}> */
    private array $routes = [];

    public function get(string $pattern, array|callable $handler, string $guard = 'auth'): void
    {
        $this->add('GET', $pattern, $handler, $guard);
    }

    public function post(string $pattern, array|callable $handler, string $guard = 'auth'): void
    {
        $this->add('POST', $pattern, $handler, $guard);
    }

    private function add(string $method, string $pattern, array|callable $handler, string $guard): void
    {
        $params = [];
        $regex = preg_replace_callback('/\{(\w+)\}/', function ($m) use (&$params) {
            $params[] = $m[1];
            return $m[1] === 'id' ? '(\d+)' : '([^/]+)';
        }, rtrim($pattern, '/') ?: '/');
        $this->routes[] = [
            'method' => $method,
            'regex' => '#^' . $regex . '$#',
            'params' => $params,
            'handler' => $handler,
            'guard' => $guard,
        ];
    }

    public function dispatch(string $method, string $path): void
    {
        $path = '/' . trim($path, '/');
        if ($method === 'HEAD') {
            $method = 'GET';
        }
        $allowed = false;
        foreach ($this->routes as $route) {
            if (!preg_match($route['regex'], $path, $m)) {
                continue;
            }
            if ($route['method'] !== $method) {
                $allowed = true;
                continue;
            }
            $args = [];
            foreach ($route['params'] as $i => $name) {
                $args[$name] = $name === 'id' ? (int) $m[$i + 1] : urldecode($m[$i + 1]);
            }
            $this->guard($route['guard']);
            if ($method === 'POST') {
                Csrf::verifyRequest();
            }
            $handler = $route['handler'];
            if (is_array($handler)) {
                [$class, $action] = $handler;
                (new $class())->$action(...array_values($args));
            } else {
                $handler(...array_values($args));
            }
            return;
        }
        if ($allowed) {
            http_response_code(405);
            echo 'Methode nicht erlaubt';
            return;
        }
        Auth::check() ? View::error(404, 'Seite nicht gefunden', 'Die angeforderte Seite existiert nicht.') : redirect('/login');
    }

    private function guard(string $guard): void
    {
        if ($guard === 'guest') {
            return;
        }
        if (!Auth::check()) {
            if (Request::wantsJson()) {
                json_response(['error' => 'Nicht angemeldet'], 401);
            }
            $_SESSION['intended'] = Request::path();
            redirect('/login');
        }
        if ($guard === 'admin' && !Auth::isAdmin()) {
            View::error(403, 'Keine Berechtigung', 'Diese Seite ist Administratoren vorbehalten.');
        }
    }
}

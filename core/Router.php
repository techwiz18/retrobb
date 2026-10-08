<?php
declare(strict_types=1);

namespace RetroBB\Core;

class Router
{
    /** @var array<int, array{method:string, pattern:string, handler:callable|array}> */
    private array $routes = [];

    public function add(string $method, string $pattern, callable|array $handler): void
    {
        $this->routes[] = [strtoupper($method), $pattern, $handler];
    }

    public function get(string $pattern, callable|array $handler): void
    {
        $this->add('GET', $pattern, $handler);
    }

    public function post(string $pattern, callable|array $handler): void
    {
        $this->add('POST', $pattern, $handler);
    }

    public function dispatch(string $method, string $path): void
    {
        $method = strtoupper($method);
        foreach ($this->routes as [$m, $pattern, $handler]) {
            if ($m !== $method && !($m === 'GET' && $method === 'HEAD')) {
                continue;
            }
            if (preg_match($pattern, $path, $matches)) {
                array_shift($matches);
                // Route captures are strings; cast pure digits to int so
                // strict_types=1 controller signatures (int $id) work.
                $matches = array_map(
                    fn($m) => is_string($m) && preg_match('/^\d+$/', $m) ? (int) $m : $m,
                    array_values($matches)
                );
                if (is_array($handler)) {
                    [$class, $fn] = $handler;
                    (new $class)->$fn(...array_values($matches));
                } else {
                    $handler(...array_values($matches));
                }
                return;
            }
        }
        http_response_code(404);
        View::render('errors/404', ['path' => $path]);
    }

    public static function currentPath(): string
    {
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        $path = parse_url($uri, PHP_URL_PATH) ?? '/';
        // support ?/forum/... fallback for hosts without mod_rewrite
        if (isset($_GET['/'])) {
            $path = '/' . ltrim((string) $_GET['/'], '/');
        } elseif (str_starts_with($path . '', '/index.php')) {
            $path = substr($path, strlen('/index.php')) ?: '/';
        }
        if ($path === '') {
            $path = '/';
        }
        return $path;
    }
}

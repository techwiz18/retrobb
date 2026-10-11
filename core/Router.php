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
        // Subpath fallback: try the base-stripped path FIRST so app segments
        // under the prefix (/forum/members, /forum/search, ...) reach their
        // real handlers instead of the greedy /forum/<slug> route; genuine
        // forum URLs still win because their stripped form (/<slug>.fN)
        // matches no route and we fall through to the raw path.
        $candidates = [];
        $base = \base_path();
        if ($base !== '' && ($path === $base || str_starts_with($path, $base . '/'))) {
            $stripped = substr($path, strlen($base)) ?: '/';
            if ($stripped !== $path) {
                $candidates[] = $stripped;
            }
        }
        $candidates[] = $path;
        foreach ($candidates as $try) {
            foreach ($this->routes as [$m, $pattern, $handler]) {
                if ($m !== $method && !($m === 'GET' && $method === 'HEAD')) {
                    continue;
                }
                if (preg_match($pattern, $try, $matches)) {
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
        // NOTE: do NOT strip base_path() here. App routes like /forum/<slug>.fN
        // legitimately start with the base prefix (/forum), so stripping would
        // break them (currentPath '/forum/x.f1' -> '/x.f1' matches nothing).
        // dispatch() tries raw first, then the stripped fallback — that is the
        // only place base-stripping belongs.
        return $path;
    }
}

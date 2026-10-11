<?php
declare(strict_types=1);

// Global helpers (no namespace for brevity in views).

function e(?string $s): string
{
    return htmlspecialchars((string) $s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function redirect(string $url, int $code = 302): void
{
    header('Location: ' . url($url), true, $code);
    exit;
}

/** Argon2id where available, bcrypt fallback for minimal shared hosts. */
function password_hash_safe(string $password): string
{
    $algo = defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_DEFAULT;
    $hash = password_hash($password, $algo);
    if ($hash === false) {
        // Last resort: bcrypt is always available.
        $hash = (string) password_hash($password, PASSWORD_DEFAULT);
    }
    return $hash;
}

function setting(string $key, string $default = ''): string
{
    static $cache = null;
    if ($cache === null) {
        try {
            $pdo = \RetroBB\Core\Db::pdo();
            $rows = $pdo->query('SELECT `key`, `value` FROM settings')->fetchAll();
            $cache = [];
            foreach ($rows as $r) {
                $cache[$r['key']] = $r['value'];
            }
        } catch (Throwable $t) {
            $cache = [];
        }
    }
    return $cache[$key] ?? $default;
}

function board_name(): string
{
    return setting('board_name', 'RetroBB');
}

/**
 * Subpath deployment prefix (reverse-proxy setups): leading /, no trailing
 * slash, '' when the board serves from the domain root. Env wins over
 * config.php; never written by the installer.
 */
function base_path(): string
{
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }
    $raw = getenv('RETROBB_BASE_PATH');
    if ($raw === false) {
        try {
            $cfg = require dirname(__DIR__) . '/config.php';
            $raw = $cfg['base_path'] ?? '';
        } catch (\Throwable) {
            $raw = '';
        }
    }
    $normal = '/' . trim((string) $raw, '/');
    $cache = $normal === '/' ? '' : $normal;
    return $cache;
}

/**
 * True when the request already addresses the canonical URL. Compares
 * base-relative paths (subpath deploys) and ignores pagination suffixes,
 * so both /topic/x and /forum/topic/x match without redirect loops.
 */
function is_canonical(string $canonical): bool
{
    $path = \RetroBB\Core\Router::currentPath();
    $base = strtok($path, '?');
    $base = (string) preg_replace('#/page-\d+$#', '', $base);
    // Strip the subpath prefix from BOTH sides: currentPath() is raw now
    // (dispatch handles the fallback), so /forum/topic/x must compare equal
    // to its stripped form without redirect-looping, while genuine
    // /forum/<slug>.fN routes still match their canonical exactly.
    if (($b = base_path()) !== '' && ($base === $b || str_starts_with($base, $b . '/'))) {
        $stripped = substr($base, strlen($b)) ?: '/';
        // Only accept the stripped form when it is a real app path — a bare
        // '/<slug>.fN' is never canonical (forum routes live under /forum/).
        // Compare against both raw and stripped to avoid loops.
        if ($stripped !== $base) {
            $canon = $canonical;
            if (str_starts_with($canon, $b . '/')) {
                $canon = substr($canon, strlen($b));
            }
            return $base === $canonical || $stripped === $canon;
        }
    }
    $canon = $canonical;
    if (($b = base_path()) !== '' && str_starts_with($canon, $b . '/')) {
        $canon = substr($canon, strlen($b));
    }
    return $base === $canonical || $base === $canon;
}

/**
 * Single choke point for every URL the app emits. Absolute URLs pass
 * through; anything already carrying the prefix is left alone (idempotent),
 * so Referer-derived paths stay valid.
 */
function url(string $path): string
{
    if (preg_match('#^https?://#i', $path)) {
        return $path;
    }
    $base = base_path();
    if ($base !== '' && ($path === $base || str_starts_with($path, $base . '/'))) {
        return $path;
    }
    return $base . '/' . ltrim($path, '/');
}

/** Board-wide feature flag. Unknown features default ON (opt-out model). */
function feature(string $name): bool
{
    $map = [
        'alerts' => 'feature_alerts',
        'pms' => 'feature_pms',
        'reactions' => 'feature_reactions',
        'mentions' => 'feature_mentions',
        'skin_selector' => 'skin_selector',
    ];
    $key = $map[$name] ?? ('feature_' . $name);
    return setting($key, '1') === '1';
}

/** Which theme modes (light/dark/auto) the owner lets users pick. Never empty. */
function allowed_themes(): array
{
    $out = [];
    foreach (['light', 'dark', 'auto'] as $t) {
        if (setting('theme_' . $t, '1') === '1') {
            $out[] = $t;
        }
    }
    return $out ?: ['auto'];
}

function skin(): string
{
    if (!feature('skin_selector')) {
        $d = setting('default_skin', 'classic');
        return in_array($d, ['classic', 'midnight', 'silver'], true) ? $d : 'classic';
    }
    $s = $_COOKIE['retrobb_skin'] ?? setting('default_skin', 'classic');
    return in_array($s, ['classic', 'midnight', 'silver'], true) ? $s : 'classic';
}

function theme(): string
{
    $allowed = allowed_themes();
    $fallback = setting('default_theme', 'auto');
    if (!in_array($fallback, $allowed, true)) {
        $fallback = in_array('auto', $allowed, true) ? 'auto' : $allowed[0];
    }
    $t = $_COOKIE['retrobb_theme'] ?? $fallback;
    if (!in_array($t, ['light', 'dark', 'auto'], true) || !in_array($t, $allowed, true)) {
        $t = $fallback;
    }
    return $t;
}

function canonical_url(string $path): string
{
    // Prefer the configured board URL when the owner set one at install.
    $base = rtrim(setting('board_url', ''), '/');
    if ($base !== '' && preg_match('#^https?://#i', $base)) {
        // board_url may itself carry the subpath (https://hub/forum after
        // cutover) while app paths already include the base prefix — never
        // double it, but still add it for paths that lack it (e.g. mail
        // links built from bare paths).
        $parts = parse_url($base);
        $prefix = rtrim($parts['path'] ?? '', '/');
        if ($prefix !== '' && ($path === $prefix || str_starts_with($path, $prefix . '/'))) {
            $origin = ($parts['scheme'] ?? 'http') . '://' . ($parts['host'] ?? '');
            if (isset($parts['port'])) {
                $origin .= ':' . $parts['port'];
            }
            return $origin . $path;
        }
        return $base . $path;
    }
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    return $scheme . '://' . $host . $path;
}

function time_ago(string $datetime): string
{
    try {
        $t = new DateTime($datetime);
    } catch (Throwable) {
        // Unparseable input (e.g. legacy garbage in the column): show
        // nothing rather than echoing the raw value back into the page.
        return '';
    }
    $diff = time() - $t->getTimestamp();
    if ($diff < 60) {
        return 'just now';
    }
    if ($diff < 3600) {
        $m = (int) floor($diff / 60);
        return $m . ' min ago';
    }
    if ($diff < 86400) {
        $h = (int) floor($diff / 3600);
        return $h . ' hr ago';
    }
    if ($diff < 86400 * 30) {
        $d = (int) floor($diff / 86400);
        return $d . ' day' . ($d > 1 ? 's' : '') . ' ago';
    }
    return $t->format('M j, Y g:ia');
}

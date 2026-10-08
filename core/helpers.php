<?php
declare(strict_types=1);

// Global helpers (no namespace for brevity in views).

function e(?string $s): string
{
    return htmlspecialchars((string) $s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function redirect(string $url, int $code = 302): void
{
    header('Location: ' . $url, true, $code);
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

function skin(): string
{
    $s = $_COOKIE['retrobb_skin'] ?? setting('default_skin', 'classic');
    return in_array($s, ['classic', 'midnight', 'silver'], true) ? $s : 'classic';
}

function theme(): string
{
    $t = $_COOKIE['retrobb_theme'] ?? 'auto';
    return in_array($t, ['light', 'dark', 'auto'], true) ? $t : 'auto';
}

function canonical_url(string $path): string
{
    // Prefer the configured board URL when the owner set one at install.
    $base = rtrim(setting('board_url', ''), '/');
    if ($base !== '' && preg_match('#^https?://#i', $base)) {
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
        return $datetime;
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

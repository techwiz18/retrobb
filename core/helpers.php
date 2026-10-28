<?php
declare(strict_types=1);

// Global helpers (no namespace for brevity in views).

/** Current package version. Bump on release; -dev/-beta suffixes sort below releases. */
const RETROBB_VERSION = '0.6.0-beta.1';

function e(?string $s): string
{
    return htmlspecialchars((string) $s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function redirect(string $url, int $code = 302): void
{
    // Choke point: never emit an off-site redirect, no matter what a caller
    // passes. Absolute URLs survive only when they point back at this host
    // (canonical-host 301s) or the configured board host; everything else
    // (protocol-relative, backslashes, schemes) collapses to '/'.
    if (preg_match('#^[a-z][a-z0-9+.-]*://#i', $url)) {
        $h = strtolower((string) parse_url($url, PHP_URL_HOST));
        $mine = strtolower(explode(':', (string) ($_SERVER['HTTP_HOST'] ?? ''))[0]);
        $boardHost = '';
        $boardUrl = setting('board_url', '');
        if ($boardUrl !== '' && preg_match('#^https?://#i', $boardUrl)) {
            $boardHost = strtolower((string) parse_url($boardUrl, PHP_URL_HOST));
        }
        if ($h === '' || ($h !== $mine && $h !== $boardHost)) {
            $url = '/';
        }
    } elseif (str_starts_with($url, '//') || str_contains($url, '\\') || preg_match('#^[a-z][a-z0-9+.-]*:#i', $url)) {
        $url = '/';
    }
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
 * Read one scalar from config.php. Cached per request; a missing or
 * unreadable config yields the default instead of throwing (pre-install
 * requests boot before there is a database).
 */
function config_value(string $key, mixed $default = null): mixed
{
    static $cfg = null;
    if ($cfg === null) {
        try {
            $loaded = require dirname(__DIR__) . '/config.php';
            $cfg = is_array($loaded) ? $loaded : [];
        } catch (\Throwable) {
            $cfg = [];
        }
    }
    return $cfg[$key] ?? $default;
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
    // Compare the path portion only: '?picked=…' style queries must not
    // defeat the already-prefixed check (that doubled /forum once).
    $onlyPath = explode('?', $path, 2)[0];
    if ($base !== '' && ($onlyPath === $base || str_starts_with($onlyPath, $base . '/'))) {
        return $path;
    }
    return $base . '/' . ltrim($path, '/');
}

/**
 * Latest-release check against GitHub. Cached 24h in settings; never fatal
 * (offline hosts, disabled URL wrappers, and API hiccups all degrade to
 * 'unknown'). Returns [latest|null, upToDate|null].
 */
function update_check(): array
{
    $cached = setting('update_latest', '');
    $at = (int) setting('update_checked_at', '0');
    if ($cached !== '' && $at > time() - 86400) {
        return [$cached, version_compare(ltrim(RETROBB_VERSION, 'v'), ltrim($cached, 'v'), '>=')];
    }
    $latest = null;
    $ctx = stream_context_create(['http' => ['timeout' => 5, 'header' => "User-Agent: RetroBB\r\nAccept: application/json\r\n"]]);
    foreach (['file_get_contents', 'curl'] as $method) {
        try {
            if ($method === 'file_get_contents' && ini_get('allow_url_fopen')) {
                $json = @file_get_contents('https://api.github.com/techwiz18/retrobb/releases/latest', false, $ctx);
                $data = $json ? json_decode($json, true) : null;
                if (is_array($data) && !empty($data['tag_name'])) {
                    $latest = (string) $data['tag_name'];
                    break;
                }
            } elseif ($method === 'curl' && function_exists('curl_init')) {
                $ch = curl_init('https://api.github.com/techwiz18/retrobb/releases/latest');
                curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 5, CURLOPT_USERAGENT => 'RetroBB', CURLOPT_HTTPHEADER => ['Accept: application/json']]);
                $json = curl_exec($ch);
                curl_close($ch);
                $data = $json ? json_decode($json, true) : null;
                if (is_array($data) && !empty($data['tag_name'])) {
                    $latest = (string) $data['tag_name'];
                    break;
                }
            }
        } catch (\Throwable) {
        }
    }
    if ($latest !== null) {
        try {
            $pdo = \RetroBB\Core\Db::pdo();
            $up = $pdo->prepare('INSERT INTO settings (`key`, `value`) VALUES (?, ?) ON DUPLICATE KEY UPDATE `value`=VALUES(`value`)');
            $up->execute(['update_latest', $latest]);
            $up->execute(['update_checked_at', (string) time()]);
        } catch (\Throwable) {
        }
        return [$latest, version_compare(ltrim(RETROBB_VERSION, 'v'), ltrim($latest, 'v'), '>=')];
    }
    return [$cached !== '' ? $cached : null, null];
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
    // No board_url: return a base-prefixed RELATIVE URL. Never build absolute
    // links from the request Host (issue #13 / H1): a spoofed Host would
    // otherwise poison canonical tags and password-reset links. Callers that
    // need a real origin must use canonical_absolute_url() and cope with ''
    // (not configured).
    $bp = base_path();
    if ($bp !== '' && $path !== $bp && !str_starts_with(explode('?', $path, 2)[0], $bp . '/')) {
        $path = $bp . '/' . ltrim($path, '/');
    }
    return $path;
}

/**
 * Absolute URL for mail/feeds, or '' when board_url is not configured. Never
 * falls back to the request Host (H1) — a blank result means the caller must
 * refuse to emit the link rather than trusting whatever Host the client sent.
 */
function canonical_absolute_url(string $path): string
{
    $base = rtrim(setting('board_url', ''), '/');
    if ($base === '' || !preg_match('#^https?://#i', $base)) {
        return '';
    }
    return canonical_url($path);
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

/** Parse a php.ini shorthand byte value (128M, 8M, 512K). 0 = unlimited. */
function php_shorthand_bytes(string $v): int
{
    $v = trim($v);
    if ($v === '' || $v === '-1') {
        return 0;
    }
    $unit = strtolower(substr($v, -1));
    $num = (float) $v;
    if (ctype_digit($unit)) {
        return (int) $num;
    }
    return (int) ($num * match ($unit) {
        'g' => 1024 * 1024 * 1024,
        'm' => 1024 * 1024,
        'k' => 1024,
        default => 1,
    });
}

/**
 * Effective avatar limits: admin caps clamped by what the host allows
 * (upload_max_filesize / post_max_size win — the admin UI says so).
 * @return array{bytes:int, width:int, height:int}
 */
function avatar_limits(): array
{
    $bytes = max(10, min(2048, (int) setting('avatar_max_kb', '100'))) * 1024;
    foreach ([ini_get('upload_max_filesize'), ini_get('post_max_size')] as $h) {
        $cap = is_string($h) ? php_shorthand_bytes($h) : 0;
        if ($cap > 0) {
            $bytes = min($bytes, $cap);
        }
    }
    return [
        'bytes' => $bytes,
        'width' => max(16, min(2000, (int) setting('avatar_max_width', '200'))),
        'height' => max(16, min(2000, (int) setting('avatar_max_height', '200'))),
    ];
}

/**
 * Whether uploaded avatars can be resized server-side (PHP GD). Shared
 * hosts without GD fall back to rejecting over-dimension uploads.
 */
function avatar_resize_available(): bool
{
    return extension_loaded('gd') && function_exists('imagecreatetruecolor')
        && function_exists('imagecopyresampled') && function_exists('imagecreatefromjpeg')
        && function_exists('imagecreatefrompng') && function_exists('imagecreatefromgif')
        && function_exists('imagejpeg') && function_exists('imagepng') && function_exists('imagegif');
}

/**
 * Is this a direct image link? The extension must terminate the PATH —
 * query-string tricks (?x=.jpg) and fragments (#x.jpg) don't count, so a
 * "linked avatar" can't hide a tracking/beacon URL behind an extension.
 */
function avatar_link_ok(string $url): bool
{
    if (!preg_match('#^https?://[^/]+/.+#i', $url)) {
        return false;
    }
    $path = parse_url($url, PHP_URL_PATH) ?? '';
    return (bool) preg_match('#\.(jpe?g|png|gif|webp|avif)$#i', $path);
}

/**
 * Avatar <img> for uploads/links, initial-letter fallback otherwise.
 * Missing keys (pre-avatar schema) safely fall back to the initial.
 * $size: 'post' (caps), 'profile' (caps), 'mini' (half caps, 24–64px list
 * thumb). Bool kept for back-compat (true = profile).
 */
function avatar_html(array $user, bool|string $size = 'post'): string
{
    $kind = $user['avatar_kind'] ?? 'none';
    $src = '';
    if ($kind === 'upload' && isset($user['avatar_file']) && preg_match('#^[A-Za-z0-9_-]+\.(jpg|jpeg|png|gif)$#', (string) $user['avatar_file'])) {
        $src = url('/avatars/' . $user['avatar_file']);
    } elseif ($kind === 'link' && isset($user['avatar_url']) && avatar_link_ok((string) $user['avatar_url'])) {
        $src = $user['avatar_url'];
    }
    if ($size === true) {
        $size = 'profile';
    } elseif ($size === false) {
        $size = 'post';
    }
    $cls = $size === 'profile' ? 'avatarimg avatar-lg' : 'avatarimg';
    // Display size follows the admin's max dimensions (uploads are shrunk
    // to fit on the way in) — identical in posts and on profiles; minis
    // render at half caps for dense lists like the members index.
    // Width + aspect-ratio (never fixed height) so narrow columns and the
    // max-width guard can shrink without distortion.
    $lim = avatar_limits();
    $w = (int) $lim['width'];
    $h = (int) $lim['height'];
    if ($size === 'mini') {
        $w = max(24, min(64, (int) floor($w / 2)));
        $h = max(24, min(64, (int) floor($h / 2)));
    }
    // Images render at the configured size with aspect preserved (the
    // postbit column scales from the same caps, so nothing spills); the
    // max-width guard only bites on viewports narrower than the layout.
    if ($src !== '') {
        return '<img class="' . $cls . '" style="width:' . $w . 'px;aspect-ratio:' . $w . '/' . $h . '" src="' . e($src) . '" alt="" loading="lazy">';
    }
    $name = (string) ($user['username'] ?? '?');
    $hue = abs(crc32($name)) % 360;
    $initial = mb_strtoupper(mb_substr($name, 0, 1));
    $lg = $size === 'profile' ? ' avatar-lg' : '';
    $box = 'width:' . $w . 'px;aspect-ratio:' . $w . '/' . $h;
    return '<span class="avatar' . $lg . '" style="' . $box . ';font-size:' . (int) round(min($w, $h) * 0.46) . 'px;background:hsl(' . $hue . ',45%,55%)">' . e($initial) . '</span>';
}

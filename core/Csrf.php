<?php
declare(strict_types=1);

namespace RetroBB\Core;

class Csrf
{
    public static function token(): string
    {
        Auth::startSession();
        if (empty($_SESSION['csrf'])) {
            $_SESSION['csrf'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf'];
    }

    public static function field(): string
    {
        return '<input type="hidden" name="csrf" value="' . htmlspecialchars(self::token(), ENT_QUOTES, 'UTF-8') . '">';
    }

    public static function verify(?string $token): bool
    {
        // Fail closed: no session means no token was ever issued.
        if (!Auth::startSession(false)) {
            return false;
        }
        $sess = $_SESSION['csrf'] ?? '';
        return $token !== null && $sess !== '' && hash_equals($sess, $token);
    }

    /**
     * Sessionless double-submit token for the anonymous skin/theme switchers
     * (issue #2). Guests deliberately get no session, so the switchers cannot
     * use the session token above. A random value rides in a cookie and is
     * echoed back in the form; a cross-site page can do neither — it cannot
     * read the cookie nor set it for this host — so a mismatched/unset pair
     * fails. Minted once per request and only when the switcher is rendered.
     */
    public static function anonToken(): string
    {
        static $token = null;
        if ($token !== null) {
            return $token;
        }
        $token = (string) ($_COOKIE['retrobb_csrf'] ?? '');
        if (preg_match('/^[a-f0-9]{32}$/', $token) !== 1) {
            $token = bin2hex(random_bytes(16));
            // The layout is output-buffered (see View::render), so this still
            // reaches the response headers even late in the render.
            if (!headers_sent()) {
                $https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
                setcookie('retrobb_csrf', $token, [
                    'expires' => time() + 86400 * 365,
                    'path' => \base_path() ?: '/',
                    'samesite' => 'Lax',
                    'httponly' => true,
                    'secure' => $https,
                ]);
            }
        }
        return $token;
    }

    /** Distinct field name so it never shadows a page's real session `csrf`. */
    public static function anonField(): string
    {
        return '<input type="hidden" name="anon_csrf" value="' . htmlspecialchars(self::anonToken(), ENT_QUOTES, 'UTF-8') . '">';
    }

    public static function verifyAnon(?string $submitted): bool
    {
        $cookie = (string) ($_COOKIE['retrobb_csrf'] ?? '');
        return $submitted !== null && $submitted !== ''
            && preg_match('/^[a-f0-9]{32}$/', $cookie) === 1
            && hash_equals($cookie, $submitted);
    }
}

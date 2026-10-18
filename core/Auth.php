<?php
declare(strict_types=1);

namespace RetroBB\Core;

class Auth
{
    /**
     * Start the session. With $create=false (lazy mode) no session or cookie
     * is created for first-time guests — the session only resumes when the
     * client already sent a session cookie. Returns whether a session is active.
     */
    public static function startSession(bool $create = true): bool
    {
        if (session_status() !== PHP_SESSION_NONE) {
            return true;
        }
        if (!$create && empty($_COOKIE[session_name()])) {
            return false;
        }
        // Harden the session cookie (no JS access, same-site only).
        // Path follows the install: subpath boards scope it to the prefix.
        // Secure goes on automatically under HTTPS (never force it: plain-HTTP
        // dev installs would silently lose their sessions).
        $https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => \base_path() ?: '/',
            'httponly' => true,
            'samesite' => 'Lax',
            'secure' => $https,
        ]);
        session_start();
        return true;
    }

    public static function user(): ?array
    {
        if (!self::startSession(false)) {
            return null;
        }
        return $_SESSION['user'] ?? null;
    }

    /**
     * Idle + absolute session limits in seconds (0 disables one). Config wins,
     * env overrides it, sane defaults cover an untouched install.
     *
     * @return array{0:int,1:int} [idle, absolute]
     */
    private static function sessionLimits(): array
    {
        $envIdle = getenv('RETROBB_SESSION_IDLE');
        $envAbs = getenv('RETROBB_SESSION_ABSOLUTE');
        $idle = ($envIdle === false || $envIdle === '')
            ? (int) \config_value('session_idle_seconds', 1800)
            : (int) $envIdle;
        $abs = ($envAbs === false || $envAbs === '')
            ? (int) \config_value('session_absolute_seconds', 604800)
            : (int) $envAbs;
        return [max(0, $idle), max(0, $abs)];
    }

    public static function login(array $user): void
    {
        self::startSession();
        session_regenerate_id(true);
        // Start both clocks here: validateSession() enforces them from now on.
        $_SESSION['auth_at'] = time();
        $_SESSION['seen_at'] = time();
        $_SESSION['user'] = [
            'id' => (int) $user['id'],
            'username' => $user['username'],
            'user_group' => $user['user_group'],
            // Pre-tier schemas lack the column: super_known=false keeps old
            // behavior (every admin acts super) until migrate runs.
            'is_super' => (int) ($user['is_super'] ?? 0),
            'super_known' => array_key_exists('is_super', $user),
        ];
    }

    public static function logout(): void
    {
        self::startSession();
        unset($_SESSION['user'], $_SESSION['auth_at'], $_SESSION['seen_at']);
        session_regenerate_id(true);
    }

    /** New session id, same data: call after privilege changes (password change). */
    public static function rotate(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
    }

    public static function check(): bool
    {
        return self::user() !== null;
    }

    public static function isAdmin(): bool
    {
        $u = self::user();
        return $u !== null && $u['user_group'] === 'admin';
    }

    public static function isMod(): bool
    {
        $u = self::user();
        return $u !== null && in_array($u['user_group'], ['admin', 'mod'], true);
    }

    /** Unrestricted tier: flagged super admins (first admin bootstrapped). */
    public static function isSuper(): bool
    {
        $u = self::user();
        if ($u === null || $u['user_group'] !== 'admin') {
            return false;
        }
        if (!($u['super_known'] ?? false)) {
            return true;
        }
        return ($u['is_super'] ?? 0) === 1;
    }

    /** Refresh group in session after admin change. */
    public static function refresh(array $freshUser): void
    {
        self::startSession();
        if (isset($_SESSION['user']) && (int) $_SESSION['user']['id'] === (int) $freshUser['id']) {
            $_SESSION['user']['user_group'] = $freshUser['user_group'];
            $_SESSION['user']['username'] = $freshUser['username'];
            $_SESSION['user']['is_super'] = (int) ($freshUser['is_super'] ?? 0);
            $_SESSION['user']['super_known'] = array_key_exists('is_super', $freshUser);
        }
    }

    /**
     * Re-validate the session against the DB on every request: a demoted
     * staffer loses powers immediately, a banned user is logged out at once.
     * Safe to call pre-install (tables may not exist yet).
     */
    public static function validateSession(): void
    {
        if (!self::startSession(false)) {
            return;
        }
        if (empty($_SESSION['user'])) {
            return;
        }
        $now = time();
        // Sessions minted before limits existed adopt the clock once, then
        // age normally — an upgrade must not log everyone out mid-session.
        if (!isset($_SESSION['auth_at'])) {
            $_SESSION['auth_at'] = $now;
            $_SESSION['seen_at'] = $now;
        }
        [$idleLimit, $absLimit] = self::sessionLimits();
        $started = (int) $_SESSION['auth_at'];
        $seen = (int) ($_SESSION['seen_at'] ?? $started);
        if (($absLimit > 0 && $now - $started > $absLimit)
            || ($idleLimit > 0 && $now - $seen > $idleLimit)) {
            self::logout();
            $_SESSION['flash_error'] = 'Your session expired. Please log in again.';
            return;
        }
        $_SESSION['seen_at'] = $now;
        $id = (int) $_SESSION['user']['id'];
        try {
            $fresh = \RetroBB\Models\User::find($id);
        } catch (\Throwable) {
            return;
        }
        if (!$fresh) {
            self::logout();
            return;
        }
        $_SESSION['user']['username'] = $fresh['username'];
        $_SESSION['user']['user_group'] = $fresh['user_group'];
        $_SESSION['user']['is_super'] = (int) ($fresh['is_super'] ?? 0);
        $_SESSION['user']['super_known'] = array_key_exists('is_super', $fresh);
        try {
            $ban = \RetroBB\Models\Moderation::activeBan($id);
        } catch (\Throwable) {
            $ban = null;
        }
        if ($ban) {
            self::logout();
            $_SESSION['flash_error'] = 'Your account has been banned'
                . (!empty($ban['expires_at']) ? ' until ' . $ban['expires_at'] : ' permanently')
                . '. Reason: ' . $ban['reason'];
        }
    }
}

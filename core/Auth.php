<?php
declare(strict_types=1);

namespace RetroBB\Core;

class Auth
{
    public static function startSession(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            // Harden the session cookie (no JS access, same-site only).
            session_set_cookie_params([
                'lifetime' => 0,
                'path' => '/',
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
            session_start();
        }
    }

    public static function user(): ?array
    {
        self::startSession();
        return $_SESSION['user'] ?? null;
    }

    public static function login(array $user): void
    {
        self::startSession();
        session_regenerate_id(true);
        $_SESSION['user'] = [
            'id' => (int) $user['id'],
            'username' => $user['username'],
            'user_group' => $user['user_group'],
        ];
    }

    public static function logout(): void
    {
        self::startSession();
        unset($_SESSION['user']);
        session_regenerate_id(true);
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

    /** Refresh group in session after admin change. */
    public static function refresh(array $freshUser): void
    {
        self::startSession();
        if (isset($_SESSION['user']) && (int) $_SESSION['user']['id'] === (int) $freshUser['id']) {
            $_SESSION['user']['user_group'] = $freshUser['user_group'];
            $_SESSION['user']['username'] = $freshUser['username'];
        }
    }

    /**
     * Re-validate the session against the DB on every request: a demoted
     * staffer loses powers immediately, a banned user is logged out at once.
     * Safe to call pre-install (tables may not exist yet).
     */
    public static function validateSession(): void
    {
        self::startSession();
        if (empty($_SESSION['user'])) {
            return;
        }
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

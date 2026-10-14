<?php
declare(strict_types=1);

namespace RetroBB\Controllers;

use RetroBB\Core\Auth;
use RetroBB\Core\Csrf;
use RetroBB\Core\View;
use RetroBB\Models\User;

class AuthController
{
    /** 10 failures in 15 minutes from one IP blocks it. Table may predate migration: fail open. */
    private static function loginBlocked(string $ip): bool
    {
        try {
            $st = \RetroBB\Core\Db::pdo()->prepare('SELECT attempts, last_at FROM login_attempts WHERE ip=?');
            $st->execute([$ip]);
            $row = $st->fetch();
            if (!$row) {
                return false;
            }
            if (strtotime((string) $row['last_at']) < time() - 900) {
                \RetroBB\Core\Db::pdo()->prepare('DELETE FROM login_attempts WHERE ip=?')->execute([$ip]);
                return false;
            }
            return (int) $row['attempts'] >= 10;
        } catch (\Throwable) {
            return false;
        }
    }

    private static function loginFailed(string $ip): void
    {
        if ($ip === '') {
            return;
        }
        try {
            $pdo = \RetroBB\Core\Db::pdo();
            $pdo->prepare('INSERT INTO login_attempts (ip, attempts, last_at) VALUES (?,1,NOW()) ON DUPLICATE KEY UPDATE attempts=attempts+1, last_at=NOW()')->execute([$ip]);
            $pdo->exec("DELETE FROM login_attempts WHERE last_at < NOW() - INTERVAL 1 DAY");
        } catch (\Throwable) {
            // Pre-migration boards keep the session throttle only.
        }
    }

    private static function loginCleared(string $ip): void
    {
        if ($ip === '') {
            return;
        }
        try {
            \RetroBB\Core\Db::pdo()->prepare('DELETE FROM login_attempts WHERE ip=?')->execute([$ip]);
        } catch (\Throwable) {
        }
    }

    public function registerForm(): void
    {
        if (Auth::check()) {
            redirect('/');
        }
        View::render('auth/register', ['pageTitle' => 'Register — ' . board_name(), 'errors' => []]);
    }

    public function registerSubmit(): void
    {
        if (!Csrf::verify($_POST['csrf'] ?? null)) {
            View::render('auth/register', ['pageTitle' => 'Register', 'errors' => ['Session expired.']]);
            return;
        }
        $cap = \RetroBB\Core\Captcha::verify($_POST);
        if (!$cap['ok']) {
            View::render('auth/register', ['pageTitle' => 'Register', 'errors' => [$cap['error'] ?? 'CAPTCHA failed.']]);
            return;
        }
        $res = User::create(trim((string) ($_POST['username'] ?? '')), trim((string) ($_POST['email'] ?? '')), (string) ($_POST['password'] ?? ''));
        if (!$res['ok']) {
            View::render('auth/register', ['pageTitle' => 'Register', 'errors' => $res['errors']]);
            return;
        }
        $user = User::find($res['id']);
        Auth::login($user);
        $next = (string) ($_GET['next'] ?? '/');
        if (!str_starts_with($next, '/') || str_starts_with($next, '//')) {
            $next = '/';
        }
        redirect($next);
    }

    public function loginForm(): void
    {
        if (Auth::check()) {
            redirect('/');
        }
        View::render('auth/login', ['pageTitle' => 'Log in — ' . board_name(), 'error' => null]);
    }

    public function loginSubmit(): void
    {
        if (!Csrf::verify($_POST['csrf'] ?? null)) {
            View::render('auth/login', ['pageTitle' => 'Log in', 'error' => 'Session expired.']);
            return;
        }
        $login = trim((string) ($_POST['login'] ?? ''));
        $pass = (string) ($_POST['password'] ?? '');
        // IP-backed throttle (the session counter alone resets with cookies).
        // Behind a proxy REMOTE_ADDR is the proxy — see trusted_proxy_header.
        $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
        if ($ip !== '' && self::loginBlocked($ip)) {
            View::render('auth/login', ['pageTitle' => 'Log in', 'error' => 'Invalid login.']);
            return;
        }
        // naive throttle
        $key = 'login_attempts';
        $_SESSION[$key] = ($_SESSION[$key] ?? 0) + 1;
        if ($_SESSION[$key] > 20) {
            View::render('auth/login', ['pageTitle' => 'Log in', 'error' => 'Too many attempts. Wait a bit.']);
            return;
        }
        $user = User::findByLogin($login);
        if (!$user || !\RetroBB\Core\Passwords::verify($pass, $user)) {
            self::loginFailed($ip);
            View::render('auth/login', ['pageTitle' => 'Log in', 'error' => 'Invalid login.']);
            return;
        }
        // Imported accounts carry legacy hashes: upgrade to modern on success.
        if (($user['auth_scheme'] ?? 'modern') !== 'modern') {
            try {
                \RetroBB\Core\Db::pdo()->prepare("UPDATE users SET password_hash=?, auth_scheme='modern', passwd_salt='' WHERE id=?")
                    ->execute([password_hash_safe($pass), $user['id']]);
            } catch (\Throwable) {
                // Pre-011 tables: keep the legacy hash, it still verifies.
            }
        }
        $ban = \RetroBB\Models\Moderation::activeBan((int) $user['id']);
        if ($ban) {
            $msg = 'This account is banned' . ($ban['expires_at'] ? ' until ' . $ban['expires_at'] : ' permanently') . '. Reason: ' . $ban['reason'];
            View::render('auth/login', ['pageTitle' => 'Log in', 'error' => $msg]);
            return;
        }
        $_SESSION[$key] = 0;
        self::loginCleared($ip);
        Auth::login($user);
        $next = (string) ($_POST['next'] ?? $_GET['next'] ?? '/');
        // Local paths only: block "//evil.com" protocol-relative redirects.
        if (!str_starts_with($next, '/') || str_starts_with($next, '//')) {
            $next = '/';
        }
        redirect($next);
    }

    public function logout(): void
    {
        if (!Csrf::verify($_POST['csrf'] ?? null)) {
            redirect('/');
        }
        Auth::logout();
        redirect('/');
    }

    public function forgotForm(): void
    {
        if (Auth::check()) {
            redirect('/');
        }
        View::render('auth/forgot', ['pageTitle' => 'Forgot password', 'sent' => false]);
    }

    public function forgotSubmit(): void
    {
        if (Auth::check()) {
            redirect('/');
        }
        if (!Csrf::verify($_POST['csrf'] ?? null)) {
            View::render('auth/forgot', ['pageTitle' => 'Forgot password', 'sent' => false]);
            return;
        }
        // Session throttle on top of the per-user one in the model.
        $_SESSION['pwreset_attempts'] = ($_SESSION['pwreset_attempts'] ?? 0) + 1;
        if ($_SESSION['pwreset_attempts'] > 10) {
            View::render('auth/forgot', ['pageTitle' => 'Forgot password', 'sent' => true]);
            return;
        }
        $login = trim((string) ($_POST['login'] ?? ''));
        // Same message either way: never reveal whether an account exists.
        $user = $login !== '' ? User::findByLogin($login) : null;
        if ($user) {
            $token = \RetroBB\Models\PasswordReset::issue((int) $user['id']);
            if ($token !== '' && !empty($user['email'])) {
                \RetroBB\Core\Mail::send(
                    $user['email'],
                    'Password reset for ' . board_name(),
                    "Someone asked to reset the password for '" . $user['username'] . "' on " . board_name() . ".\n\n"
                    . \RetroBB\Core\Mail::resetLink($token) . "\n\n"
                    . "The link works once and expires in an hour. Ignore this mail if it wasn't you."
                );
            }
        }
        View::render('auth/forgot', ['pageTitle' => 'Forgot password', 'sent' => true]);
    }

    public function resetForm(): void
    {
        if (Auth::check()) {
            redirect('/');
        }
        $token = (string) ($_GET['token'] ?? '');
        if (!\RetroBB\Models\PasswordReset::check($token)) {
            View::render('auth/forgot', ['pageTitle' => 'Forgot password', 'sent' => false, 'badlink' => true]);
            return;
        }
        View::render('auth/reset', ['pageTitle' => 'Set new password', 'token' => $token, 'error' => null]);
    }

    public function resetSubmit(): void
    {
        if (Auth::check()) {
            redirect('/');
        }
        $token = (string) ($_POST['token'] ?? '');
        if (!Csrf::verify($_POST['csrf'] ?? null)) {
            View::render('auth/reset', ['pageTitle' => 'Set new password', 'token' => $token, 'error' => 'Session expired.']);
            return;
        }
        $pass = (string) ($_POST['password'] ?? '');
        $pass2 = (string) ($_POST['password2'] ?? '');
        if ($pass !== $pass2) {
            View::render('auth/reset', ['pageTitle' => 'Set new password', 'token' => $token, 'error' => 'Passwords do not match.']);
            return;
        }
        $res = \RetroBB\Models\PasswordReset::consume($token, $pass);
        if (!$res['ok']) {
            View::render('auth/forgot', ['pageTitle' => 'Forgot password', 'sent' => false, 'badlink' => true]);
            return;
        }
        $_SESSION['flash_ok'] = 'Password set — log in with the new one.';
        redirect('/login');
    }
}

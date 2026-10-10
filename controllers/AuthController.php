<?php
declare(strict_types=1);

namespace RetroBB\Controllers;

use RetroBB\Core\Auth;
use RetroBB\Core\Csrf;
use RetroBB\Core\View;
use RetroBB\Models\User;

class AuthController
{
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
        // naive throttle
        $key = 'login_attempts';
        $_SESSION[$key] = ($_SESSION[$key] ?? 0) + 1;
        if ($_SESSION[$key] > 20) {
            View::render('auth/login', ['pageTitle' => 'Log in', 'error' => 'Too many attempts. Wait a bit.']);
            return;
        }
        $user = User::findByLogin($login);
        if (!$user || !\RetroBB\Core\Passwords::verify($pass, $user)) {
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
}

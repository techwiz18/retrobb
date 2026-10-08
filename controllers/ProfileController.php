<?php
declare(strict_types=1);

namespace RetroBB\Controllers;

use RetroBB\Core\Slug;
use RetroBB\Core\View;
use RetroBB\Models\User;
use RetroBB\Core\Db;

class ProfileController
{
    public function index(): void
    {
        View::render('profile/index', ['users' => User::all(200), 'pageTitle' => 'Members — ' . board_name()]);
    }

    public function show(string $segment): void
    {
        $parsed = Slug::parseSuffixed($segment, 'u');
        if (!$parsed) {
            http_response_code(404);
            View::render('errors/404', ['path' => '/members/' . $segment]);
            return;
        }
        [$uname, $id] = $parsed;
        $user = User::find($id);
        if (!$user) {
            http_response_code(404);
            View::render('errors/404', ['path' => '/members/' . $segment]);
            return;
        }
        $canonical = Slug::memberUrl($user);
        if (\RetroBB\Core\Router::currentPath() !== $canonical) {
            redirect($canonical, 301);
        }
        $pdo = Db::pdo();
        $st = $pdo->prepare('SELECT t.id, t.title, t.slug, p.created_at FROM posts p JOIN topics t ON t.id=p.topic_id WHERE p.user_id=? ORDER BY p.id DESC LIMIT 10');
        $st->execute([$id]);
        $isMod = \RetroBB\Core\Auth::isMod();
        $me = \RetroBB\Core\Auth::user();
        $isSelf = $me !== null && (int) $me['id'] === $id;
        View::render('profile/show', [
            'profile' => $user,
            'recent' => $st->fetchAll(),
            'warnings' => ($isMod || $isSelf) ? \RetroBB\Models\Moderation::warningsFor($id) : [],
            'activeBan' => ($isMod || $isSelf) ? \RetroBB\Models\Moderation::activeBan($id) : null,
            'pageTitle' => $user['username'] . ' — ' . board_name(),
            'canonical' => canonical_url($canonical),
        ]);
    }

    private function needMod(): bool
    {
        if (!\RetroBB\Core\Auth::isMod()) {
            http_response_code(403);
            \RetroBB\Core\View::render('errors/403', ['pageTitle' => 'Forbidden']);
            return false;
        }
        return true;
    }

    public function warn(int $id): void
    {
        if (!$this->needMod()) {
            return;
        }
        if (!\RetroBB\Core\Csrf::verify($_POST['csrf'] ?? null)) {
            http_response_code(419);
            echo 'CSRF mismatch';
            return;
        }
        $user = User::find($id);
        if (!$user) {
            redirect('/members');
        }
        $reason = trim((string) ($_POST['reason'] ?? ''));
        if (mb_strlen($reason) < 3) {
            $_SESSION['flash_error'] = 'Give a short warning reason.';
            redirect(\RetroBB\Core\Slug::memberUrl($user));
        }
        \RetroBB\Models\Moderation::warn($id, (int) \RetroBB\Core\Auth::user()['id'], $reason);
        $_SESSION['flash_ok'] = 'Warning recorded.';
        redirect(\RetroBB\Core\Slug::memberUrl($user));
    }

    public function ban(int $id): void
    {
        if (!$this->needMod()) {
            return;
        }
        if (!\RetroBB\Core\Csrf::verify($_POST['csrf'] ?? null)) {
            http_response_code(419);
            echo 'CSRF mismatch';
            return;
        }
        $user = User::find($id);
        if (!$user) {
            redirect('/members');
        }
        if ($user['user_group'] === 'admin') {
            $_SESSION['flash_error'] = 'Admins cannot be banned here.';
            redirect(\RetroBB\Core\Slug::memberUrl($user));
        }
        $reason = trim((string) ($_POST['reason'] ?? ''));
        $daysRaw = trim((string) ($_POST['days'] ?? ''));
        $days = $daysRaw === '' ? null : max(1, min(3650, (int) $daysRaw));
        if (mb_strlen($reason) < 3) {
            $_SESSION['flash_error'] = 'Give a short ban reason.';
            redirect(\RetroBB\Core\Slug::memberUrl($user));
        }
        \RetroBB\Models\Moderation::ban($id, (int) \RetroBB\Core\Auth::user()['id'], $reason, $days);
        \RetroBB\Core\Auth::refresh($user);
        $_SESSION['flash_ok'] = 'User banned.';
        redirect(\RetroBB\Core\Slug::memberUrl($user));
    }

    public function unban(int $banId): void
    {
        if (!$this->needMod()) {
            return;
        }
        if (!\RetroBB\Core\Csrf::verify($_POST['csrf'] ?? null)) {
            http_response_code(419);
            echo 'CSRF mismatch';
            return;
        }
        \RetroBB\Models\Moderation::unban($banId, (int) \RetroBB\Core\Auth::user()['id']);
        $_SESSION['flash_ok'] = 'Ban lifted.';
        redirect('/admin/bans');
    }

    public function settingsForm(): void
    {
        $me = \RetroBB\Core\Auth::user();
        if (!$me) {
            redirect('/login?next=' . urlencode('/settings/profile'));
        }
        $user = User::find((int) $me['id']);
        \RetroBB\Core\View::render('profile/edit', [
            'edituser' => $user, 'pageTitle' => 'Edit profile — ' . board_name(),
            'error' => null,
        ]);
    }

    public function settingsSubmit(): void
    {
        $me = \RetroBB\Core\Auth::user();
        if (!$me) {
            redirect('/login');
        }
        $user = User::find((int) $me['id']);
        if (!\RetroBB\Core\Csrf::verify($_POST['csrf'] ?? null)) {
            \RetroBB\Core\View::render('profile/edit', ['edituser' => $user, 'pageTitle' => 'Edit profile', 'error' => 'Session expired.']);
            return;
        }
        $which = (string) ($_POST['form'] ?? 'profile');
        if ($which === 'password') {
            $res = User::changePassword((int) $me['id'], (string) ($_POST['current'] ?? ''), (string) ($_POST['new'] ?? ''));
            if (!$res['ok']) {
                \RetroBB\Core\View::render('profile/edit', ['edituser' => $user, 'pageTitle' => 'Edit profile', 'error' => $res['error']]);
                return;
            }
            $_SESSION['flash_ok'] = 'Password changed.';
            redirect(\RetroBB\Core\Slug::memberUrl($user));
        }
        $res = User::updateProfile((int) $me['id'], trim((string) ($_POST['email'] ?? '')), (string) ($_POST['bio'] ?? ''));
        if (!$res['ok']) {
            \RetroBB\Core\View::render('profile/edit', ['edituser' => $user, 'pageTitle' => 'Edit profile', 'error' => $res['error']]);
            return;
        }
        $_SESSION['flash_ok'] = 'Profile updated.';
        redirect(\RetroBB\Core\Slug::memberUrl(User::find((int) $me['id'])));
    }
}

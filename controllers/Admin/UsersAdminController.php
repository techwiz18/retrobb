<?php
declare(strict_types=1);

namespace RetroBB\Controllers\Admin;

use RetroBB\Core\Auth;
use RetroBB\Core\Guard;
use RetroBB\Core\View;
use RetroBB\Models\User;

/** Members, groups, super grants and bans (issue #47). */
class UsersAdminController
{
    public function usersPage(): void
    {
        Guard::requireAdmin();
        View::render('admin/users', ['users' => User::all(50), 'pageTitle' => 'Users — AdminCP']);
    }

    public function bansPage(): void
    {
        Guard::requireAdmin();
        $perPage = 50;
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $pages = max(1, (int) ceil(\RetroBB\Models\Moderation::banCount() / $perPage));
        if ($page > $pages) {
            $page = $pages;
        }
        View::render('admin/bans', [
            'bans' => \RetroBB\Models\Moderation::banPage($page, $perPage),
            'page' => $page,
            'pages' => $pages,
            'pageTitle' => 'Bans — AdminCP',
        ]);
    }

    public function setGroup(int $id): void
    {
        Guard::requireSuper();
        Guard::csrf('/admin');
        // never demote yourself
        if (Auth::user() && (int) Auth::user()['id'] === $id) {
            redirect('/admin');
        }
        User::setGroup($id, (string) ($_POST['group'] ?? 'member'));
        \RetroBB\Core\Modlog::log((int) Auth::user()['id'], 'setgroup', 'user', $id, '-> ' . (string) ($_POST['group'] ?? 'member'));
        redirect('/admin/users');
    }

    /** Grant/revoke the super tier. Supers manage supers, never themselves. */
    public function superToggle(int $id): void
    {
        Guard::requireSuper();
        Guard::csrf('/admin');
        $me = Auth::user();
        if ($me && (int) $me['id'] === $id) {
            redirect('/admin/users');
        }
        $target = User::find($id);
        if (!$target || ($target['user_group'] ?? '') !== 'admin') {
            redirect('/admin/users');
        }
        $on = !((int) ($target['is_super'] ?? 0) === 1);
        User::setSuper($id, $on);
        \RetroBB\Core\Modlog::log((int) $me['id'], $on ? 'super_grant' : 'super_revoke', 'user', $id, $target['username']);
        redirect('/admin/users');
    }

    public function unban(int $banId): void
    {
        Guard::requireAdmin();
        Guard::csrf('/admin');
        \RetroBB\Models\Moderation::unban($banId, (int) Auth::user()['id'], (string) ($_POST['reason'] ?? ''));
        redirect('/admin/bans');
    }
}

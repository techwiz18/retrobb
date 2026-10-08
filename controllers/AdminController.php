<?php
declare(strict_types=1);

namespace RetroBB\Controllers;

use RetroBB\Core\Auth;
use RetroBB\Core\Csrf;
use RetroBB\Core\Db;
use RetroBB\Core\Slug;
use RetroBB\Core\View;
use RetroBB\Models\User;

class AdminController
{
    private function guard(): void
    {
        if (!Auth::isAdmin()) {
            http_response_code(403);
            View::render('errors/403', ['pageTitle' => 'Forbidden']);
            exit;
        }
    }

    public function index(): void
    {
        $this->guard();
        $pdo = Db::pdo();
        $cats = $pdo->query('SELECT * FROM categories ORDER BY sort')->fetchAll();
        foreach ($cats as &$c) {
            $st = $pdo->prepare('SELECT * FROM forums WHERE category_id=? ORDER BY sort');
            $st->execute([$c['id']]);
            $c['forums'] = $st->fetchAll();
        }
        $users = User::all(50);
        $settings = $pdo->query('SELECT * FROM settings')->fetchAll();
        $modpage = max(1, (int) ($_GET['modpage'] ?? 1));
        $modlog = \RetroBB\Core\Modlog::latest($modpage, 50);
        $modpages = max(1, (int) ceil($modlog['total'] / 50));
        View::render('admin/index', [
            'cats' => $cats, 'users' => $users, 'settings' => $settings,
            'bans' => \RetroBB\Models\Moderation::banList(50),
            'modlog' => $modlog['entries'], 'modtotal' => $modlog['total'],
            'modpage' => $modpage, 'modpages' => $modpages,
            'openReports' => \RetroBB\Models\Report::openCount(),
            'pageTitle' => 'AdminCP — ' . board_name(),
        ]);
    }

    public function saveSettings(): void
    {
        $this->guard();
        if (!Csrf::verify($_POST['csrf'] ?? null)) {
            redirect('/admin');
        }
        $allowed = ['board_name', 'board_tagline', 'default_skin', 'posts_per_page', 'topics_per_page',
            'flood_seconds', 'edit_window_mins', 'captcha_provider', 'captcha_sitekey', 'captcha_secret'];
        $pdo = Db::pdo();
        foreach ($allowed as $k) {
            if (isset($_POST[$k])) {
                $v = substr(trim((string) $_POST[$k]), 0, 500);
                if (in_array($k, ['posts_per_page', 'topics_per_page'], true)) {
                    $v = (string) max(5, min(50, (int) $v));
                }
                if (in_array($k, ['flood_seconds', 'edit_window_mins'], true)) {
                    $v = (string) max(0, min(3600, (int) $v));
                }
                if ($k === 'captcha_provider' && !in_array($v, ['honeypot', 'builtin', 'turnstile', 'hcaptcha', 'recaptcha'], true)) {
                    continue;
                }
                // Portable upsert (works on SQLite and MySQL alike).
                $upd = $pdo->prepare('UPDATE settings SET `value`=? WHERE `key`=?');
                $upd->execute([$v, $k]);
                if ($upd->rowCount() === 0) {
                    $pdo->prepare('INSERT INTO settings (`key`, `value`) VALUES (?, ?)')->execute([$k, $v]);
                }
            }
        }
        redirect('/admin?saved=1');
    }

    public function addForum(): void
    {
        $this->guard();
        if (!Csrf::verify($_POST['csrf'] ?? null)) {
            redirect('/admin');
        }
        $pdo = Db::pdo();
        $cat = (int) ($_POST['category_id'] ?? 0);
        $name = trim((string) ($_POST['name'] ?? ''));
        $desc = trim((string) ($_POST['description'] ?? ''));
        if ($cat > 0 && $name !== '') {
            $pdo->prepare('INSERT INTO forums (category_id, name, slug, description, sort) VALUES (?,?,?,?,0)')->execute([$cat, $name, Slug::make($name), $desc]);
        }
        redirect('/admin');
    }

    public function addCategory(): void
    {
        $this->guard();
        if (!Csrf::verify($_POST['csrf'] ?? null)) {
            redirect('/admin');
        }
        $title = trim((string) ($_POST['title'] ?? ''));
        if ($title !== '') {
            Db::pdo()->prepare('INSERT INTO categories (title, sort) VALUES (?,0)')->execute([$title]);
        }
        redirect('/admin');
    }

    public function setGroup(int $id): void
    {
        $this->guard();
        if (!Csrf::verify($_POST['csrf'] ?? null)) {
            redirect('/admin');
        }
        // never demote yourself
        if (Auth::user() && (int) Auth::user()['id'] === $id) {
            redirect('/admin');
        }
        User::setGroup($id, (string) ($_POST['group'] ?? 'member'));
        redirect('/admin');
    }

    public function moveCategory(int $id, string $dir): void
    {
        $this->guard();
        if (!Csrf::verify($_POST['csrf'] ?? null)) {
            redirect('/admin');
        }
        \RetroBB\Models\Board::moveCategory($id, $dir === 'up' ? -1 : 1);
        redirect('/admin#structure');
    }

    public function moveForum(int $id, string $dir): void
    {
        $this->guard();
        if (!Csrf::verify($_POST['csrf'] ?? null)) {
            redirect('/admin');
        }
        \RetroBB\Models\Board::moveForum($id, $dir === 'up' ? -1 : 1);
        redirect('/admin#structure');
    }

    public function unban(int $banId): void
    {
        $this->guard();
        if (!Csrf::verify($_POST['csrf'] ?? null)) {
            redirect('/admin');
        }
        \RetroBB\Models\Moderation::unban($banId, (int) Auth::user()['id']);
        redirect('/admin#bans');
    }
}

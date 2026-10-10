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

    private function cats(): array
    {
        $pdo = Db::pdo();
        $cats = $pdo->query('SELECT * FROM categories ORDER BY sort, id')->fetchAll();
        foreach ($cats as &$c) {
            $st = $pdo->prepare('SELECT * FROM forums WHERE category_id=? ORDER BY sort, id');
            $st->execute([$c['id']]);
            $c['forums'] = $st->fetchAll();
        }
        return $cats;
    }

    private function settingsRows(): array
    {
        return Db::pdo()->query('SELECT * FROM settings')->fetchAll();
    }

    public function index(): void
    {
        $this->guard();
        $bans = \RetroBB\Models\Moderation::banList(100);
        $active = 0;
        foreach ($bans as $b) {
            if (empty($b['lifted_at']) && (empty($b['expires_at']) || strtotime($b['expires_at']) > time())) {
                $active++;
            }
        }
        View::render('admin/dashboard', [
            'stats' => \RetroBB\Models\Board::stats(),
            'openReports' => \RetroBB\Models\Report::openCount(),
            'activeBans' => $active,
            'pageTitle' => 'AdminCP — ' . board_name(),
        ]);
    }

    public function settingsPage(): void
    {
        $this->guard();
        View::render('admin/settings', ['settings' => $this->settingsRows(), 'pageTitle' => 'Settings — AdminCP']);
    }

    public function featuresPage(): void
    {
        $this->guard();
        View::render('admin/features', ['settings' => $this->settingsRows(), 'pageTitle' => 'Features — AdminCP']);
    }

    public function spamPage(): void
    {
        $this->guard();
        View::render('admin/spam', ['settings' => $this->settingsRows(), 'pageTitle' => 'Spam protection — AdminCP']);
    }

    public function structurePage(): void
    {
        $this->guard();
        View::render('admin/structure', ['cats' => $this->cats(), 'pageTitle' => 'Structure — AdminCP']);
    }

    public function bansPage(): void
    {
        $this->guard();
        View::render('admin/bans', ['bans' => \RetroBB\Models\Moderation::banList(100), 'pageTitle' => 'Bans — AdminCP']);
    }

    public function usersPage(): void
    {
        $this->guard();
        View::render('admin/users', ['users' => User::all(50), 'pageTitle' => 'Users — AdminCP']);
    }

    public function modlogPage(): void
    {
        $this->guard();
        $modpage = max(1, (int) ($_GET['modpage'] ?? 1));
        $modlog = \RetroBB\Core\Modlog::latest($modpage, 50);
        View::render('admin/modlog', [
            'modlog' => $modlog['entries'], 'modtotal' => $modlog['total'],
            'modpage' => $modpage, 'modpages' => max(1, (int) ceil($modlog['total'] / 50)),
            'pageTitle' => 'Mod log — AdminCP',
        ]);
    }

    public function saveSettings(): void
    {
        $this->guard();
        if (!Csrf::verify($_POST['csrf'] ?? null)) {
            redirect('/admin');
        }
        $allowed = ['board_name', 'board_tagline', 'posts_per_page', 'topics_per_page',
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
                // Native upsert (MySQL reports rows-changed, so UPDATE-then-check misfires).
                $pdo->prepare('INSERT INTO settings (`key`, `value`) VALUES (?, ?) ON DUPLICATE KEY UPDATE `value`=VALUES(`value`)')->execute([$k, $v]);
            }
        }
        redirect($this->settingsReturn());
    }

    public function saveFeatures(): void
    {
        $this->guard();
        if (!Csrf::verify($_POST['csrf'] ?? null)) {
            redirect('/admin');
        }
        $pdo = Db::pdo();
        $upsert = $pdo->prepare('INSERT INTO settings (`key`, `value`) VALUES (?, ?) ON DUPLICATE KEY UPDATE `value`=VALUES(`value`)');
        foreach (['feature_alerts', 'feature_mentions', 'feature_reactions', 'feature_pms', 'skin_selector'] as $k) {
            $upsert->execute([$k, isset($_POST[$k]) ? '1' : '0']);
        }
        foreach (['theme_light', 'theme_dark', 'theme_auto'] as $k) {
            $upsert->execute([$k, isset($_POST[$k]) ? '1' : '0']);
        }
        // At least one theme mode must stay on, or members get a broken picker.
        if (!isset($_POST['theme_light']) && !isset($_POST['theme_dark']) && !isset($_POST['theme_auto'])) {
            $upsert->execute(['theme_auto', '1']);
        }
        $effectiveThemes = array_values(array_filter(
            ['light', 'dark', 'auto'],
            fn($m) => isset($_POST['theme_' . $m])
        ));
        if (!$effectiveThemes) {
            $effectiveThemes = ['auto'];
        }
        $skin = (string) ($_POST['default_skin'] ?? 'classic');
        $upsert->execute(['default_skin', in_array($skin, ['classic', 'midnight', 'silver'], true) ? $skin : 'classic']);
        $thm = (string) ($_POST['default_theme'] ?? 'auto');
        if (!in_array($thm, ['light', 'dark', 'auto'], true)) {
            $thm = 'auto';
        }
        // A default nobody may pick is a contradiction: coerce it to an
        // allowed mode (prefer auto) and tell the admin it happened.
        $fixedTheme = false;
        if (!in_array($thm, $effectiveThemes, true)) {
            foreach (['auto', 'light', 'dark'] as $m) {
                if (in_array($m, $effectiveThemes, true)) {
                    $thm = $m;
                    break;
                }
            }
            $fixedTheme = true;
        }
        $upsert->execute(['default_theme', $thm]);
        redirect('/admin/features?saved=1' . ($fixedTheme ? '&fixed-theme=1' : ''));
    }

    /** Where a settings POST should land: /admin/settings or /admin/spam. */
    private function settingsReturn(): string
    {
        $to = (string) ($_POST['return'] ?? 'settings');
        return $to === 'spam' ? '/admin/spam?saved=1' : '/admin/settings?saved=1';
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
        redirect('/admin/structure');
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
        redirect('/admin/structure');
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
        \RetroBB\Core\Modlog::log((int) Auth::user()['id'], 'setgroup', 'user', $id, '-> ' . (string) ($_POST['group'] ?? 'member'));
        redirect('/admin/users');
    }

    public function moveCategory(int $id, string $dir): void
    {
        $this->guard();
        if (!Csrf::verify($_POST['csrf'] ?? null)) {
            redirect('/admin');
        }
        \RetroBB\Models\Board::moveCategory($id, $dir === 'up' ? -1 : 1);
        redirect('/admin/structure');
    }

    public function moveForum(int $id, string $dir): void
    {
        $this->guard();
        if (!Csrf::verify($_POST['csrf'] ?? null)) {
            redirect('/admin');
        }
        \RetroBB\Models\Board::moveForum($id, $dir === 'up' ? -1 : 1);
        redirect('/admin/structure');
    }

    public function unban(int $banId): void
    {
        $this->guard();
        if (!Csrf::verify($_POST['csrf'] ?? null)) {
            redirect('/admin');
        }
        \RetroBB\Models\Moderation::unban($banId, (int) Auth::user()['id']);
        redirect('/admin/bans');
    }

    public function editCategoryForm(int $id): void
    {
        $this->guard();
        $cat = \RetroBB\Models\Board::category($id);
        if (!$cat) {
            http_response_code(404);
            View::render('errors/404', ['path' => '/admin/category/' . $id . '/edit']);
            return;
        }
        View::render('admin/edit-category', ['cat' => $cat, 'error' => null, 'pageTitle' => 'Edit category — AdminCP']);
    }

    public function renameCategory(int $id): void
    {
        $this->guard();
        if (!Csrf::verify($_POST['csrf'] ?? null)) {
            redirect('/admin');
        }
        $res = \RetroBB\Models\Board::renameCategory($id, (string) ($_POST['title'] ?? ''));
        if (!$res['ok']) {
            $cat = \RetroBB\Models\Board::category($id);
            if (!$cat) {
                redirect('/admin/structure');
            }
            View::render('admin/edit-category', ['cat' => $cat, 'error' => $res['error'], 'pageTitle' => 'Edit category']);
            return;
        }
        \RetroBB\Core\Modlog::log((int) Auth::user()['id'], 'rename', 'category', $id, mb_substr(trim((string) ($_POST['title'] ?? '')), 0, 150));
        $_SESSION['flash_ok'] = 'Category renamed.';
        redirect('/admin/structure');
    }

    public function deleteCategory(int $id): void
    {
        $this->guard();
        if (!Csrf::verify($_POST['csrf'] ?? null)) {
            redirect('/admin');
        }
        $cat = \RetroBB\Models\Board::category($id);
        $res = \RetroBB\Models\Board::deleteCategory($id);
        if (!$res['ok']) {
            $_SESSION['flash_error'] = $res['error'];
            redirect('/admin/structure');
        }
        \RetroBB\Core\Modlog::log((int) Auth::user()['id'], 'delete', 'category', $id, $cat ? mb_substr($cat['title'], 0, 150) : '');
        $_SESSION['flash_ok'] = 'Category deleted.';
        redirect('/admin/structure');
    }

    public function editForumForm(int $id): void
    {
        $this->guard();
        $forum = \RetroBB\Models\Board::forum($id);
        if (!$forum) {
            http_response_code(404);
            View::render('errors/404', ['path' => '/admin/forum/' . $id . '/edit']);
            return;
        }
        View::render('admin/edit-forum', [
            'forum' => $forum, 'cats' => $this->cats(), 'error' => null,
            'pageTitle' => 'Edit forum — AdminCP',
        ]);
    }

    public function renameForum(int $id): void
    {
        $this->guard();
        if (!Csrf::verify($_POST['csrf'] ?? null)) {
            redirect('/admin');
        }
        $res = \RetroBB\Models\Board::renameForum(
            $id,
            (string) ($_POST['name'] ?? ''),
            (string) ($_POST['description'] ?? ''),
            (int) ($_POST['category_id'] ?? 0)
        );
        if (!$res['ok']) {
            $forum = \RetroBB\Models\Board::forum($id);
            if (!$forum) {
                redirect('/admin/structure');
            }
            View::render('admin/edit-forum', [
                'forum' => $forum, 'cats' => $this->cats(), 'error' => $res['error'],
                'pageTitle' => 'Edit forum',
            ]);
            return;
        }
        \RetroBB\Core\Modlog::log((int) Auth::user()['id'], 'rename', 'forum', $id, mb_substr(trim((string) ($_POST['name'] ?? '')), 0, 150));
        $_SESSION['flash_ok'] = 'Forum saved.';
        redirect('/admin/structure');
    }

    public function deleteForum(int $id): void
    {
        $this->guard();
        if (!Csrf::verify($_POST['csrf'] ?? null)) {
            redirect('/admin');
        }
        $forum = \RetroBB\Models\Board::forum($id);
        $res = \RetroBB\Models\Board::deleteForum($id);
        if (!$res['ok']) {
            $_SESSION['flash_error'] = $res['error'];
            redirect('/admin/structure');
        }
        \RetroBB\Core\Modlog::log((int) Auth::user()['id'], 'delete', 'forum', $id, $forum ? mb_substr($forum['name'], 0, 150) : '');
        $_SESSION['flash_ok'] = 'Forum deleted.';
        redirect('/admin/structure');
    }
}

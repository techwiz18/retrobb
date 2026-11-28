<?php
declare(strict_types=1);

namespace RetroBB\Controllers\Admin;

use RetroBB\Core\Db;
use RetroBB\Core\Guard;
use RetroBB\Core\Slug;
use RetroBB\Core\View;
use RetroBB\Core\Auth;

/** Forum/category structure: the only AdminCP module that writes board
 *  layout (issue #47). */
class StructureAdminController
{
    /** @return array */
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

    public function structurePage(): void
    {
        Guard::requireAdmin();
        View::render('admin/structure', ['cats' => $this->cats(), 'pageTitle' => 'Structure — AdminCP']);
    }

    public function addForum(): void
    {
        Guard::requireAdmin();
        Guard::csrf('/admin');
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
        Guard::requireAdmin();
        Guard::csrf('/admin');
        $title = trim((string) ($_POST['title'] ?? ''));
        if ($title !== '') {
            Db::pdo()->prepare('INSERT INTO categories (title, sort) VALUES (?,0)')->execute([$title]);
        }
        redirect('/admin/structure');
    }

    public function moveCategory(int $id, string $dir): void
    {
        Guard::requireAdmin();
        Guard::csrf('/admin');
        \RetroBB\Models\Board::moveCategory($id, $dir === 'up' ? -1 : 1);
        redirect('/admin/structure');
    }

    public function moveForum(int $id, string $dir): void
    {
        Guard::requireAdmin();
        Guard::csrf('/admin');
        \RetroBB\Models\Board::moveForum($id, $dir === 'up' ? -1 : 1);
        redirect('/admin/structure');
    }

    public function editCategoryForm(int $id): void
    {
        Guard::requireAdmin();
        $cat = \RetroBB\Models\Board::category($id);
        if (!$cat) {
            Guard::notFound('/admin/category/' . $id . '/edit');
        }
        View::render('admin/edit-category', ['cat' => $cat, 'error' => null, 'pageTitle' => 'Edit category — AdminCP']);
    }

    public function renameCategory(int $id): void
    {
        Guard::requireAdmin();
        Guard::csrf('/admin');
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
        Guard::requireAdmin();
        Guard::csrf('/admin');
        $cat = \RetroBB\Models\Board::category($id);
        $res = \RetroBB\Models\Board::deleteCategory($id);
        if (!$res['ok']) {
            Guard::flashThenReturn('/admin/structure', $res['error']);
        }
        \RetroBB\Core\Modlog::log((int) Auth::user()['id'], 'delete', 'category', $id, $cat ? mb_substr($cat['title'], 0, 150) : '');
        $_SESSION['flash_ok'] = 'Category deleted.';
        redirect('/admin/structure');
    }

    public function editForumForm(int $id): void
    {
        Guard::requireAdmin();
        $forum = \RetroBB\Models\Board::forum($id);
        if (!$forum) {
            Guard::notFound('/admin/forum/' . $id . '/edit');
        }
        View::render('admin/edit-forum', [
            'forum' => $forum, 'cats' => $this->cats(), 'error' => null,
            'pageTitle' => 'Edit forum — AdminCP',
        ]);
    }

    public function renameForum(int $id): void
    {
        Guard::requireAdmin();
        Guard::csrf('/admin');
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
        Guard::requireAdmin();
        Guard::csrf('/admin');
        $forum = \RetroBB\Models\Board::forum($id);
        $res = \RetroBB\Models\Board::deleteForum($id);
        if (!$res['ok']) {
            Guard::flashThenReturn('/admin/structure', $res['error']);
        }
        \RetroBB\Core\Modlog::log((int) Auth::user()['id'], 'delete', 'forum', $id, $forum ? mb_substr($forum['name'], 0, 150) : '');
        $_SESSION['flash_ok'] = 'Forum deleted.';
        redirect('/admin/structure');
    }
}

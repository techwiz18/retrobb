<?php
declare(strict_types=1);

namespace RetroBB\Controllers;

use RetroBB\Core\Auth;
use RetroBB\Core\Csrf;
use RetroBB\Core\Modlog;
use RetroBB\Core\Slug;
use RetroBB\Core\View;
use RetroBB\Models\Post;
use RetroBB\Models\Report;

class ReportController
{
    public function reportForm(int $postId): void
    {
        if (!Auth::check()) {
            redirect('/login?next=' . urlencode('/post/' . $postId . '/report'));
        }
        $post = Post::find($postId);
        if (!$post) {
            http_response_code(404);
            View::render('errors/404', ['path' => '/post/' . $postId . '/report']);
            return;
        }
        View::render('report/new', ['post' => $post, 'pageTitle' => 'Report post — ' . board_name(), 'error' => null]);
    }

    public function reportSubmit(int $postId): void
    {
        if (!Auth::check()) {
            redirect('/login');
        }
        $post = Post::find($postId);
        if (!$post) {
            http_response_code(404);
            View::render('errors/404', ['path' => '/post/' . $postId . '/report']);
            return;
        }
        if (!Csrf::verify($_POST['csrf'] ?? null)) {
            View::render('report/new', ['post' => $post, 'pageTitle' => 'Report post', 'error' => 'Session expired.']);
            return;
        }
        if ((int) $post['user_id'] === (int) Auth::user()['id']) {
            View::render('report/new', ['post' => $post, 'pageTitle' => 'Report post', 'error' => 'You cannot report your own post.']);
            return;
        }
        $res = Report::create($postId, (int) $post['topic_id'], (int) Auth::user()['id'], (string) ($_POST['reason'] ?? ''));
        if (!$res['ok']) {
            View::render('report/new', ['post' => $post, 'pageTitle' => 'Report post', 'error' => $res['error']]);
            return;
        }
        $_SESSION['flash_ok'] = 'Thanks — the moderators will take a look.';
        redirect(Slug::topicUrl(['id' => $post['topic_id'], 'title' => $post['topic_title']]) . '#p' . $postId);
    }

    public function queue(): void
    {
        if (!Auth::isMod()) {
            http_response_code(403);
            View::render('errors/403', ['pageTitle' => 'Forbidden']);
            return;
        }
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $data = Report::openList($page);
        $pages = max(1, (int) ceil($data['total'] / 25));
        View::render('report/queue', [
            'reports' => $data['reports'], 'total' => $data['total'],
            'page' => $page, 'pages' => $pages,
            'pageTitle' => 'Mod queue — ' . board_name(),
        ]);
    }

    public function handle(int $id): void
    {
        if (!Auth::isMod()) {
            http_response_code(403);
            echo 'Forbidden';
            return;
        }
        if (!Csrf::verify($_POST['csrf'] ?? null)) {
            http_response_code(419);
            echo 'CSRF mismatch';
            return;
        }
        $rep = Report::find($id);
        if ($rep && $rep['status'] === 'open') {
            $status = ($_POST['status'] ?? '') === 'resolved' ? 'resolved' : 'dismissed';
            Report::handle($id, (int) Auth::user()['id'], $status, (string) ($_POST['note'] ?? ''));
            Modlog::log((int) Auth::user()['id'], 'report_' . $status, 'report', $id, "post {$rep['post_id']}");
        }
        redirect('/mod/reports');
    }
}

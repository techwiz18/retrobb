<?php
declare(strict_types=1);

namespace RetroBB\Controllers;

use RetroBB\Core\Auth;
use RetroBB\Core\Csrf;
use RetroBB\Core\Modlog;
use RetroBB\Core\Router;
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
            // AdminCP entry (/admin/reports) keeps the admin chrome; the
            // ModCP path keeps the mod chrome. Same content either way.
            'admin' => $this->isAdminContext(),
            'pageTitle' => 'Mod queue — ' . board_name(),
        ]);
    }

    /** True when the request lives under /admin (path or referer), so error
        re-renders of shared POST endpoints keep the caller's chrome. */
    private function isAdminContext(): bool
    {
        if (str_starts_with(Router::currentPath(), '/admin/')) {
            return true;
        }
        $ref = $_SERVER['HTTP_REFERER'] ?? '';
        $path = is_string($ref) && $ref !== '' ? (string) (parse_url($ref, PHP_URL_PATH) ?? '') : '';
        return str_starts_with($path, '/admin/');
    }

    /** Back to the queue the caller came from (AdminCP or ModCP). */
    private function queueReturn(): void
    {
        // Prefix allowlist only: never follow an open referer anywhere
        // else (same rule as the skin/theme switchers). Any /admin/* source
        // returns to the AdminCP queue (form pages resolve away, so only
        // the queue itself keeps its ?page=).
        $parts = parse_url($_SERVER['HTTP_REFERER'] ?? '');
        $path = is_array($parts) ? (string) ($parts['path'] ?? '') : '';
        $admin = str_starts_with($path, '/admin/');
        $onQueue = $path === '/admin/reports' || $path === '/mod/reports';
        if ($admin || $onQueue || str_starts_with($path, '/mod/report/')) {
            $qs = ($onQueue && is_array($parts) && ($parts['query'] ?? '') !== '') ? '?' . $parts['query'] : '';
            redirect(($admin ? '/admin/reports' : '/mod/reports') . $qs);
        }
        redirect('/mod/reports');
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
            $note = mb_substr(trim((string) ($_POST['note'] ?? '')), 0, 500);
            // Resolving claims action was taken: a note is required so the
            // log says what happened. Dismissing stays one click.
            if ($status === 'resolved' && $note === '') {
                $this->resolveForm($id, 'Tell us what action you took before resolving.');
                return;
            }
            Report::handle($id, (int) Auth::user()['id'], $status, $note);
            Modlog::log((int) Auth::user()['id'], 'report_' . $status, 'report', $id, "post {$rep['post_id']}" . ($note !== '' ? ': ' . mb_substr($note, 0, 150) : ''));
        }
        $this->queueReturn();
    }

    /** Resolve form (GET): what did you do about it? Note required on submit. */
    public function resolveForm(int $id, ?string $error = null): void
    {
        if (!Auth::isMod()) {
            http_response_code(403);
            View::render('errors/403', ['pageTitle' => 'Forbidden']);
            return;
        }
        $rep = Report::find($id);
        if (!$rep || $rep['status'] !== 'open') {
            $this->queueReturn();
        }
        $post = Post::find((int) $rep['post_id']);
        $topic = $post ? \RetroBB\Models\Topic::find((int) $post['topic_id']) : null;
        View::render('report/resolve', [
            'report' => $rep, 'post' => $post, 'topic' => $topic, 'error' => $error,
            'admin' => $this->isAdminContext(),
            'pageTitle' => 'Resolve report — ' . board_name(),
        ]);
    }

    private function openReport(int $id): ?array
    {
        if (!Auth::isMod()) {
            http_response_code(403);
            echo 'Forbidden';
            return null;
        }
        if (!Csrf::verify($_POST['csrf'] ?? null)) {
            http_response_code(419);
            echo 'CSRF mismatch';
            return null;
        }
        $rep = Report::find($id);
        return ($rep && $rep['status'] === 'open') ? $rep : null;
    }

    /** Delete the reported post and resolve the report. */
    public function deletePost(int $id): void
    {
        $rep = $this->openReport($id);
        if ($rep === null) {
            $this->queueReturn();
        }
        $me = (int) Auth::user()['id'];
        $post = Post::find((int) $rep['post_id']);
        if ($post && (int) $post['user_id'] !== $me && ($post['user_group'] ?? 'member') !== 'member' && !Auth::isSuper()) {
            $_SESSION['flash_error'] = 'Only super admins can delete staff posts.';
            $this->queueReturn();
        }
        $topicId = $post ? Post::delete((int) $rep['post_id']) : null;
        Report::handle($id, $me, 'resolved', 'reported post deleted');
        Modlog::log($me, 'delete', 'post', (int) $rep['post_id'], 'via report #' . $id);
        $_SESSION['flash_ok'] = 'Reported post deleted.';
        if ($topicId !== null) {
            $topic = \RetroBB\Models\Topic::find($topicId);
            redirect($topic ? Slug::topicUrl($topic) : '/');
        }
        $this->queueReturn();
    }

    /** Warn form (GET): the mod writes the actual warning, not the report text. */
    public function warnForm(int $id, ?string $error = null): void
    {
        if (!Auth::isMod()) {
            http_response_code(403);
            View::render('errors/403', ['pageTitle' => 'Forbidden']);
            return;
        }
        $rep = Report::find($id);
        if (!$rep || $rep['status'] !== 'open') {
            $this->queueReturn();
        }
        $post = Post::find((int) $rep['post_id']);
        $topic = $post ? \RetroBB\Models\Topic::find((int) $post['topic_id']) : null;
        View::render('report/warn', [
            'report' => $rep, 'post' => $post, 'topic' => $topic, 'error' => $error,
            'admin' => $this->isAdminContext(),
            'pageTitle' => 'Warn author — ' . board_name(),
        ]);
    }

    /** Warn the reported post's author and resolve the report. */
    public function warnAuthor(int $id): void
    {
        $rep = $this->openReport($id);
        if ($rep === null) {
            $this->queueReturn();
        }
        $reason = mb_substr(trim((string) ($_POST['reason'] ?? '')), 0, 500);
        if (mb_strlen($reason) < 3) {
            $this->warnForm($id, 'Write the warning reason (3+ chars) — the user sees it.');
            return;
        }
        $me = (int) Auth::user()['id'];
        $post = Post::find((int) $rep['post_id']);
        if ($post) {
            $ok = \RetroBB\Models\Moderation::warn((int) $post['user_id'], $me, $reason, (int) $post['id'], (int) $post['topic_id']);
            if (!$ok) {
                $this->warnForm($id, 'That author cannot be warned — dismiss or delete the post instead.');
                return;
            }
        }
        Report::handle($id, $me, 'resolved', 'author warned: ' . mb_substr($reason, 0, 200));
        Modlog::log($me, 'report_resolved', 'report', $id, 'author warned');
        $_SESSION['flash_ok'] = 'Author warned.';
        $this->queueReturn();
    }
}

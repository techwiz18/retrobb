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

    /** Run $fn with the report row locked; bounce to the queue unless open.
        Serializes two mods handling the same report: the loser sees a
        non-open row and lands back on the queue with nothing double-done. */
    private function withLockedReport(int $id, callable $fn): void
    {
        $pdo = \RetroBB\Core\Db::pdo();
        $pdo->beginTransaction();
        try {
            $st = $pdo->prepare('SELECT * FROM reports WHERE id=? FOR UPDATE');
            $st->execute([$id]);
            $rep = $st->fetch() ?: null;
            if (!$rep || $rep['status'] !== 'open') {
                $pdo->rollBack();
                $this->queueReturn();
            }
            $fn($rep);
            if ($pdo->inTransaction()) {
                $pdo->commit();
            }
        } catch (\Throwable $t) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $t;
        }
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
        $status = ($_POST['status'] ?? '') === 'resolved' ? 'resolved' : 'dismissed';
        $note = mb_substr(trim((string) ($_POST['note'] ?? '')), 0, 500);
        // Resolving claims action was taken: a note is required so the
        // log says what happened. Dismissing stays one click. Validated
        // before the lock: the form render below must not fall through
        // to queueReturn().
        if ($status === 'resolved' && $note === '') {
            $this->resolveForm($id, 'Tell us what action you took before resolving.');
            return;
        }
        $this->withLockedReport($id, function (array $rep) use ($id, $status, $note): void {
            Report::handle($id, (int) Auth::user()['id'], $status, $note);
            Modlog::log((int) Auth::user()['id'], 'report_' . $status, 'report', $id, "post {$rep['post_id']}" . ($note !== '' ? ': ' . mb_substr($note, 0, 150) : ''));
        });
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

    /** Delete the reported post and resolve the report. */
    public function deletePost(int $id): void
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
        // Redirect target escapes the closure: redirect() exits, and exiting
        // inside the lock would roll the delete back.
        $goto = null;
        $this->withLockedReport($id, function (array $rep) use ($id, &$goto): void {
            $me = (int) Auth::user()['id'];
            $post = Post::find((int) $rep['post_id']);
            if ($post && ($err = \RetroBB\Models\Moderation::staffPostError($post, $me, Auth::isSuper(), 'delete')) !== null) {
                $_SESSION['flash_error'] = $err;
                return;
            }
            $topicId = $post ? Post::delete((int) $rep['post_id']) : null;
            Report::handle($id, $me, 'resolved', 'reported post deleted');
            Modlog::log($me, 'delete', 'post', (int) $rep['post_id'], 'via report #' . $id);
            $_SESSION['flash_ok'] = 'Reported post deleted.';
            if ($topicId !== null) {
                $topic = \RetroBB\Models\Topic::find($topicId);
                $goto = $topic ? Slug::topicUrl($topic) : '/';
            }
        });
        if ($goto !== null) {
            redirect($goto);
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
        $reason = mb_substr(trim((string) ($_POST['reason'] ?? '')), 0, 500);
        if (mb_strlen($reason) < 3) {
            $this->warnForm($id, 'Write the warning reason (3+ chars) — the user sees it.');
            return;
        }
        // warnForm renders (no redirect), so it must skip the queueReturn
        // below — everything else lands back on the queue.
        $shown = false;
        $this->withLockedReport($id, function (array $rep) use ($id, $reason, &$shown): void {
            $me = (int) Auth::user()['id'];
            $post = Post::find((int) $rep['post_id']);
            if ($post) {
                $ok = \RetroBB\Models\Moderation::warn((int) $post['user_id'], $me, $reason, (int) $post['id'], (int) $post['topic_id']);
                if (!$ok) {
                    $this->warnForm($id, 'That author cannot be warned — dismiss or delete the post instead.');
                    $shown = true;
                    return;
                }
            }
            Report::handle($id, $me, 'resolved', 'author warned: ' . mb_substr($reason, 0, 200));
            Modlog::log($me, 'report_resolved', 'report', $id, 'author warned');
            $_SESSION['flash_ok'] = 'Author warned.';
        });
        if (!$shown) {
            $this->queueReturn();
        }
    }
}

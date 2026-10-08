<?php
declare(strict_types=1);

namespace RetroBB\Controllers;

use RetroBB\Core\Auth;
use RetroBB\Core\Csrf;
use RetroBB\Core\Slug;
use RetroBB\Core\View;
use RetroBB\Models\Board;
use RetroBB\Models\Topic;

class TopicController
{
    public function show(string $segment): void
    {
        $parsed = Slug::parseSuffixed($segment, 't');
        if (!$parsed) {
            http_response_code(404);
            View::render('errors/404', ['path' => '/topic/' . $segment]);
            return;
        }
        [$slug, $id] = $parsed;
        $topic = Topic::find($id);
        if (!$topic) {
            http_response_code(404);
            View::render('errors/404', ['path' => '/topic/' . $segment]);
            return;
        }
        $canonical = Slug::topicUrl($topic);
        $path = \RetroBB\Core\Router::currentPath();
        $base = explode('?', $path)[0];
        // strip /page-N suffix for canonical check
        $baseNoPage = (string) preg_replace('#/page-\d+$#', '', $base);
        if ($baseNoPage !== $canonical) {
            redirect($canonical, 301);
        }
        Topic::bumpViews($id);

        $page = 1;
        if (preg_match('#/page-(\d+)$#', $base, $m)) {
            $page = max(1, (int) $m[1]);
        } elseif (isset($_GET['page']) && (int) $_GET['page'] > 1) {
            // Unify on the pretty /page-N form so ?page= URLs don't duplicate content.
            redirect($canonical . '/page-' . (int) $_GET['page'], 301);
        } elseif (isset($_GET['page'])) {
            $page = max(1, (int) $_GET['page']);
        }
        $perPage = max(5, min(50, (int) setting('posts_per_page', '15')));
        $data = Topic::posts($id, $page, $perPage);
        $pages = max(1, (int) ceil($data['total'] / $perPage));
        $forum = Board::forum((int) $topic['forum_id']);

        View::render('topic/show', [
            'topic' => $topic,
            'forum' => $forum,
            'posts' => $data['posts'],
            'total' => $data['total'],
            'page' => $page,
            'pages' => $pages,
            'perPage' => $perPage,
            'pageTitle' => $topic['title'] . ' — ' . board_name(),
            'metaDesc' => \RetroBB\Core\BBCode::excerpt($data['posts'][0]['body_bbcode'] ?? $topic['title']),
            'canonical' => canonical_url($canonical . ($page > 1 ? '/page-' . $page : '')),
        ]);
    }

    public function newForm(int $forumId): void
    {
        if (!Auth::check()) {
            redirect('/login?next=' . urlencode('/new-topic/' . $forumId));
        }
        $forum = Board::forum($forumId);
        if (!$forum) {
            http_response_code(404);
            View::render('errors/404', ['path' => '/new-topic/' . $forumId]);
            return;
        }
        View::render('topic/new', ['forum' => $forum, 'pageTitle' => 'New topic — ' . board_name(), 'error' => null]);
    }

    public function newSubmit(int $forumId): void
    {
        if (!Auth::check()) {
            redirect('/login');
        }
        $forum = Board::forum($forumId);
        if (!$forum) {
            http_response_code(404);
            View::render('errors/404', ['path' => '/new-topic/' . $forumId]);
            return;
        }
        if (!Csrf::verify($_POST['csrf'] ?? null)) {
            View::render('topic/new', ['forum' => $forum, 'pageTitle' => 'New topic', 'error' => 'Session expired. Try again.']);
            return;
        }
        $res = Topic::create($forumId, (int) Auth::user()['id'], (string) ($_POST['title'] ?? ''), (string) ($_POST['body'] ?? ''));
        if (!$res['ok']) {
            View::render('topic/new', ['forum' => $forum, 'pageTitle' => 'New topic', 'error' => $res['error']]);
            return;
        }
        redirect(Slug::topicUrl($res['topic']));
    }

    public function reply(string $segment): void
    {
        if (!Auth::check()) {
            redirect('/login');
        }
        $parsed = Slug::parseSuffixed($segment, 't');
        if (!$parsed) {
            redirect('/');
        }
        [, $id] = $parsed;
        if (!Csrf::verify($_POST['csrf'] ?? null)) {
            redirect(Slug::topicUrl(Topic::find($id) ?? ['id' => $id, 'title' => 'topic']));
        }
        $res = Topic::reply($id, (int) Auth::user()['id'], (string) ($_POST['body'] ?? ''));
        $topic = Topic::find($id);
        if (!$res['ok']) {
            $_SESSION['flash_error'] = $res['error'];
        }
        redirect(Slug::topicUrl($topic ?? ['id' => $id, 'title' => 'topic']) . '#reply');
    }

    public function toggleFlag(int $id, string $flag): void
    {
        if (!Auth::isMod()) {
            http_response_code(403);
            echo 'Forbidden';
            return;
        }
        // POST-only: no token-via-URL fallback.
        if (!Csrf::verify($_POST['csrf'] ?? null)) {
            http_response_code(419);
            echo 'CSRF mismatch';
            return;
        }
        $topic = Topic::find($id);
        if (!$topic) {
            redirect('/');
        }
        Topic::setFlags($id, $flag, ((int) $topic[$flag] === 1) ? 0 : 1);
        redirect(Slug::topicUrl($topic));
    }

    public function delete(int $id): void
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
        $topic = Topic::find($id);
        Topic::delete($id);
        redirect($topic ? Slug::forumUrl(['id' => $topic['forum_id'], 'name' => $topic['forum_name']]) : '/');
    }
}

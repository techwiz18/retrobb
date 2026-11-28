<?php
declare(strict_types=1);

namespace RetroBB\Controllers;

use RetroBB\Core\Auth;
use RetroBB\Core\Guard;
use RetroBB\Core\Slug;
use RetroBB\Core\View;
use RetroBB\Models\Board;
use RetroBB\Models\Moderation;
use RetroBB\Models\Topic;

class TopicController
{
    public function show(string $segment): void
    {
        $path = '/topic/' . $segment;
        $parsed = Slug::parseSuffixed($segment, 't');
        if (!$parsed) {
            Guard::notFound($path);
        }
        [$slug, $id] = $parsed;
        $topic = Topic::find($id);
        if (!$topic) {
            Guard::notFound($path);
        }
        // "Moved" ghosts redirect straight to the topic's new home.
        if (!empty($topic['moved_to_id'])) {
            $target = Topic::find((int) $topic['moved_to_id']);
            if ($target) {
                redirect(Slug::topicUrl($target), 301);
            }
        }
        $canonical = Slug::topicUrl($topic);
        $path = \RetroBB\Core\Router::currentPath();
        $base = explode('?', $path)[0];
        if (!\is_canonical($canonical)) {
            redirect($canonical, 301);
        }
        // Views count once per session: refreshes and post-action redirects don't inflate.
        // NOTE: topic pages deliberately still mint a session for first-time
        // guests — view-dedup needs somewhere to remember. Everywhere else
        // stays lazy (see Auth::startSession).
        \RetroBB\Core\Auth::startSession();
        $_SESSION['viewed_topics'] = $_SESSION['viewed_topics'] ?? [];
        if (!in_array($id, $_SESSION['viewed_topics'], true)) {
            Topic::bumpViews($id);
            $topic['views'] = (int) $topic['views'] + 1;
            $_SESSION['viewed_topics'][] = $id;
            $_SESSION['viewed_topics'] = array_slice($_SESSION['viewed_topics'], -200);
        }

        $page = 1;
        if (preg_match('#/page-(\d+)$#', $base, $m)) {
            $page = max(1, (int) $m[1]);
        } elseif (isset($_GET['page']) && (int) $_GET['page'] > 1) {
            // Unify on the pretty /page-N form so ?page= URLs don't duplicate content.
            redirect($canonical . '/page-' . (int) $_GET['page'], 301);
        } elseif (isset($_GET['page'])) {
            $page = max(1, (int) $_GET['page']);
        }
        $perPage = \RetroBB\Core\Settings::int('posts_per_page');
        $data = Topic::posts($id, $page, $perPage);
        $pages = max(1, (int) ceil($data['total'] / $perPage));
        $forum = Board::forum((int) $topic['forum_id']);
        $reactions = feature('reactions') ? \RetroBB\Models\Reaction::countsForPosts(array_map(fn($p) => (int) $p['id'], $data['posts'])) : [];
        $me = Auth::user();
        if ($me) {
            \RetroBB\Models\TopicRead::markRead((int) $me['id'], $id);
        }
        // One policy decides the buttons and the endpoints (#34); the view
        // only reads these booleans.
        $firstPostId = Topic::firstPostId($id);
        $postActions = [];
        if ($me) {
            foreach ($data['posts'] as $p) {
                $p['locked'] = (int) $topic['locked'];
                $postActions[(int) $p['id']] = [
                    'edit' => Moderation::postActionError($p, 'edit', $firstPostId, (int) $data['total']) === null,
                    'delete' => Moderation::postActionError($p, 'delete', $firstPostId, (int) $data['total']) === null,
                ];
            }
        }

        View::render('topic/show', [
            'topic' => $topic,
            'forum' => $forum,
            'posts' => $data['posts'],
            'reactions' => $reactions,
            'postActions' => $postActions,
            'canModerate' => Auth::isMod(),
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
        Guard::requireLogin('/new-topic/' . $forumId);
        $forum = Board::forum($forumId);
        if (!$forum) {
            Guard::notFound('/new-topic/' . $forumId);
        }
        View::render('topic/new', ['forum' => $forum, 'pageTitle' => 'New topic — ' . board_name(), 'error' => null]);
    }

    public function newSubmit(int $forumId): void
    {
        Guard::requireLogin('/new-topic/' . $forumId);
        $forum = Board::forum($forumId);
        if (!$forum) {
            Guard::notFound('/new-topic/' . $forumId);
        }
        Guard::csrf(Slug::forumUrl($forum));
        $res = Topic::create($forumId, (int) Auth::user()['id'], (string) ($_POST['title'] ?? ''), (string) ($_POST['body'] ?? ''));
        if (!$res['ok']) {
            View::render('topic/new', ['forum' => $forum, 'pageTitle' => 'New topic', 'error' => $res['error']]);
            return;
        }
        redirect(Slug::topicUrl($res['topic']));
    }

    public function reply(string $segment): void
    {
        Guard::requireLogin('/topic/' . $segment);
        $parsed = Slug::parseSuffixed($segment, 't');
        if (!$parsed) {
            Guard::notFound('/topic/' . $segment);
        }
        [, $id] = $parsed;
        $topic = Topic::find($id);
        if (!$topic) {
            Guard::notFound('/topic/' . $segment);
        }
        Guard::csrf(Slug::topicUrl($topic));
        $res = Topic::reply($id, (int) Auth::user()['id'], (string) ($_POST['body'] ?? ''));
        if (!$res['ok']) {
            $_SESSION['flash_error'] = $res['error'];
        }
        redirect(Slug::topicUrl($topic) . '#reply');
    }

    public function toggleFlag(int $id, string $flag): void
    {
        Guard::requireModerator();
        $topic = Topic::find($id);
        if (!$topic) {
            Guard::notFound('/topic/' . $id . '/' . $flag);
        }
        // POST-only: no token-via-URL fallback.
        Guard::csrf(Slug::topicUrl($topic));
        Topic::setFlags($id, $flag, ((int) $topic[$flag] === 1) ? 0 : 1);
        \RetroBB\Core\Modlog::log((int) Auth::user()['id'], (((int) $topic[$flag] === 1) ? 'un' : '') . $flag, 'topic', $id, mb_substr($topic['title'], 0, 150));
        redirect(Slug::topicUrl($topic));
    }

    public function delete(int $id): void
    {
        Guard::requireModerator();
        $topic = Topic::find($id);
        if (!$topic) {
            Guard::notFound('/topic/' . $id . '/delete');
        }
        Guard::csrf(Slug::topicUrl($topic));
        if ((int) ($topic['user_id'] ?? 0) !== (int) Auth::user()['id']
            && ($topic['author_group'] ?? 'member') !== 'member' && !Auth::isSuper()) {
            Guard::forbidden('Only super admins can delete staff topics.');
        }
        Topic::delete($id);
        \RetroBB\Core\Modlog::log((int) Auth::user()['id'], 'delete', 'topic', $id, mb_substr($topic['title'], 0, 150));
        redirect(Slug::forumUrl(['id' => $topic['forum_id'], 'name' => $topic['forum_name']]));
    }

    public function moveForm(int $id): void
    {
        Guard::requireModerator();
        $topic = Topic::find($id);
        if (!$topic) {
            Guard::notFound('/topic/' . $id . '/move');
        }
        View::render('topic/move', [
            'topic' => $topic, 'cats' => Board::index(),
            'pageTitle' => 'Move topic — ' . board_name(), 'error' => null,
        ]);
    }

    public function moveSubmit(int $id): void
    {
        Guard::requireModerator();
        $topic = Topic::find($id);
        if (!$topic) {
            Guard::notFound('/topic/' . $id . '/move');
        }
        Guard::csrf(Slug::topicUrl($topic));
        $res = Topic::move($id, (int) ($_POST['forum_id'] ?? 0), isset($_POST['ghost']), (int) Auth::user()['id']);
        if (!$res['ok']) {
            View::render('topic/move', [
                'topic' => $topic, 'cats' => Board::index(),
                'pageTitle' => 'Move topic', 'error' => $res['error'],
            ]);
            return;
        }
        $_SESSION['flash_ok'] = 'Topic moved.';
        redirect(Slug::topicUrl($topic));
    }

    public function splitForm(int $id): void
    {
        Guard::requireModerator();
        $topic = Topic::find($id);
        if (!$topic) {
            Guard::notFound('/topic/' . $id . '/split');
        }
        $all = Topic::posts($id, 1, 10000);
        View::render('topic/split', [
            'topic' => $topic, 'posts' => $all['posts'],
            'pageTitle' => 'Split topic — ' . board_name(), 'error' => null,
        ]);
    }

    public function splitSubmit(int $id): void
    {
        Guard::requireModerator();
        $topic = Topic::find($id);
        if (!$topic) {
            Guard::notFound('/topic/' . $id . '/split');
        }
        Guard::csrf(Slug::topicUrl($topic));
        $ids = array_map('intval', (array) ($_POST['post_ids'] ?? []));
        $res = Topic::split($id, $ids, (string) ($_POST['title'] ?? ''), (int) Auth::user()['id']);
        if (!$res['ok']) {
            $all = Topic::posts($id, 1, 10000);
            View::render('topic/split', [
                'topic' => $topic, 'posts' => $all['posts'],
                'pageTitle' => 'Split topic', 'error' => $res['error'],
            ]);
            return;
        }
        $_SESSION['flash_ok'] = 'Posts split into a new topic.';
        redirect(Slug::topicUrl(['id' => $res['new_id'], 'title' => (string) ($_POST['title'] ?? 'topic')]));
    }

    public function mergeForm(int $id): void
    {
        Guard::requireModerator();
        $topic = Topic::find($id);
        if (!$topic) {
            Guard::notFound('/topic/' . $id . '/merge');
        }
        View::render('topic/merge', [
            'topic' => $topic,
            'q' => trim((string) ($_GET['q'] ?? '')),
            'candidates' => Topic::search(trim((string) ($_GET['q'] ?? '')), $id),
            'pageTitle' => 'Merge topic — ' . board_name(),
        ]);
    }

    public function mergeSubmit(int $id): void
    {
        Guard::requireModerator();
        $topic = Topic::find($id);
        if (!$topic) {
            Guard::notFound('/topic/' . $id . '/merge');
        }
        Guard::csrf(Slug::topicUrl($topic));
        // Accept a topic id or a full/partial URL containing ".t123".
        $raw = (string) ($_POST['target'] ?? '');
        $targetId = 0;
        if (preg_match('/\.t(\d+)/', $raw, $m)) {
            $targetId = (int) $m[1];
        } elseif (preg_match('/^\d+$/', trim($raw))) {
            $targetId = (int) trim($raw);
        }
        if ($targetId <= 0) {
            $_SESSION['flash_error'] = 'Pick a target topic below.';
            redirect('/topic/' . $id . '/merge');
        }
        $res = Topic::merge($id, $targetId, (int) Auth::user()['id']);
        if (!$res['ok']) {
            $_SESSION['flash_error'] = $res['error'];
            redirect(Slug::topicUrl($topic));
        }
        $_SESSION['flash_ok'] = 'Topics merged.';
        $dst = Topic::find($targetId);
        redirect($dst ? Slug::topicUrl($dst) : '/');
    }

    public function editPostForm(int $postId): void
    {
        if (!Auth::check()) {
            redirect('/login');
        }
        $post = \RetroBB\Models\Post::find($postId);
        if (!$post) {
            Guard::notFound('/post/' . $postId . '/edit');
        }
        $allowed = Moderation::postActionError($post, 'edit');
        if ($allowed !== null) {
            Guard::forbidden($allowed);
        }
        View::render('topic/edit', ['post' => $post, 'is_first' => $postId === Topic::firstPostId((int) $post['topic_id']), 'pageTitle' => 'Edit post — ' . board_name(), 'error' => null]);
    }

    public function editPostSubmit(int $postId): void
    {
        if (!Auth::check()) {
            redirect('/login');
        }
        $post = \RetroBB\Models\Post::find($postId);
        if (!$post) {
            Guard::notFound('/post/' . $postId . '/edit');
        }
        $isFirst = $postId === Topic::firstPostId((int) $post['topic_id']);
        $allowed = Moderation::postActionError($post, 'edit');
        if ($allowed !== null) {
            Guard::forbidden($allowed);
        }
        Guard::csrf(Slug::topicUrl(['id' => $post['topic_id'], 'title' => $post['topic_slug']]) . '#p' . $postId);
        $body = trim((string) ($_POST['body'] ?? ''));
        if (mb_strlen($body) < 2 || mb_strlen($body) > 20000) {
            View::render('topic/edit', ['post' => $post, 'is_first' => $isFirst, 'pageTitle' => 'Edit post', 'error' => 'Post is too short or too long.']);
            return;
        }
        if ($isFirst && isset($_POST['title'])) {
            $res = Topic::retitle((int) $post['topic_id'], (string) $_POST['title']);
            if (!$res['ok']) {
                View::render('topic/edit', ['post' => $post, 'is_first' => $isFirst, 'pageTitle' => 'Edit post', 'error' => $res['error']]);
                return;
            }
            \RetroBB\Core\Modlog::log((int) Auth::user()['id'], 'retitle', 'topic', (int) $post['topic_id'], mb_substr((string) $_POST['title'], 0, 150));
            $post['topic_slug'] = \RetroBB\Core\Slug::make((string) $_POST['title']);
        }
        \RetroBB\Core\PostBody::update($postId, $body);
        \RetroBB\Core\Modlog::log((int) Auth::user()['id'], 'edit', 'post', $postId, "topic {$post['topic_id']}");
        redirect(Slug::topicUrl(['id' => $post['topic_id'], 'title' => $post['topic_slug']]) . '#p' . $postId);
    }

    public function deletePost(int $postId): void
    {
        if (!Auth::check()) {
            redirect('/login');
        }
        $post = \RetroBB\Models\Post::find($postId);
        if (!$post) {
            Guard::notFound('/post/' . $postId);
        }
        $allowed = Moderation::postActionError($post, 'delete');
        if ($allowed !== null) {
            Guard::forbidden($allowed);
        }
        Guard::csrf(Slug::topicUrl(['id' => $post['topic_id'], 'title' => $post['topic_slug']]) . '#p' . $postId);
        $topicId = \RetroBB\Models\Post::delete($postId);
        \RetroBB\Core\Modlog::log((int) Auth::user()['id'], 'delete', 'post', $postId, "topic {$post['topic_id']}");
        if ($topicId === null) {
            $forum = Board::forum((int) $post['forum_id']);
            redirect($forum ? Slug::forumUrl($forum) : '/');
        }
        $topic = Topic::find($topicId);
        redirect(($topic ? Slug::topicUrl($topic) : '/') . '#reply');
    }
}

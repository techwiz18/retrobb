<?php
declare(strict_types=1);

namespace RetroBB\Models;

use RetroBB\Core\BBCode;
use RetroBB\Core\Db;
use RetroBB\Core\Slug;

class Topic
{
    public static function find(int $id): ?array
    {
        $st = Db::pdo()->prepare('SELECT t.*, u.username AS author, f.name AS forum_name FROM topics t JOIN users u ON u.id=t.user_id JOIN forums f ON f.id=t.forum_id WHERE t.id=?');
        $st->execute([$id]);
        $r = $st->fetch();
        return $r ?: null;
    }

    public static function posts(int $topicId, int $page, int $perPage): array
    {
        $pdo = Db::pdo();
        $cnt = $pdo->prepare('SELECT COUNT(*) c FROM posts WHERE topic_id=?');
        $cnt->execute([$topicId]);
        $total = (int) $cnt->fetch()['c'];
        $offset = max(0, ($page - 1) * $perPage);
        $st = $pdo->prepare(
            'SELECT p.*, u.username, u.user_group, u.posts_count AS user_posts, u.created_at AS user_since
             FROM posts p JOIN users u ON u.id=p.user_id
             WHERE p.topic_id=? ORDER BY p.id ASC LIMIT ? OFFSET ?'
        );
        $st->bindValue(1, $topicId, \PDO::PARAM_INT);
        $st->bindValue(2, $perPage, \PDO::PARAM_INT);
        $st->bindValue(3, $offset, \PDO::PARAM_INT);
        $st->execute();
        return ['posts' => $st->fetchAll(), 'total' => $total];
    }

    public static function create(int $forumId, int $userId, string $title, string $bbcode): array
    {
        $title = trim($title);
        $bbcode = trim($bbcode);
        if (mb_strlen($title) < 3 || mb_strlen($title) > 120) {
            return ['ok' => false, 'error' => 'Title must be 3–120 characters.'];
        }
        if (mb_strlen($bbcode) < 2 || mb_strlen($bbcode) > 20000) {
            return ['ok' => false, 'error' => 'Post body is too short or too long.'];
        }
        $wait = self::floodWait($userId, \RetroBB\Core\Auth::isMod());
        if ($wait > 0) {
            return ['ok' => false, 'error' => "Slow down — please wait $wait more second(s)."];
        }
        $pdo = Db::pdo();
        $now = date('Y-m-d H:i:s');
        $st = $pdo->prepare('INSERT INTO topics (forum_id, user_id, title, slug, created_at, last_post_at, last_post_user_id, posts_count) VALUES (?,?,?,?,?,?,?,1)');
        $st->execute([$forumId, $userId, $title, Slug::make($title), $now, $now, $userId]);
        $tid = (int) $pdo->lastInsertId();
        $html = BBCode::toHtml($bbcode);
        $pdo->prepare('INSERT INTO posts (topic_id, user_id, body_bbcode, body_html, created_at) VALUES (?,?,?,?,?)')->execute([$tid, $userId, $bbcode, $html, $now]);
        $pdo->prepare('UPDATE forums SET topics_count=topics_count+1, posts_count=posts_count+1, last_topic_id=? WHERE id=?')->execute([$tid, $forumId]);
        $pdo->prepare('UPDATE users SET posts_count=posts_count+1 WHERE id=?')->execute([$userId]);
        \RetroBB\Core\Hooks::do_action('topic_created', $tid);
        $row = self::find($tid);
        return ['ok' => true, 'topic' => $row];
    }

    public static function reply(int $topicId, int $userId, string $bbcode): array
    {
        $topic = self::find($topicId);
        if (!$topic) {
            return ['ok' => false, 'error' => 'Topic not found.'];
        }
        if ((int) $topic['locked'] === 1) {
            return ['ok' => false, 'error' => 'This topic is locked.'];
        }
        $bbcode = trim($bbcode);
        if (mb_strlen($bbcode) < 2 || mb_strlen($bbcode) > 20000) {
            return ['ok' => false, 'error' => 'Reply is too short or too long.'];
        }
        $wait = self::floodWait($userId, \RetroBB\Core\Auth::isMod());
        if ($wait > 0) {
            return ['ok' => false, 'error' => "Slow down — please wait $wait more second(s)."];
        }
        $pdo = Db::pdo();
        $now = date('Y-m-d H:i:s');
        $html = BBCode::toHtml($bbcode);
        $pdo->prepare('INSERT INTO posts (topic_id, user_id, body_bbcode, body_html, created_at) VALUES (?,?,?,?,?)')->execute([$topicId, $userId, $bbcode, $html, $now]);
        $pdo->prepare('UPDATE topics SET posts_count=posts_count+1, last_post_at=?, last_post_user_id=? WHERE id=?')->execute([$now, $userId, $topicId]);
        $pdo->prepare('UPDATE forums SET posts_count=posts_count+1, last_topic_id=? WHERE id=?')->execute([$topicId, $topic['forum_id']]);
        $pdo->prepare('UPDATE users SET posts_count=posts_count+1 WHERE id=?')->execute([$userId]);
        \RetroBB\Core\Hooks::do_action('post_created', $topicId);
        return ['ok' => true];
    }

    public static function bumpViews(int $id): void
    {
        Db::pdo()->prepare('UPDATE topics SET views=views+1 WHERE id=?')->execute([$id]);
    }

    public static function setFlags(int $id, string $flag, int $val): void
    {
        if (!in_array($flag, ['pinned', 'locked'], true)) {
            return;
        }
        Db::pdo()->prepare("UPDATE topics SET $flag=? WHERE id=?")->execute([$val ? 1 : 0, $id]);
    }

    public static function delete(int $id): void
    {
        $t = self::find($id);
        if (!$t) {
            return;
        }
        $pdo = Db::pdo();
        $pdo->prepare('DELETE FROM topics WHERE id=?')->execute([$id]);
        self::recountForum((int) $t['forum_id']);
    }

    public static function recountTopic(int $topicId): void
    {
        $pdo = Db::pdo();
        $n = (int) $pdo->query('SELECT COUNT(*) c FROM posts WHERE topic_id=' . $topicId)->fetch()['c'];
        $last = $pdo->query('SELECT user_id, created_at FROM posts WHERE topic_id=' . $topicId . ' ORDER BY id DESC LIMIT 1')->fetch();
        if ($last) {
            $pdo->prepare('UPDATE topics SET posts_count=?, last_post_at=?, last_post_user_id=? WHERE id=?')
                ->execute([$n, $last['created_at'], $last['user_id'], $topicId]);
        } else {
            $pdo->prepare('UPDATE topics SET posts_count=0 WHERE id=?')->execute([$topicId]);
        }
    }

    public static function recountForum(int $forumId): void
    {
        $pdo = Db::pdo();
        $tc = (int) $pdo->query("SELECT COUNT(*) c FROM topics WHERE forum_id=$forumId")->fetch()['c'];
        $pc = (int) $pdo->query("SELECT COUNT(*) c FROM posts p JOIN topics t ON t.id=p.topic_id WHERE t.forum_id=$forumId")->fetch()['c'];
        $last = $pdo->query("SELECT id FROM topics WHERE forum_id=$forumId ORDER BY last_post_at DESC LIMIT 1")->fetch();
        $pdo->prepare('UPDATE forums SET topics_count=?, posts_count=?, last_topic_id=? WHERE id=?')->execute([$tc, $pc, $last['id'] ?? null, $forumId]);
    }

    /** Seconds a member must still wait before posting (0 = ok). Mods bypass. */
    public static function floodWait(int $userId, bool $isMod): int
    {
        if ($isMod) {
            return 0;
        }
        $secs = max(0, (int) setting('flood_seconds', '30'));
        if ($secs === 0) {
            return 0;
        }
        $since = \RetroBB\Models\Post::secondsSinceLastPost($userId);
        if ($since === null) {
            return 0;
        }
        return max(0, $secs - $since);
    }

    /** Search topics by title for the merge picker. */
    public static function search(string $q, int $excludeId = 0, int $limit = 20): array
    {
        $pdo = Db::pdo();
        $q = trim(mb_substr($q, 0, 100));
        if ($q === '') {
            $st = $pdo->prepare(
                'SELECT t.id, t.title, t.slug, f.name AS forum_name FROM topics t JOIN forums f ON f.id=t.forum_id WHERE t.id != ? ORDER BY t.last_post_at DESC LIMIT ?'
            );
            $st->bindValue(1, $excludeId, \PDO::PARAM_INT);
            $st->bindValue(2, $limit, \PDO::PARAM_INT);
            $st->execute();
            return $st->fetchAll();
        }
        $st = $pdo->prepare(
            'SELECT t.id, t.title, t.slug, f.name AS forum_name FROM topics t JOIN forums f ON f.id=t.forum_id WHERE t.id != ? AND t.title LIKE ? ORDER BY t.last_post_at DESC LIMIT ?'
        );
        $st->bindValue(1, $excludeId, \PDO::PARAM_INT);
        $st->bindValue(2, '%' . $q . '%', \PDO::PARAM_STR);
        $st->bindValue(3, $limit, \PDO::PARAM_INT);
        $st->execute();
        return $st->fetchAll();
    }

    /** Id of the topic's first post (its title lives here). */
    public static function firstPostId(int $topicId): int
    {
        $row = Db::pdo()->query('SELECT MIN(id) m FROM posts WHERE topic_id=' . $topicId)->fetch();
        return (int) ($row['m'] ?? 0);
    }

    /** Retitle a topic (slug follows; old URLs still 301 via the id). */
    public static function retitle(int $id, string $title): array
    {
        $title = trim($title);
        if (mb_strlen($title) < 3 || mb_strlen($title) > 120) {
            return ['ok' => false, 'error' => 'Title must be 3–120 characters.'];
        }
        Db::pdo()->prepare('UPDATE topics SET title=?, slug=? WHERE id=?')->execute([$title, Slug::make($title), $id]);
        return ['ok' => true];
    }

    public static function move(int $id, int $destForumId, bool $ghost, int $modId): array
    {
        $topic = self::find($id);
        $dest = Board::forum($destForumId);
        if (!$topic || !$dest) {
            return ['ok' => false, 'error' => 'Topic or forum not found.'];
        }
        if ((int) $topic['forum_id'] === $destForumId) {
            return ['ok' => false, 'error' => 'Already in that forum.'];
        }
        $pdo = Db::pdo();
        $srcForumId = (int) $topic['forum_id'];
        $pdo->prepare('UPDATE topics SET forum_id=? WHERE id=?')->execute([$destForumId, $id]);
        if ($ghost) {
            $now = date('Y-m-d H:i:s');
            $newUrl = Slug::topicUrl(array_merge($topic, ['id' => $id]));
            $pdo->prepare('INSERT INTO topics (forum_id, user_id, title, slug, pinned, locked, views, posts_count, created_at, last_post_at, last_post_user_id, moved_to_id) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)')
                ->execute([$srcForumId, $modId, 'Moved: ' . $topic['title'], Slug::make('moved-' . $topic['title']), 0, 1, 0, 1, $now, $now, $modId, $id]);
            $gid = (int) $pdo->lastInsertId();
            $html = BBCode::toHtml('This topic has moved here: [url=' . $newUrl . ']' . $topic['title'] . '[/url]');
            $pdo->prepare('INSERT INTO posts (topic_id, user_id, body_bbcode, body_html, created_at) VALUES (?,?,?,?,?)')
                ->execute([$gid, $modId, 'moved-stub', $html, $now]);
        }
        self::recountForum($srcForumId);
        self::recountForum($destForumId);
        \RetroBB\Core\Modlog::log($modId, 'move', 'topic', $id, "forum $srcForumId -> $destForumId" . ($ghost ? ' (ghost)' : ''));
        return ['ok' => true];
    }

    public static function split(int $id, array $postIds, string $title, int $modId): array
    {
        $topic = self::find($id);
        $title = trim($title);
        $postIds = array_values(array_unique(array_map('intval', $postIds)));
        if (!$topic) {
            return ['ok' => false, 'error' => 'Topic not found.'];
        }
        if (mb_strlen($title) < 3 || mb_strlen($title) > 120) {
            return ['ok' => false, 'error' => 'New title must be 3–120 characters.'];
        }
        if (count($postIds) < 1) {
            return ['ok' => false, 'error' => 'Select at least one post to split.'];
        }
        $pdo = Db::pdo();
        // Only posts actually in this topic, never ALL of them.
        $all = $pdo->query('SELECT id FROM posts WHERE topic_id=' . (int) $id . ' ORDER BY id')->fetchAll();
        $allIds = array_map(fn($r) => (int) $r['id'], $all);
        $postIds = array_values(array_intersect($postIds, $allIds));
        if (count($postIds) < 1 || count($postIds) >= count($allIds)) {
            return ['ok' => false, 'error' => 'Cannot split zero or all posts.'];
        }
        $now = date('Y-m-d H:i:s');
        $firstMover = $pdo->query('SELECT user_id, created_at FROM posts WHERE id=' . $postIds[0])->fetch();
        $pdo->prepare('INSERT INTO topics (forum_id, user_id, title, slug, created_at, last_post_at, last_post_user_id, posts_count) VALUES (?,?,?,?,?,?,?,0)')
            ->execute([(int) $topic['forum_id'], (int) $firstMover['user_id'], $title, Slug::make($title), $firstMover['created_at'], $now, $modId]);
        $newId = (int) $pdo->lastInsertId();
        $in = implode(',', $postIds);
        $pdo->exec("UPDATE posts SET topic_id=$newId WHERE id IN ($in)");
        self::recountTopic($id);
        self::recountTopic($newId);
        self::recountForum((int) $topic['forum_id']);
        \RetroBB\Core\Modlog::log($modId, 'split', 'topic', $id, count($postIds) . " posts -> t$newId");
        return ['ok' => true, 'new_id' => $newId];
    }

    public static function merge(int $sourceId, int $targetId, int $modId): array
    {
        if ($sourceId === $targetId) {
            return ['ok' => false, 'error' => 'Cannot merge a topic into itself.'];
        }
        $src = self::find($sourceId);
        $dst = self::find($targetId);
        if (!$src || !$dst) {
            return ['ok' => false, 'error' => 'Topic not found.'];
        }
        $pdo = Db::pdo();
        $pdo->exec("UPDATE posts SET topic_id=$targetId WHERE topic_id=$sourceId");
        $pdo->prepare('DELETE FROM topics WHERE id=?')->execute([$sourceId]);
        self::recountTopic($targetId);
        self::recountForum((int) $src['forum_id']);
        if ((int) $dst['forum_id'] !== (int) $src['forum_id']) {
            self::recountForum((int) $dst['forum_id']);
        }
        \RetroBB\Core\Modlog::log($modId, 'merge', 'topic', $sourceId, "merged into t$targetId");
        return ['ok' => true];
    }
}

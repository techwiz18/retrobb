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
        // recount forum (simple)
        $fid = (int) $t['forum_id'];
        $tc = (int) $pdo->query("SELECT COUNT(*) c FROM topics WHERE forum_id=$fid")->fetch()['c'];
        $pc = (int) $pdo->query("SELECT COUNT(*) c FROM posts p JOIN topics t ON t.id=p.topic_id WHERE t.forum_id=$fid")->fetch()['c'];
        $last = $pdo->query("SELECT id FROM topics WHERE forum_id=$fid ORDER BY last_post_at DESC LIMIT 1")->fetch();
        $pdo->prepare('UPDATE forums SET topics_count=?, posts_count=?, last_topic_id=? WHERE id=?')->execute([$tc, $pc, $last['id'] ?? null, $fid]);
    }
}

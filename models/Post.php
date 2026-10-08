<?php
declare(strict_types=1);

namespace RetroBB\Models;

use RetroBB\Core\BBCode;
use RetroBB\Core\Db;

class Post
{
    public static function find(int $id): ?array
    {
        $st = Db::pdo()->prepare(
            'SELECT p.*, u.username, u.user_group, t.forum_id, t.title AS topic_title, t.slug AS topic_slug, t.locked
             FROM posts p JOIN users u ON u.id=p.user_id JOIN topics t ON t.id=p.topic_id WHERE p.id=?'
        );
        $st->execute([$id]);
        $r = $st->fetch();
        return $r ?: null;
    }

    public static function updateBody(int $id, string $bbcode): void
    {
        Db::pdo()->prepare('UPDATE posts SET body_bbcode=?, body_html=?, edited_at=? WHERE id=?')->execute([
            $bbcode, BBCode::toHtml($bbcode), date('Y-m-d H:i:s'), $id,
        ]);
    }

    /** Seconds since this user last posted (anywhere), or null if never. */
    public static function secondsSinceLastPost(int $userId): ?int
    {
        $st = Db::pdo()->prepare('SELECT created_at FROM posts WHERE user_id=? ORDER BY id DESC LIMIT 1');
        $st->execute([$userId]);
        $row = $st->fetch();
        if (!$row) {
            return null;
        }
        try {
            return max(0, time() - (new \DateTime($row['created_at']))->getTimestamp());
        } catch (\Throwable) {
            return null;
        }
    }
}

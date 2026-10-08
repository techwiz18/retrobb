<?php
declare(strict_types=1);

namespace RetroBB\Models;

use RetroBB\Core\Db;
use RetroBB\Core\Slug;

class Board
{
    /** @return array categories each with forums + last post info */
    public static function index(): array
    {
        $pdo = Db::pdo();
        $cats = $pdo->query('SELECT * FROM categories ORDER BY sort, id')->fetchAll();
        foreach ($cats as &$c) {
            $st = $pdo->prepare(
                'SELECT f.*, (SELECT u.username FROM posts p JOIN users u ON u.id=p.user_id WHERE p.topic_id=f.last_topic_id ORDER BY p.id DESC LIMIT 1) AS last_user,
                 (SELECT t.title FROM topics t WHERE t.id=f.last_topic_id) AS last_title,
                 (SELECT t.slug FROM topics t WHERE t.id=f.last_topic_id) AS last_slug
                 FROM forums f WHERE f.category_id=? ORDER BY sort, id'
            );
            $st->execute([$c['id']]);
            $c['forums'] = $st->fetchAll();
        }
        return $cats;
    }

    public static function forum(int $id): ?array
    {
        $st = Db::pdo()->prepare('SELECT * FROM forums WHERE id=?');
        $st->execute([$id]);
        $r = $st->fetch();
        return $r ?: null;
    }

    public static function topics(int $forumId, int $page, int $perPage): array
    {
        $pdo = Db::pdo();
        $cnt = $pdo->prepare('SELECT COUNT(*) c FROM topics WHERE forum_id=?');
        $cnt->execute([$forumId]);
        $total = (int) $cnt->fetch()['c'];
        $offset = max(0, ($page - 1) * $perPage);
        $st = $pdo->prepare(
            'SELECT t.*, u.username AS author, lu.username AS last_user
             FROM topics t JOIN users u ON u.id=t.user_id
             LEFT JOIN users lu ON lu.id=t.last_post_user_id
             WHERE t.forum_id=? ORDER BY t.pinned DESC, t.last_post_at DESC LIMIT ? OFFSET ?'
        );
        $st->bindValue(1, $forumId, \PDO::PARAM_INT);
        $st->bindValue(2, $perPage, \PDO::PARAM_INT);
        $st->bindValue(3, $offset, \PDO::PARAM_INT);
        $st->execute();
        return ['topics' => $st->fetchAll(), 'total' => $total];
    }

    public static function stats(): array
    {
        $pdo = Db::pdo();
        try {
            return [
                'users' => (int) $pdo->query('SELECT COUNT(*) c FROM users')->fetch()['c'],
                'posts' => (int) $pdo->query('SELECT COUNT(*) c FROM posts')->fetch()['c'],
                'topics' => (int) $pdo->query('SELECT COUNT(*) c FROM topics')->fetch()['c'],
                'newest' => $pdo->query('SELECT username FROM users ORDER BY id DESC LIMIT 1')->fetch()['username'] ?? '—',
            ];
        } catch (\Throwable) {
            return ['users' => 0, 'posts' => 0, 'topics' => 0, 'newest' => '—'];
        }
    }
}

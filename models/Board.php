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
                 (SELECT p.user_id FROM posts p WHERE p.topic_id=f.last_topic_id ORDER BY p.id DESC LIMIT 1) AS last_user_id,
                 (SELECT t.title FROM topics t WHERE t.id=f.last_topic_id) AS last_title,
                 (SELECT t.slug FROM topics t WHERE t.id=f.last_topic_id) AS last_slug,
                 (SELECT t.last_post_at FROM topics t WHERE t.id=f.last_topic_id) AS last_at
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
            'SELECT t.*, u.username AS author, lu.username AS last_user, lu.id AS last_user_id
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
                'newest_id' => (int) ($pdo->query('SELECT id FROM users ORDER BY id DESC LIMIT 1')->fetch()['id'] ?? 0),
            ];
        } catch (\Throwable) {
            return ['users' => 0, 'posts' => 0, 'topics' => 0, 'newest' => '—'];
        }
    }

    /** Move a category up/down by swapping sort with its neighbour. $dir = -1|+1. */
    public static function moveCategory(int $id, int $dir): void
    {
        $pdo = Db::pdo();
        $cats = $pdo->query('SELECT id, sort FROM categories ORDER BY sort, id')->fetchAll();
        self::swapSort($pdo, 'categories', $cats, $id, $dir);
    }

    /** Move a forum up/down within its category. $dir = -1|+1. */
    public static function moveForum(int $id, int $dir): void
    {
        $pdo = Db::pdo();
        $st = $pdo->prepare('SELECT category_id FROM forums WHERE id=?');
        $st->execute([$id]);
        $row = $st->fetch();
        if (!$row) {
            return;
        }
        $st = $pdo->prepare('SELECT id, sort FROM forums WHERE category_id=? ORDER BY sort, id');
        $st->execute([$row['category_id']]);
        self::swapSort($pdo, 'forums', $st->fetchAll(), $id, $dir);
    }

    private static function swapSort(\PDO $pdo, string $table, array $rows, int $id, int $dir): void
    {
        $i = null;
        foreach ($rows as $k => $r) {
            if ((int) $r['id'] === $id) {
                $i = $k;
                break;
            }
        }
        if ($i === null) {
            return;
        }
        $j = $i + ($dir < 0 ? -1 : 1);
        if (!isset($rows[$j])) {
            return;
        }
        $a = $rows[$i];
        $b = $rows[$j];
        $upd = $pdo->prepare("UPDATE $table SET sort=? WHERE id=?");
        $upd->execute([$b['sort'], $a['id']]);
        $upd->execute([$a['sort'], $b['id']]);
        // Identical sorts would no-op the swap; nudge instead.
        if ((int) $a['sort'] === (int) $b['sort']) {
            $upd->execute([(int) $a['sort'] + ($dir < 0 ? -1 : 1), $a['id']]);
        }
    }
}

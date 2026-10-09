<?php
declare(strict_types=1);

namespace RetroBB\Models;

use RetroBB\Core\Db;

class Reaction
{
    public const ALLOWED = ['like', 'thanks', 'funny'];

    /** Toggle: same reaction again removes it, different one switches. Returns [ok, action]. */
    public static function toggle(int $postId, int $userId, string $reaction): array
    {
        $reaction = in_array($reaction, self::ALLOWED, true) ? $reaction : 'like';
        $pdo = Db::pdo();
        $st = $pdo->prepare('SELECT reaction FROM post_reactions WHERE post_id=? AND user_id=?');
        $st->execute([$postId, $userId]);
        $row = $st->fetch();
        if ($row && $row['reaction'] === $reaction) {
            $pdo->prepare('DELETE FROM post_reactions WHERE post_id=? AND user_id=?')->execute([$postId, $userId]);
            return ['ok' => true, 'action' => 'removed'];
        }
        $pdo->prepare(
            'INSERT INTO post_reactions (post_id, user_id, reaction, created_at) VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE reaction=VALUES(reaction), created_at=VALUES(created_at)'
        )->execute([$postId, $userId, $reaction, date('Y-m-d H:i:s')]);
        return ['ok' => true, 'action' => ($row ? 'switched' : 'added')];
    }

    /** @return array<string,int> reaction => count */
    public static function counts(int $postId): array
    {
        try {
            $st = Db::pdo()->prepare('SELECT reaction, COUNT(*) c FROM post_reactions WHERE post_id=? GROUP BY reaction');
            $st->execute([$postId]);
            $out = [];
            foreach ($st->fetchAll() as $r) {
                $out[$r['reaction']] = (int) $r['c'];
            }
            return $out;
        } catch (\Throwable) {
            return [];
        }
    }

    /** @return array<int,array<string,int>> postId => counts (one query for a topic page). */    public static function countsForPosts(array $postIds): array
    {
        $postIds = array_values(array_unique(array_map('intval', $postIds)));
        if (!$postIds) {
            return [];
        }
        try {
            $in = implode(',', $postIds);
            $rows = Db::pdo()->query("SELECT post_id, reaction, COUNT(*) c FROM post_reactions WHERE post_id IN ($in) GROUP BY post_id, reaction")->fetchAll();
        } catch (\Throwable) {
            return [];
        }
        $out = [];
        foreach ($rows as $r) {
            $out[(int) $r['post_id']][$r['reaction']] = (int) $r['c'];
        }
        return $out;
    }
}

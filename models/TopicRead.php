<?php
declare(strict_types=1);

namespace RetroBB\Models;

use RetroBB\Core\Db;

/**
 * Per-user read markers: "new" means posts the user hasn't seen, not just
 * recent activity. A topic is unread when its newest post id passes both the
 * user's marker for that topic and the join cutoff (posts made before the
 * account existed are never "new").
 */
class TopicRead
{
    /** Newest post id that predates this user's account (per-request cached). */
    public static function joinPostId(int $userId): int
    {
        static $cache = [];
        if (isset($cache[$userId])) {
            return $cache[$userId];
        }
        try {
            $u = Db::pdo()->prepare('SELECT created_at FROM users WHERE id=?');
            $u->execute([$userId]);
            $row = $u->fetch();
            if (!$row) {
                return $cache[$userId] = 0;
            }
            $st = Db::pdo()->prepare('SELECT MAX(id) m FROM posts WHERE created_at <= ?');
            $st->execute([$row['created_at']]);
            return $cache[$userId] = (int) ($st->fetch()['m'] ?? 0);
        } catch (\Throwable) {
            return 0;
        }
    }

    /** Mark a topic read up to its newest post. Best-effort (table may predate migration). */
    public static function markRead(int $userId, int $topicId): void
    {
        if ($userId <= 0 || $topicId <= 0) {
            return;
        }
        try {
            $pdo = Db::pdo();
            $m = $pdo->query('SELECT MAX(id) m FROM posts WHERE topic_id=' . $topicId)->fetch()['m'] ?? 0;
            $pdo->prepare(
                'INSERT INTO topic_reads (user_id, topic_id, last_post_id, updated_at) VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE last_post_id=VALUES(last_post_id), updated_at=VALUES(updated_at)'
            )->execute([$userId, $topicId, (int) $m, date('Y-m-d H:i:s')]);
        } catch (\Throwable) {
        }
    }

    /** @return array<int,true> unread topic ids out of the given list. */
    public static function unreadMap(int $userId, array $topicIds): array
    {
        $topicIds = array_values(array_unique(array_map('intval', $topicIds)));
        if (!$topicIds || $userId <= 0) {
            return [];
        }
        try {
            $cut = self::joinPostId($userId);
            $in = implode(',', $topicIds);
            $rows = Db::pdo()->query(
                "SELECT t.id, (SELECT MAX(p.id) FROM posts p WHERE p.topic_id=t.id) AS lastpid, COALESCE((SELECT r.last_post_id FROM topic_reads r WHERE r.user_id=$userId AND r.topic_id=t.id),0) AS mark FROM topics t WHERE t.id IN ($in)"
            )->fetchAll();
        } catch (\Throwable) {
            return [];
        }
        $out = [];
        foreach ($rows as $r) {
            if ((int) $r['lastpid'] > max((int) $r['mark'], $cut)) {
                $out[(int) $r['id']] = true;
            }
        }
        return $out;
    }

    /** @return array<int,true> forum ids containing at least one unread topic. */
    public static function forumsUnread(int $userId): array
    {
        if ($userId <= 0) {
            return [];
        }
        try {
            $cut = self::joinPostId($userId);
            $rows = Db::pdo()->query(
                "SELECT DISTINCT t.forum_id AS fid FROM topics t WHERE (SELECT MAX(p.id) FROM posts p WHERE p.topic_id=t.id) > GREATEST(COALESCE((SELECT r.last_post_id FROM topic_reads r WHERE r.user_id=$userId AND r.topic_id=t.id),0), $cut)"
            )->fetchAll();
        } catch (\Throwable) {
            return [];
        }
        $out = [];
        foreach ($rows as $r) {
            $out[(int) $r['fid']] = true;
        }
        return $out;
    }
}

<?php
declare(strict_types=1);

namespace RetroBB\Models;

use RetroBB\Core\Db;
use RetroBB\Core\Modlog;

class Moderation
{
    public static function warn(int $userId, int $modId, string $reason, int $postId = 0, int $topicId = 0): void
    {
        $reason = mb_substr(trim($reason), 0, 500);
        try {
            Db::pdo()->prepare(
                'INSERT INTO warnings (user_id, warned_by, reason, created_at, post_id, topic_id) VALUES (?,?,?,?,?,?)'
            )->execute([$userId, $modId, $reason, date('Y-m-d H:i:s'), $postId, $topicId]);
        } catch (\Throwable) {
            // Pre-010 tables lack post/topic columns — record it anyway.
            Db::pdo()->prepare(
                'INSERT INTO warnings (user_id, warned_by, reason, created_at) VALUES (?,?,?,?)'
            )->execute([$userId, $modId, $reason, date('Y-m-d H:i:s')]);
            $postId = 0;
            $topicId = 0;
        }
        Modlog::log($modId, 'warn', 'user', $userId, mb_substr($reason, 0, 200));
        \RetroBB\Models\Notification::create($userId, $modId, 'warning', $topicId, $postId, $reason);
    }

    /** @return array warnings newest first */
    public static function warningsFor(int $userId): array
    {
        $st = Db::pdo()->prepare(
            'SELECT w.*, u.username AS warned_by_name FROM warnings w JOIN users u ON u.id=w.warned_by WHERE w.user_id=? ORDER BY w.id DESC'
        );
        $st->execute([$userId]);
        return $st->fetchAll();
    }

    public static function warningCount(int $userId): int
    {
        try {
            $st = Db::pdo()->prepare('SELECT COUNT(*) c FROM warnings WHERE user_id=?');
            $st->execute([$userId]);
            return (int) $st->fetch()['c'];
        } catch (\Throwable) {
            return 0;
        }
    }

    /** $days null = permanent. Returns ban id. */
    public static function ban(int $userId, int $modId, string $reason, ?int $days): int
    {
        $expires = $days === null ? null : date('Y-m-d H:i:s', time() + $days * 86400);
        Db::pdo()->prepare(
            'INSERT INTO bans (user_id, banned_by, reason, expires_at, created_at) VALUES (?,?,?,?,?)'
        )->execute([$userId, $modId, mb_substr(trim($reason), 0, 500), $expires, date('Y-m-d H:i:s')]);
        $id = (int) Db::pdo()->lastInsertId();
        Modlog::log($modId, 'ban', 'user', $userId, ($days === null ? 'permanent' : $days . 'd') . ': ' . mb_substr(trim($reason), 0, 150));
        return $id;
    }

    public static function unban(int $banId, int $modId): void
    {
        $st = Db::pdo()->prepare('SELECT * FROM bans WHERE id=?');
        $st->execute([$banId]);
        $ban = $st->fetch();
        if (!$ban) {
            return;
        }
        Db::pdo()->prepare('UPDATE bans SET lifted_at=? WHERE id=?')->execute([date('Y-m-d H:i:s'), $banId]);
        Modlog::log($modId, 'unban', 'user', (int) $ban['user_id'], 'ban #' . $banId);
    }

    /** Active (non-lifted, non-expired) ban for a user, or null. */
    public static function activeBan(int $userId): ?array
    {
        $st = Db::pdo()->prepare(
            "SELECT * FROM bans WHERE user_id=? AND lifted_at IS NULL AND (expires_at IS NULL OR expires_at > ?) ORDER BY id DESC LIMIT 1"
        );
        $st->execute([$userId, date('Y-m-d H:i:s')]);
        $r = $st->fetch();
        return $r ?: null;
    }

    /** @return array active + recent bans */
    public static function banList(int $limit = 100): array
    {
        $limit = max(1, min(1000, $limit));
        return Db::pdo()->query(
            'SELECT b.*, u.username, a.username AS banned_by_name FROM bans b JOIN users u ON u.id=b.user_id JOIN users a ON a.id=b.banned_by ORDER BY b.id DESC LIMIT ' . $limit
        )->fetchAll();
    }
}

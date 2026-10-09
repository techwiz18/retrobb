<?php
declare(strict_types=1);

namespace RetroBB\Models;

use RetroBB\Core\Db;

class Notification
{
    public static function create(int $userId, int $actorId, string $type, int $topicId = 0, int $postId = 0, string $detail = ''): void
    {
        if ($userId <= 0 || $userId === $actorId) {
            return;
        }
        // Alerts are the master switch: mention/reply/reaction notifications
        // are all suppressed when the owner disables them. Rendering (mention
        // links) and reactions themselves keep working.
        if (!feature('alerts')) {
            return;
        }
        if (!in_array($type, ['mention', 'reply', 'reaction', 'warning'], true)) {
            $type = 'mention';
        }
        try {
            Db::pdo()->prepare(
                'INSERT INTO notifications (user_id, actor_id, type, topic_id, post_id, created_at, detail) VALUES (?,?,?,?,?,?,?)'
            )->execute([$userId, $actorId, $type, $topicId, $postId, date('Y-m-d H:i:s'), mb_substr($detail, 0, 500)]);
        } catch (\Throwable) {
            try {
                // Pre-009 tables lack `detail` — degrade, don't lose the alert.
                Db::pdo()->prepare(
                    'INSERT INTO notifications (user_id, actor_id, type, topic_id, post_id, created_at) VALUES (?,?,?,?,?,?)'
                )->execute([$userId, $actorId, $type, $topicId, $postId, date('Y-m-d H:i:s')]);
            } catch (\Throwable) {
                // Table may not exist pre-migration — alerts are best-effort.
            }
        }
    }

    public static function unreadCount(int $userId): int
    {
        try {
            $st = Db::pdo()->prepare('SELECT COUNT(*) c FROM notifications WHERE user_id=? AND read_at IS NULL');
            $st->execute([$userId]);
            return (int) $st->fetch()['c'];
        } catch (\Throwable) {
            return 0;
        }
    }

    /** @return array{items: array, total: int} */
    public static function list(int $userId, int $page = 1, int $perPage = 25): array
    {
        $pdo = Db::pdo();
        $total = (int) $pdo->prepare('SELECT COUNT(*) c FROM notifications WHERE user_id=?')->execute([$userId]) ?: 0;
        $cnt = $pdo->prepare('SELECT COUNT(*) c FROM notifications WHERE user_id=?');
        $cnt->execute([$userId]);
        $total = (int) $cnt->fetch()['c'];
        $offset = max(0, ($page - 1) * $perPage);
        $st = $pdo->prepare(
            'SELECT n.*, a.username AS actor_name, t.title AS topic_title FROM notifications n LEFT JOIN users a ON a.id=n.actor_id LEFT JOIN topics t ON t.id=n.topic_id WHERE n.user_id=? ORDER BY n.id DESC LIMIT ? OFFSET ?'
        );
        $st->bindValue(1, $userId, \PDO::PARAM_INT);
        $st->bindValue(2, $perPage, \PDO::PARAM_INT);
        $st->bindValue(3, $offset, \PDO::PARAM_INT);
        $st->execute();
        return ['items' => $st->fetchAll(), 'total' => $total];
    }

    public static function markAllRead(int $userId): void
    {
        try {
            Db::pdo()->prepare('UPDATE notifications SET read_at=? WHERE user_id=? AND read_at IS NULL')
                ->execute([date('Y-m-d H:i:s'), $userId]);
        } catch (\Throwable) {
        }
    }
}

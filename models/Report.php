<?php
declare(strict_types=1);

namespace RetroBB\Models;

use RetroBB\Core\Db;

class Report
{
    public static function create(int $postId, int $topicId, int $reporterId, string $reason): array
    {
        $reason = trim($reason);
        if (mb_strlen($reason) < 3 || mb_strlen($reason) > 500) {
            return ['ok' => false, 'error' => 'Please give a short reason (3–500 chars).'];
        }
        $pdo = Db::pdo();
        $dup = $pdo->prepare("SELECT id FROM reports WHERE post_id=? AND reporter_id=? AND status='open' LIMIT 1");
        $dup->execute([$postId, $reporterId]);
        if ($dup->fetch()) {
            return ['ok' => false, 'error' => 'You already reported this post.'];
        }
        $pdo->prepare(
            'INSERT INTO reports (post_id, topic_id, reporter_id, reason, status, created_at) VALUES (?,?,?,?,?,?)'
        )->execute([$postId, $topicId, $reporterId, $reason, 'open', date('Y-m-d H:i:s')]);
        \RetroBB\Core\Hooks::do_action('report_created', (int) $pdo->lastInsertId());
        return ['ok' => true];
    }

    /** @return array{reports: array, total: int} */
    public static function openList(int $page = 1, int $perPage = 25): array
    {
        $pdo = Db::pdo();
        $total = (int) $pdo->query("SELECT COUNT(*) c FROM reports WHERE status='open'")->fetch()['c'];
        $offset = max(0, ($page - 1) * $perPage);
        $st = $pdo->prepare(
            "SELECT r.*, ru.username AS reporter, pu.username AS post_author, t.title AS topic_title, t.slug AS topic_slug
             FROM reports r
             JOIN users ru ON ru.id=r.reporter_id
             JOIN posts p ON p.id=r.post_id
             JOIN users pu ON pu.id=p.user_id
             JOIN topics t ON t.id=r.topic_id
             WHERE r.status='open' ORDER BY r.id ASC LIMIT ? OFFSET ?"
        );
        $st->bindValue(1, $perPage, \PDO::PARAM_INT);
        $st->bindValue(2, $offset, \PDO::PARAM_INT);
        $st->execute();
        return ['reports' => $st->fetchAll(), 'total' => $total];
    }

    public static function openCount(): int
    {
        try {
            return (int) Db::pdo()->query("SELECT COUNT(*) c FROM reports WHERE status='open'")->fetch()['c'];
        } catch (\Throwable) {
            return 0;
        }
    }

    public static function find(int $id): ?array
    {
        $st = Db::pdo()->prepare('SELECT * FROM reports WHERE id=?');
        $st->execute([$id]);
        $r = $st->fetch();
        return $r ?: null;
    }

    public static function handle(int $id, int $modId, string $status, string $note = ''): void
    {
        $status = $status === 'resolved' ? 'resolved' : 'dismissed';
        Db::pdo()->prepare(
            'UPDATE reports SET status=?, handled_by=?, handled_at=?, handle_note=? WHERE id=?'
        )->execute([$status, $modId, date('Y-m-d H:i:s'), mb_substr($note, 0, 500), $id]);
    }
}

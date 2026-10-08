<?php
declare(strict_types=1);

namespace RetroBB\Core;

class Modlog
{
    public static function log(int $actorId, string $action, string $targetType = '', int $targetId = 0, string $detail = ''): void
    {
        try {
            Db::pdo()->prepare(
                'INSERT INTO modlog (actor_id, action, target_type, target_id, detail, created_at) VALUES (?,?,?,?,?,?)'
            )->execute([$actorId, $action, $targetType, $targetId, mb_substr($detail, 0, 500), date('Y-m-d H:i:s')]);
        } catch (\Throwable $t) {
            error_log('RetroBB modlog failed: ' . $t->getMessage());
        }
        Hooks::do_action('modlog', $actorId, $action, $targetType, $targetId);
    }

    /** @return array{entries: array, total: int} */
    public static function latest(int $page = 1, int $perPage = 50): array
    {
        $pdo = Db::pdo();
        $total = (int) $pdo->query('SELECT COUNT(*) c FROM modlog')->fetch()['c'];
        $offset = max(0, ($page - 1) * $perPage);
        $st = $pdo->prepare(
            'SELECT m.*, u.username AS actor FROM modlog m LEFT JOIN users u ON u.id=m.actor_id ORDER BY m.id DESC LIMIT ? OFFSET ?'
        );
        $st->bindValue(1, $perPage, \PDO::PARAM_INT);
        $st->bindValue(2, $offset, \PDO::PARAM_INT);
        $st->execute();
        return ['entries' => $st->fetchAll(), 'total' => $total];
    }
}

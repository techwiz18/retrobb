<?php
declare(strict_types=1);

namespace RetroBB\Models;

use RetroBB\Core\Db;
use RetroBB\Core\Modlog;

class Moderation
{
    /** Warn a user. Refuses admin targets (any actor) and mod targets unless
     *  the actor is super. Returns false on refusal. */
    public static function warn(int $userId, int $modId, string $reason, int $postId = 0, int $topicId = 0): bool
    {
        $reason = mb_substr(trim($reason), 0, 500);
        [$target, $actorSuper] = self::staffContext($userId, $modId);
        if ($target === 'admin') {
            return false;
        }
        if ($target === 'mod' && !$actorSuper) {
            return false;
        }
        try {
            Db::transact(function () use ($userId, $modId, $reason, $postId, $topicId): void {
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
            });
        } catch (\Throwable) {
            return false;
        }
        return true;
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
        return Db::countQuery('SELECT COUNT(*) c FROM warnings WHERE user_id=?', [$userId]);
    }

    /** $days null = permanent. Returns ban id, or 0 on refusal (admin targets
     *  always; mod targets need a super actor). */
    public static function ban(int $userId, int $modId, string $reason, ?int $days): int
    {
        [$target, $actorSuper] = self::staffContext($userId, $modId);
        if ($target === 'admin') {
            return 0;
        }
        if ($target === 'mod' && !$actorSuper) {
            return 0;
        }
        $expires = $days === null ? null : date('Y-m-d H:i:s', time() + $days * 86400);
        $id = 0;
        Db::transact(function () use ($userId, $modId, $reason, $expires, $days, &$id): void {
            Db::pdo()->prepare(
                'INSERT INTO bans (user_id, banned_by, reason, expires_at, created_at) VALUES (?,?,?,?,?)'
            )->execute([$userId, $modId, mb_substr(trim($reason), 0, 500), $expires, date('Y-m-d H:i:s')]);
            $id = (int) Db::pdo()->lastInsertId();
            Modlog::log($modId, 'ban', 'user', $userId, ($days === null ? 'permanent' : $days . 'd') . ': ' . mb_substr(trim($reason), 0, 150));
        });
        return $id;
    }

    /** Lifts a ban by ban id, with an optional unban note for the modlog.
     *  Returns the unbanned user id (0 when missing). */
    public static function unban(int $banId, int $modId, string $note = ''): int
    {
        $st = Db::pdo()->prepare('SELECT * FROM bans WHERE id=?');
        $st->execute([$banId]);
        $ban = $st->fetch();
        if (!$ban) {
            return 0;
        }
        Db::pdo()->prepare('UPDATE bans SET lifted_at=? WHERE id=?')->execute([date('Y-m-d H:i:s'), $banId]);
        $detail = 'ban #' . $banId;
        $note = mb_substr(trim($note), 0, 200);
        if ($note !== '') {
            $detail .= ': ' . $note;
        }
        Modlog::log($modId, 'unban', 'user', (int) $ban['user_id'], $detail);
        return (int) $ban['user_id'];
    }

    /** Staff-post rule, shared by every post edit/delete path: anyone may
        touch their own posts, but another staffer's post needs a super
        admin. Returns the error or null. */
    public static function staffPostError(array $post, int $actorId, bool $actorSuper, string $verb): ?string
    {
        if ((int) ($post['user_id'] ?? 0) !== $actorId
            && ($post['user_group'] ?? 'member') !== 'member'
            && !$actorSuper) {
            return 'Only super admins can ' . $verb . ' staff posts.';
        }
        return null;
    }

    /** Target group + whether the actor is super. Pre-tier schemas lack the
     *  column: then every admin counts as super (old behavior preserved). */
    private static function staffContext(int $userId, int $modId): array
    {
        try {
            $st = Db::pdo()->prepare('SELECT id, user_group, is_super FROM users WHERE id IN (?,?)');
            $st->execute([$userId, $modId]);
            $rows = [];
            foreach ($st->fetchAll() as $row) {
                $rows[(int) $row['id']] = $row;
            }
            $tGroup = $rows[$userId]['user_group'] ?? 'member';
            $aGroup = $rows[$modId]['user_group'] ?? 'member';
            $aSuper = $aGroup === 'admin' && (int) ($rows[$modId]['is_super'] ?? 0) === 1;
            return [$tGroup, $aSuper];
        } catch (\Throwable) {
            $st = Db::pdo()->prepare('SELECT id, user_group FROM users WHERE id IN (?,?)');
            $st->execute([$userId, $modId]);
            $groups = [];
            foreach ($st->fetchAll() as $row) {
                $groups[(int) $row['id']] = $row['user_group'] ?? 'member';
            }
            $tGroup = $groups[$userId] ?? 'member';
            $aGroup = $groups[$modId] ?? 'member';
            return [$tGroup, $aGroup === 'admin'];
        }
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

    /** Total ban rows (for paging the mod/admin ban history). */
    public static function banCount(): int
    {
        return Db::countQuery('SELECT COUNT(*) c FROM bans');
    }

    /** How many bans are in force right now (counts every row, no window). */
    public static function activeBanCount(): int
    {
        return Db::countQuery(
            'SELECT COUNT(*) c FROM bans WHERE lifted_at IS NULL AND (expires_at IS NULL OR expires_at > ?)',
            [date('Y-m-d H:i:s')]
        );
    }

    /** @return array active + recent bans */
    public static function banPage(int $page = 1, int $perPage = 50): array    {
        $page = max(1, $page);
        $perPage = max(1, min(200, $perPage));
        $offset = ($page - 1) * $perPage;
        $st = Db::pdo()->prepare(
            'SELECT b.*, u.username, a.username AS banned_by_name FROM bans b JOIN users u ON u.id=b.user_id JOIN users a ON a.id=b.banned_by ORDER BY b.id DESC LIMIT ? OFFSET ?'
        );
        $st->bindValue(1, $perPage, \PDO::PARAM_INT);
        $st->bindValue(2, $offset, \PDO::PARAM_INT);
        $st->execute();
        return $st->fetchAll();
    }
}

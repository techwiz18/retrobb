<?php
declare(strict_types=1);

namespace RetroBB\Models;

use RetroBB\Core\BBCode;
use RetroBB\Core\Db;
use RetroBB\Core\Slug;

class Pm
{
    public static function send(int $senderId, int $recipientId, string $subject, string $bbcode, int $replyTo = 0): array
    {
        $subject = trim($subject);
        $bbcode = trim($bbcode);
        if ($recipientId <= 0 || $recipientId === $senderId) {
            return ['ok' => false, 'error' => 'Pick a valid recipient.'];
        }
        if ($subject !== '' && mb_strlen($subject) > 120) {
            return ['ok' => false, 'error' => 'Subject must be under 120 characters.'];
        }
        if (mb_strlen($bbcode) < 2 || mb_strlen($bbcode) > 20000) {
            return ['ok' => false, 'error' => 'Message is too short or too long.'];
        }
        $to = User::find($recipientId);
        if (!$to) {
            return ['ok' => false, 'error' => 'Recipient not found.'];
        }
        if ($subject === '') {
            $subject = '(no subject)';
        }
        // Same anti-spam flood control as forum posts (mods bypass).
        $flood = max(0, (int) setting('flood_seconds', '30'));
        if ($flood > 0 && !\RetroBB\Core\Auth::isMod()) {
            $st = Db::pdo()->prepare('SELECT created_at FROM pms WHERE sender_id=? ORDER BY id DESC LIMIT 1');
            $st->execute([$senderId]);
            if ($last = $st->fetch()) {
                try {
                    $wait = $flood - max(0, time() - (new \DateTime($last['created_at']))->getTimestamp());
                } catch (\Throwable) {
                    $wait = 0;
                }
                if ($wait > 0) {
                    return ['ok' => false, 'error' => "Slow down — please wait $wait more second(s)."];
                }
            }
        }
        // Threading: only link replies the sender is actually allowed to see.
        if ($replyTo > 0 && !self::findFor($replyTo, $senderId)) {
            $replyTo = 0;
        }
        Db::pdo()->prepare(
            'INSERT INTO pms (sender_id, recipient_id, subject, body_bbcode, body_html, created_at, reply_to_id) VALUES (?,?,?,?,?,?,?)'
        )->execute([$senderId, $recipientId, mb_substr($subject, 0, 120), $bbcode, BBCode::toHtml($bbcode), date('Y-m-d H:i:s'), $replyTo]);
        return ['ok' => true, 'id' => (int) Db::pdo()->lastInsertId()];
    }

    public static function inbox(int $userId, int $page = 1, int $perPage = 25): array
    {
        $pdo = Db::pdo();
        $cnt = $pdo->prepare('SELECT COUNT(*) c FROM pms WHERE recipient_id=? AND recipient_deleted=0');
        $cnt->execute([$userId]);
        $total = (int) $cnt->fetch()['c'];
        $offset = max(0, ($page - 1) * $perPage);
        $st = $pdo->prepare(
            'SELECT p.*, u.username AS sender_name FROM pms p JOIN users u ON u.id=p.sender_id WHERE p.recipient_id=? AND p.recipient_deleted=0 ORDER BY p.id DESC LIMIT ? OFFSET ?'
        );
        $st->bindValue(1, $userId, \PDO::PARAM_INT);
        $st->bindValue(2, $perPage, \PDO::PARAM_INT);
        $st->bindValue(3, $offset, \PDO::PARAM_INT);
        $st->execute();
        return ['items' => $st->fetchAll(), 'total' => $total];
    }

    public static function findFor(int $id, int $userId): ?array
    {
        $st = Db::pdo()->prepare(
            'SELECT p.*, s.username AS sender_name, r.username AS recipient_name FROM pms p JOIN users s ON s.id=p.sender_id JOIN users r ON r.id=p.recipient_id WHERE p.id=? AND ((p.sender_id=? AND p.sender_deleted=0) OR (p.recipient_id=? AND p.recipient_deleted=0)) LIMIT 1'
        );
        $st->execute([$id, $userId, $userId]);
        $r = $st->fetch();
        return $r ?: null;
    }

    public static function markRead(int $id, int $userId): void
    {
        Db::pdo()->prepare('UPDATE pms SET read_at=? WHERE id=? AND recipient_id=? AND read_at IS NULL')
            ->execute([date('Y-m-d H:i:s'), $id, $userId]);
    }

    public static function unreadCount(int $userId): int
    {
        try {
            $st = Db::pdo()->prepare('SELECT COUNT(*) c FROM pms WHERE recipient_id=? AND read_at IS NULL AND recipient_deleted=0');
            $st->execute([$userId]);
            return (int) $st->fetch()['c'];
        } catch (\Throwable) {
            return 0;
        }
    }

    public static function outbox(int $userId, int $page = 1, int $perPage = 25): array
    {
        $pdo = Db::pdo();
        $cnt = $pdo->prepare('SELECT COUNT(*) c FROM pms WHERE sender_id=? AND sender_deleted=0');
        $cnt->execute([$userId]);
        $total = (int) $cnt->fetch()['c'];
        $offset = max(0, ($page - 1) * $perPage);
        $st = $pdo->prepare(
            'SELECT p.*, u.username AS recipient_name FROM pms p JOIN users u ON u.id=p.recipient_id WHERE p.sender_id=? AND p.sender_deleted=0 ORDER BY p.id DESC LIMIT ? OFFSET ?'
        );
        $st->bindValue(1, $userId, \PDO::PARAM_INT);
        $st->bindValue(2, $perPage, \PDO::PARAM_INT);
        $st->bindValue(3, $offset, \PDO::PARAM_INT);
        $st->execute();
        return ['items' => $st->fetchAll(), 'total' => $total];
    }

    /** Messages this user deleted (from inbox or sent). Purged once both sides deleted. */
    public static function trash(int $userId, int $page = 1, int $perPage = 25): array
    {
        $pdo = Db::pdo();
        $cnt = $pdo->prepare('SELECT COUNT(*) c FROM pms WHERE (sender_id=? AND sender_deleted=1) OR (recipient_id=? AND recipient_deleted=1)');
        $cnt->execute([$userId, $userId]);
        $total = (int) $cnt->fetch()['c'];
        $offset = max(0, ($page - 1) * $perPage);
        $st = $pdo->prepare(
            'SELECT p.*, s.username AS sender_name, r.username AS recipient_name FROM pms p JOIN users s ON s.id=p.sender_id JOIN users r ON r.id=p.recipient_id WHERE (p.sender_id=? AND p.sender_deleted=1) OR (p.recipient_id=? AND p.recipient_deleted=1) ORDER BY p.id DESC LIMIT ? OFFSET ?'
        );
        $st->bindValue(1, $userId, \PDO::PARAM_INT);
        $st->bindValue(2, $userId, \PDO::PARAM_INT);
        $st->bindValue(3, $perPage, \PDO::PARAM_INT);
        $st->bindValue(4, $offset, \PDO::PARAM_INT);
        $st->execute();
        return ['items' => $st->fetchAll(), 'total' => $total];
    }

    /** Soft-delete for this user's side; hard-delete once both sides deleted. */
    public static function deleteFor(int $id, int $userId): bool
    {
        $pdo = Db::pdo();
        $st = $pdo->prepare('SELECT sender_id, recipient_id, sender_deleted, recipient_deleted FROM pms WHERE id=? LIMIT 1');
        $st->execute([$id]);
        $row = $st->fetch();
        if (!$row) {
            return false;
        }
        if ((int) $row['sender_id'] === $userId) {
            $pdo->prepare('UPDATE pms SET sender_deleted=1 WHERE id=?')->execute([$id]);
        } elseif ((int) $row['recipient_id'] === $userId) {
            // Deleting also clears the unread badge for this message.
            $pdo->prepare('UPDATE pms SET read_at=COALESCE(read_at, ?) WHERE id=?')->execute([date('Y-m-d H:i:s'), $id]);
            $pdo->prepare('UPDATE pms SET recipient_deleted=1 WHERE id=?')->execute([$id]);
        } else {
            return false;
        }
        $pdo->prepare('DELETE FROM pms WHERE id=? AND sender_deleted=1 AND recipient_deleted=1')->execute([$id]);
        return true;
    }

    public static function sentCount(int $userId): int
    {
        try {
            $st = Db::pdo()->prepare('SELECT COUNT(*) c FROM pms WHERE sender_id=? AND sender_deleted=0');
            $st->execute([$userId]);
            return (int) $st->fetch()['c'];
        } catch (\Throwable) {
            return 0;
        }
    }

    public static function trashCount(int $userId): int
    {
        try {
            $st = Db::pdo()->prepare('SELECT COUNT(*) c FROM pms WHERE (sender_id=? AND sender_deleted=1) OR (recipient_id=? AND recipient_deleted=1)');
            $st->execute([$userId, $userId]);
            return (int) $st->fetch()['c'];
        } catch (\Throwable) {
            return 0;
        }
    }

    // ---- drafts ----

    public static function saveDraft(int $userId, string $to, string $subject, string $bbcode, int $draftId = 0, int $replyTo = 0): array
    {
        $to = trim(mb_substr($to, 0, 50));
        $subject = trim(mb_substr($subject, 0, 120));
        $bbcode = trim($bbcode);
        if ($to === '' && $subject === '' && $bbcode === '') {
            return ['ok' => false, 'error' => 'Nothing to save yet.'];
        }
        if (mb_strlen($bbcode) > 20000) {
            return ['ok' => false, 'error' => 'Draft is too long.'];
        }
        $now = date('Y-m-d H:i:s');
        try {
            if ($draftId > 0) {
                $chk = Db::pdo()->prepare('SELECT id FROM pm_drafts WHERE id=? AND user_id=? LIMIT 1');
                $chk->execute([$draftId, $userId]);
                if (!$chk->fetch()) {
                    return ['ok' => false, 'error' => 'Draft not found.'];
                }
                Db::pdo()->prepare('UPDATE pm_drafts SET to_name=?, subject=?, body_bbcode=?, updated_at=?, reply_to_id=? WHERE id=? AND user_id=?')
                    ->execute([$to, $subject, $bbcode, $now, $replyTo, $draftId, $userId]);
                return ['ok' => true, 'id' => $draftId];
            }
            Db::pdo()->prepare('INSERT INTO pm_drafts (user_id, to_name, subject, body_bbcode, created_at, updated_at, reply_to_id) VALUES (?,?,?,?,?,?,?)')
                ->execute([$userId, $to, $subject, $bbcode, $now, $now, $replyTo]);
            return ['ok' => true, 'id' => (int) Db::pdo()->lastInsertId()];
        } catch (\Throwable) {
            return ['ok' => false, 'error' => 'Could not save draft.'];
        }
    }

    public static function drafts(int $userId): array
    {
        try {
            $st = Db::pdo()->prepare('SELECT * FROM pm_drafts WHERE user_id=? ORDER BY updated_at DESC LIMIT 50');
            $st->execute([$userId]);
            return $st->fetchAll();
        } catch (\Throwable) {
            return [];
        }
    }

    public static function findDraft(int $id, int $userId): ?array
    {
        try {
            $st = Db::pdo()->prepare('SELECT * FROM pm_drafts WHERE id=? AND user_id=? LIMIT 1');
            $st->execute([$id, $userId]);
            $r = $st->fetch();
            return $r ?: null;
        } catch (\Throwable) {
            return null;
        }
    }

    public static function discardDraft(int $id, int $userId): void
    {
        try {
            Db::pdo()->prepare('DELETE FROM pm_drafts WHERE id=? AND user_id=?')->execute([$id, $userId]);
        } catch (\Throwable) {
        }
    }

    /** Replies to this message that $userId is allowed to see, oldest first. */
    public static function replies(int $id, int $userId): array
    {
        try {
            $st = Db::pdo()->prepare(
                'SELECT p.*, s.username AS sender_name FROM pms p JOIN users s ON s.id=p.sender_id WHERE p.reply_to_id=? AND ((p.sender_id=? AND p.sender_deleted=0) OR (p.recipient_id=? AND p.recipient_deleted=0)) ORDER BY p.id ASC LIMIT 50'
            );
            $st->execute([$id, $userId, $userId]);
            return $st->fetchAll();
        } catch (\Throwable) {
            return [];
        }
    }

    /** Resolve a draft's To: line the same way the compose form does. */
    public static function resolveRecipient(string $toName): ?array
    {
        $toName = trim($toName);
        if ($toName === '' || str_contains($toName, '@')) {
            // Usernames can't contain @ — never resolve by email address.
            return null;
        }
        $to = User::findByUsername($toName);
        if (!$to && preg_match('/^(.+)\.u(\d+)$/', $toName, $m)) {
            $cand = User::find((int) $m[2]);
            // The name part must match the username or its profile slug,
            // so "someone.u5" can't silently address a different account.
            $want = strtolower(trim($m[1]));
            if ($cand && ($want === strtolower($cand['username']) || $want === Slug::make($cand['username']))) {
                $to = $cand;
            }
        }
        if (!$to) {
            $to = User::findByLogin($toName);
        }
        return $to;
    }
}

<?php
declare(strict_types=1);

namespace RetroBB\Models;

use RetroBB\Core\Db;

/**
 * Single-use password-reset tokens. Only SHA-256 hashes touch the database;
 * the raw token travels by email alone. Tokens live one hour; requesting
 * again retires older ones so exactly one per user is valid at a time.
 */
class PasswordReset
{
    public const TTL = 3600;

    /** Issue a token; returns the raw token (to email) or '' when throttled. */
    public static function issue(int $userId): string
    {
        if ($userId <= 0) {
            return '';
        }
        try {
            $pdo = Db::pdo();
            $now = date('Y-m-d H:i:s');
            // At most 3 issues per user per hour. Superseded rows are retired,
            // not deleted, so the count survives rotation; expired rows sweep.
            $cnt = $pdo->prepare('SELECT COUNT(*) c FROM password_resets WHERE user_id=? AND created_at > ?');
            $cnt->execute([$userId, date('Y-m-d H:i:s', time() - 3600)]);
            if ((int) $cnt->fetch()['c'] >= 3) {
                return '';
            }
            $pdo->prepare('UPDATE password_resets SET used_at=? WHERE user_id=? AND used_at IS NULL')->execute([$now, $userId]);
            $pdo->prepare('DELETE FROM password_resets WHERE expires_at < ?')->execute([$now]);
            $token = bin2hex(random_bytes(32));
            $pdo->prepare('INSERT INTO password_resets (user_id, token_hash, expires_at, created_at) VALUES (?,?,?,?)')
                ->execute([$userId, hash('sha256', $token), date('Y-m-d H:i:s', time() + self::TTL), $now]);
            return $token;
        } catch (\Throwable) {
            return '';
        }
    }

    /** The user behind a live token, or null (unknown, expired, or used). */
    public static function check(string $token): ?array
    {
        if (!preg_match('/^[0-9a-f]{64}$/', $token)) {
            return null;
        }
        try {
            $st = Db::pdo()->prepare(
                'SELECT r.*, u.username, u.email FROM password_resets r JOIN users u ON u.id=r.user_id WHERE r.token_hash=? AND r.used_at IS NULL LIMIT 1'
            );
            $st->execute([hash('sha256', $token)]);
            $row = $st->fetch();
            if (!$row || strtotime($row['expires_at']) < time()) {
                return null;
            }
            return $row;
        } catch (\Throwable) {
            return null;
        }
    }

    /** Set the new password and burn every token for that user. */
    public static function consume(string $token, string $password): array
    {
        if (strlen($password) < 8) {
            return ['ok' => false, 'error' => 'New password must be at least 8 characters.'];
        }
        $row = self::check($token);
        if (!$row) {
            return ['ok' => false, 'error' => 'This reset link is invalid or expired. Request a new one.'];
        }
        try {
            $pdo = Db::pdo();
            $pdo->prepare("UPDATE users SET password_hash=?, auth_scheme='modern', passwd_salt='' WHERE id=?")
                ->execute([password_hash_safe($password), $row['user_id']]);
            $pdo->prepare('UPDATE password_resets SET used_at=? WHERE user_id=? AND used_at IS NULL')
                ->execute([date('Y-m-d H:i:s'), $row['user_id']]);
        } catch (\Throwable) {
            // Pre-011 tables lack the scheme columns — password still changes.
            try {
                Db::pdo()->prepare('UPDATE users SET password_hash=? WHERE id=?')->execute([password_hash_safe($password), $row['user_id']]);
                Db::pdo()->prepare('UPDATE password_resets SET used_at=? WHERE user_id=? AND used_at IS NULL')
                    ->execute([date('Y-m-d H:i:s'), $row['user_id']]);
            } catch (\Throwable $t) {
                return ['ok' => false, 'error' => 'Could not save the new password.'];
            }
        }
        return ['ok' => true, 'user_id' => (int) $row['user_id']];
    }
}

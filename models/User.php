<?php
declare(strict_types=1);

namespace RetroBB\Models;

use RetroBB\Core\Db;
use PDO;

class User
{
    public static function find(int $id): ?array
    {
        $st = Db::pdo()->prepare('SELECT * FROM users WHERE id=?');
        $st->execute([$id]);
        $r = $st->fetch();
        return $r ?: null;
    }

    public static function findByLogin(string $login): ?array
    {
        // Case-insensitive so "Admin" and "admin" are the same account.
        $st = Db::pdo()->prepare('SELECT * FROM users WHERE LOWER(username)=LOWER(?) OR LOWER(email)=LOWER(?) LIMIT 1');
        $st->execute([$login, $login]);
        $r = $st->fetch();
        return $r ?: null;
    }

    public static function findByUsername(string $username): ?array
    {
        $st = Db::pdo()->prepare('SELECT * FROM users WHERE username=? LIMIT 1');
        $st->execute([$username]);
        $r = $st->fetch();
        return $r ?: null;
    }

    public static function create(string $username, string $email, string $password): array
    {
        $errors = [];
        if (!preg_match('/^[A-Za-z0-9_\- ]{3,30}$/', $username)) {
            $errors[] = 'Username must be 3–30 chars (letters, numbers, space, _ -).';
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Invalid email address.';
        }
        if (strlen($password) < 8) {
            $errors[] = 'Password must be at least 8 characters.';
        }
        if ($errors) {
            return ['ok' => false, 'errors' => $errors];
        }
        // Case-insensitive uniqueness (SQLite UNIQUE is case-sensitive).
        $chk = Db::pdo()->prepare('SELECT id FROM users WHERE LOWER(username)=LOWER(?) OR LOWER(email)=LOWER(?) LIMIT 1');
        $chk->execute([$username, $email]);
        if ($chk->fetch()) {
            return ['ok' => false, 'errors' => ['Username or email already taken.']];
        }
        $hash = password_hash_safe($password);
        try {
            $st = Db::pdo()->prepare('INSERT INTO users (username, email, password_hash, user_group, created_at) VALUES (?,?,?,?,?)');
            $st->execute([$username, $email, $hash, 'member', date('Y-m-d H:i:s')]);
            return ['ok' => true, 'id' => (int) Db::pdo()->lastInsertId()];
        } catch (\Throwable $t) {
            return ['ok' => false, 'errors' => ['Username or email already taken.']];
        }
    }

    public static function all(int $limit = 100): array
    {
        return Db::pdo()->query('SELECT id, username, user_group, posts_count, created_at FROM users ORDER BY created_at ASC LIMIT ' . $limit)->fetchAll();
    }

    public static function setGroup(int $id, string $group): void
    {
        $group = in_array($group, ['admin', 'mod', 'member'], true) ? $group : 'member';
        Db::pdo()->prepare('UPDATE users SET user_group=? WHERE id=?')->execute([$group, $id]);
    }

    /** Update a member's own editable fields. Returns [ok, error?]. */
    public static function updateProfile(int $id, string $email, string $bio): array
    {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['ok' => false, 'error' => 'Invalid email address.'];
        }
        $chk = Db::pdo()->prepare('SELECT id FROM users WHERE LOWER(email)=LOWER(?) AND id != ? LIMIT 1');
        $chk->execute([$email, $id]);
        if ($chk->fetch()) {
            return ['ok' => false, 'error' => 'That email is already in use.'];
        }
        Db::pdo()->prepare('UPDATE users SET email=?, bio=? WHERE id=?')
            ->execute([$email, mb_substr(trim($bio), 0, 1000), $id]);
        return ['ok' => true];
    }

    /** Change password after verifying the current one. Returns [ok, error?]. */
    public static function changePassword(int $id, string $current, string $new): array
    {
        if (strlen($new) < 8) {
            return ['ok' => false, 'error' => 'New password must be at least 8 characters.'];
        }
        $user = self::find($id);
        if (!$user || !password_verify($current, $user['password_hash'])) {
            return ['ok' => false, 'error' => 'Current password is wrong.'];
        }
        Db::pdo()->prepare('UPDATE users SET password_hash=? WHERE id=?')->execute([password_hash_safe($new), $id]);
        return ['ok' => true];
    }
}

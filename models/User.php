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
        $st = Db::pdo()->prepare('SELECT * FROM users WHERE username=? OR email=? LIMIT 1');
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
}

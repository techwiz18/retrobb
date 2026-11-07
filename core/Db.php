<?php
declare(strict_types=1);

namespace RetroBB\Core;

use PDO;

class Db
{
    private static ?PDO $pdo = null;

    public static function pdo(): PDO
    {
        if (self::$pdo) {
            return self::$pdo;
        }
        // MySQL 8 (or MariaDB 10.6+) is the only supported backend.
        // Connection details live in config.php (see config.example.php).
        $cfg = require dirname(__DIR__) . '/config.php';
        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
            $cfg['mysql_host'] ?? '127.0.0.1',
            $cfg['mysql_port'] ?? 3306,
            $cfg['mysql_db'] ?? 'retrobb'
        );
        try {
            $pdo = new PDO($dsn, $cfg['mysql_user'] ?? 'retrobb', $cfg['mysql_pass'] ?? '', [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                // Native prepares: bound values never become SQL text, even
                // if a future query forgets a cast somewhere upstream.
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
            // The charset= DSN parameter is ignored by libmysql-based PHP
            // builds (common on shared hosts): without this, the connection
            // falls back to latin1 and every 4-byte character (all our
            // emoticon emoji) degrades to "?".
            $pdo->exec("SET NAMES 'utf8mb4'");
        } catch (\PDOException $e) {
            throw new \RuntimeException('Database connection failed: ' . $e->getMessage());
        }
        self::$pdo = $pdo;
        return $pdo;
    }

    public static function reset(): void
    {
        self::$pdo = null;
    }

    /** Single COUNT(*) query that returns 0 when the table is missing. */
    public static function countQuery(string $sql, array $params = []): int
    {
        try {
            $st = self::pdo()->prepare($sql);
            $st->execute($params);
            return (int) ($st->fetch()['c'] ?? 0);
        } catch (\Throwable) {
            return 0;
        }
    }

    /** Run $fn inside a transaction, joining an active one (nested model
        calls share the outer transaction instead of committing early). */
    public static function transact(callable $fn): mixed
    {
        $pdo = self::pdo();
        if ($pdo->inTransaction()) {
            return $fn();
        }
        $pdo->beginTransaction();
        try {
            $result = $fn();
            $pdo->commit();
            return $result;
        } catch (\Throwable $t) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $t;
        }
    }
}

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
            ]);
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
}

<?php
declare(strict_types=1);

namespace RetroBB\Core;

use PDO;
use PDOException;

class Db
{
    private static ?PDO $pdo = null;

    public static function pdo(): PDO
    {
        if (self::$pdo) {
            return self::$pdo;
        }
        $cfg = require dirname(__DIR__) . '/config.php';
        $driver = $cfg['db_driver'] ?? 'sqlite';
        if ($driver === 'mysql') {
            $dsn = sprintf(
                'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
                $cfg['mysql_host'],
                $cfg['mysql_port'] ?? 3306,
                $cfg['mysql_db']
            );
            $pdo = new PDO($dsn, $cfg['mysql_user'], $cfg['mysql_pass'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
        } else {
            $path = $cfg['sqlite_path'];
            $dir = dirname($path);
            if (!is_dir($dir)) {
                mkdir($dir, 0775, true);
            }
            $pdo = new PDO('sqlite:' . $path, null, null, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
            $pdo->exec('PRAGMA foreign_keys = ON');
        }
        self::$pdo = $pdo;
        return $pdo;
    }

    public static function reset(): void
    {
        self::$pdo = null;
    }
}

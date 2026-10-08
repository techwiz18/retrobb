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
            // Never auto-create here: merely connecting would conjure an empty
            // file and defeat the installer's exists-check. The migrator and
            // installer create it explicitly.
            if (!is_file($path)) {
                throw new \RuntimeException('Database not installed: ' . $path);
            }
            $pdo = new PDO('sqlite:' . $path, null, null, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
            $pdo->exec('PRAGMA foreign_keys = ON');
            // Best-effort durability/concurrency tuning: on read-only storage
            // (fresh deploy before chown, locked-down hosts) these must never fatal.
            foreach (['journal_mode = WAL', 'busy_timeout = 5000', 'synchronous = NORMAL'] as $pragma) {
                try {
                    $pdo->exec('PRAGMA ' . $pragma);
                } catch (\Throwable $t) {
                    error_log('RetroBB PRAGMA failed (' . $pragma . '): ' . $t->getMessage());
                }
            }
        }
        self::$pdo = $pdo;
        return $pdo;
    }

    public static function reset(): void
    {
        self::$pdo = null;
    }
}

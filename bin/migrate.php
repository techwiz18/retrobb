<?php
declare(strict_types=1);
// RetroBB migrator + seeder. Usage:
//   php bin/migrate.php            (migrate only)
//   php bin/migrate.php --seed     (migrate + demo seed)
//   php bin/migrate.php --fresh    (wipe sqlite file, migrate)
//   php bin/migrate.php --fresh --seed

$root = dirname(__DIR__);
require_once $root . '/core/Db.php';

use RetroBB\Core\Db;

$args = $argv ?? [];
$fresh = in_array('--fresh', $args, true);
$seed = in_array('--seed', $args, true);

$config = require $root . '/config.php';
if (($config['db_driver'] ?? 'sqlite') === 'sqlite' && $fresh) {
    $path = $config['sqlite_path'];
    if (is_file($path)) {
        unlink($path);
        echo "Wiped $path\n";
    }
    Db::reset();
}
// The migrator owns file creation (Db::pdo deliberately refuses to conjure it).
if (($config['db_driver'] ?? 'sqlite') === 'sqlite') {
    $dir = dirname($config['sqlite_path']);
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }
    if (!is_file($config['sqlite_path'])) {
        touch($config['sqlite_path']);
        echo 'Created ' . $config['sqlite_path'] . "\n";
    }
}

$pdo = Db::pdo();
$driver = $config['db_driver'] ?? 'sqlite';
$migrations = glob($root . '/migrations/*.sql');
sort($migrations);
foreach ($migrations as $file) {
    // Prefer the MySQL dialect variant when one ships for this migration.
    $variant = $root . '/migrations/mysql/' . basename($file);
    if ($driver === 'mysql' && is_file($variant)) {
        $file = $variant;
    }
    echo 'Applying ' . basename($file) . "...\n";
    // Our migration files are plain DDL with one statement per chunk and no
    // semicolons inside statements: strip full-line comments, split on ";".
    $lines = explode("\n", (string) file_get_contents($file));
    $lines = array_filter($lines, fn($l) => !str_starts_with(trim($l), '--'));
    foreach (explode(';', implode("\n", $lines)) as $stmt) {
        $stmt = trim($stmt);
        if ($stmt === '') {
            continue;
        }
        try {
            $pdo->exec($stmt);
        } catch (Throwable $t) {
            // Ignore idempotent re-run noise across both dialects.
            $msg = $t->getMessage();
            if (!str_contains($msg, 'already exists') && !str_contains($msg, 'duplicate column') && !str_contains($msg, 'Duplicate key name')) {
                throw $t;
            }
        }
    }
}

// default settings (portable on SQLite and MySQL)
$defaults = [
    'board_name' => 'RetroBB',
    'board_tagline' => 'An old-school forum for the modern web',
    'board_url' => '',
    'default_skin' => 'classic',
    'posts_per_page' => '15',
    'topics_per_page' => '25',
    'flood_seconds' => '30',
    'edit_window_mins' => '30',
    'captcha_provider' => 'honeypot',
    'captcha_sitekey' => '',
    'captcha_secret' => '',
];
$upd = $pdo->prepare('UPDATE settings SET `value`=? WHERE `key`=?');
$ins = $pdo->prepare('INSERT INTO settings (`key`, `value`) VALUES (?, ?)');
foreach ($defaults as $k => $v) {
    $upd->execute([$v, $k]);
    if ($upd->rowCount() === 0) {
        try {
            $ins->execute([$k, $v]);
        } catch (Throwable) {
            // already present (race) — safe to ignore
        }
    }
}

echo "Migrations done.\n";

if ($seed) {
    require_once $root . '/bin/seed.php';
}

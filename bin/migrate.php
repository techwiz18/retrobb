<?php
declare(strict_types=1);
// RetroBB migrator + seeder (MySQL 8+ / MariaDB 10.6+). Usage:
//   php bin/migrate.php            (migrate only)
//   php bin/migrate.php --seed     (migrate + demo seed)

$root = dirname(__DIR__);
require_once $root . '/core/Db.php';

use RetroBB\Core\Db;

$args = $argv ?? [];
$seed = in_array('--seed', $args, true);

$pdo = Db::pdo();
$migrations = glob($root . '/migrations/*.sql');
sort($migrations);
foreach ($migrations as $file) {
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
            // Ignore idempotent re-run noise.
            $msg = $t->getMessage();
            if (!str_contains($msg, 'already exists') && !str_contains($msg, 'duplicate column') && !str_contains($msg, 'Duplicate key name')) {
                throw $t;
            }
        }
    }
}

// default settings
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
$upsert = $pdo->prepare('INSERT INTO settings (`key`, `value`) VALUES (?, ?) ON DUPLICATE KEY UPDATE `value`=VALUES(`value`)');
foreach ($defaults as $k => $v) {
    $upsert->execute([$k, $v]);
}

echo "Migrations done.\n";

if ($seed) {
    require_once $root . '/bin/seed.php';
}

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
            if (!str_contains($msg, 'already exists') && !str_contains($msg, 'duplicate column') && !str_contains($msg, 'Duplicate key name') && !str_contains($msg, 'Duplicate column')) {
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
    'delete_window_mins' => '30',
    'captcha_provider' => 'honeypot',
    'captcha_sitekey' => '',
    'captcha_secret' => '',
    'mail_from' => '',
    'feature_alerts' => '1',
    'feature_pms' => '1',
    'feature_reactions' => '1',
    'feature_mentions' => '1',
    'skin_selector' => '1',
    'theme_light' => '1',
    'theme_dark' => '1',
    'theme_auto' => '1',
    'default_theme' => 'auto',
];
// default settings: seed-only (INSERT IGNORE) so upgrades never clobber
// an admin's existing values — new keys arrive, old ones stay untouched.
$seedUpsert = $pdo->prepare('INSERT IGNORE INTO settings (`key`, `value`) VALUES (?, ?)');
foreach ($defaults as $k => $v) {
    $seedUpsert->execute([$k, $v]);
}

// charset healing: only utf8mb4 stores 4-byte emoji. Anything else degrades
// them to "?" (or fatals under strict mode), so normalize our tables:
// - utf8mb3 tables convert directly (lossless, same character set family).
// - latin1 tables take the two-step dance per text column (bytes shuttle
//   through a BLOB so UTF-8 content is reinterpreted, never re-encoded).
//   The app has always spoken utf8mb4 on the wire, so stored bytes already
//   are UTF-8 and survive the trip byte-identical.
try {
    $dbName = (string) $pdo->query('SELECT DATABASE()')->fetchColumn();
    $ours = ['users', 'categories', 'forums', 'topics', 'posts', 'settings', 'plugins', 'reports', 'warnings', 'bans', 'modlog', 'notifications', 'post_reactions', 'pms', 'pm_drafts', 'topic_reads', 'password_resets'];
    $nonMb4 = $pdo->query(
        "SELECT TABLE_NAME, TABLE_COLLATION FROM information_schema.TABLES WHERE TABLE_SCHEMA=" . $pdo->quote($dbName)
    )->fetchAll(PDO::FETCH_ASSOC);
    foreach ($nonMb4 as $row) {
        $t = (string) $row['TABLE_NAME'];
        $coll = (string) $row['TABLE_COLLATION'];
        if (!in_array($t, $ours, true) || str_starts_with($coll, 'utf8mb4')) {
            continue;
        }
        $safe = str_replace('`', '', $t);
        if (str_starts_with($coll, 'latin1')) {
            $cols = $pdo->query(
                'SELECT COLUMN_NAME, DATA_TYPE, CHARACTER_MAXIMUM_LENGTH, IS_NULLABLE, COLUMN_DEFAULT, EXTRA FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='
                . $pdo->quote($dbName) . ' AND TABLE_NAME=' . $pdo->quote($t) . " AND DATA_TYPE IN ('char','varchar','text','mediumtext')"
            )->fetchAll(PDO::FETCH_ASSOC);
            foreach ($cols as $c) {
                $cn = str_replace('`', '', (string) $c['COLUMN_NAME']);
                $nullDef = ($c['IS_NULLABLE'] === 'YES') ? ' NULL' : ' NOT NULL';
                $def = '';
                if ($c['COLUMN_DEFAULT'] !== null) {
                    $def = ' DEFAULT ' . $pdo->quote((string) $c['COLUMN_DEFAULT']);
                }
                $extra = trim((string) $c['EXTRA']) !== '' ? ' ' . $c['EXTRA'] : '';
                $blobType = match ((string) $c['DATA_TYPE']) {
                    'char' => 'BINARY(' . (int) $c['CHARACTER_MAXIMUM_LENGTH'] . ')',
                    'varchar' => 'VARBINARY(' . (int) $c['CHARACTER_MAXIMUM_LENGTH'] . ')',
                    'text' => 'BLOB',
                    default => 'MEDIUMBLOB',
                };
                $origType = strtoupper((string) $c['DATA_TYPE']) . ($c['DATA_TYPE'] === 'char' || $c['DATA_TYPE'] === 'varchar' ? '(' . (int) $c['CHARACTER_MAXIMUM_LENGTH'] . ')' : '');
                echo "Converting $safe.$cn to utf8mb4...\n";
                $pdo->exec("ALTER TABLE `$safe` MODIFY `$cn` $blobType$nullDef$extra");
                $pdo->exec("ALTER TABLE `$safe` MODIFY `$cn` $origType CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci$nullDef$def$extra");
            }
            $pdo->exec("ALTER TABLE `$safe` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        } elseif (str_starts_with($coll, 'utf8')) {
            echo 'Converting ' . $safe . " to utf8mb4...\n";
            $pdo->exec('ALTER TABLE `' . $safe . '` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
        }
    }
} catch (Throwable $t) {
    echo 'Charset check skipped (' . $t->getMessage() . ")\n";
}

echo "Migrations done.\n";

if ($seed) {
    require_once $root . '/bin/seed.php';
}

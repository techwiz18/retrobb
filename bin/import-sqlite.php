<?php
declare(strict_types=1);
// One-way rescue importer for pre-1.0 SQLite boards (RetroBB is MySQL-only now).
// Usage: php bin/import-sqlite.php /path/to/retrobb.sqlite
// Imports every known table into the MySQL database from config.php,
// preserving ids, then prints row counts for verification.

$root = dirname(__DIR__);
require_once $root . '/core/Db.php';

use RetroBB\Core\Db;

$srcPath = $argv[1] ?? null;
if (!$srcPath || !is_file($srcPath)) {
    fwrite(STDERR, "Usage: php bin/import-sqlite.php /path/to/retrobb.sqlite\n");
    exit(1);
}

$src = new PDO('sqlite:' . $srcPath, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$dst = Db::pdo();

$tables = ['users', 'categories', 'forums', 'topics', 'posts', 'settings', 'plugins', 'reports', 'warnings', 'bans', 'modlog'];
$existing = [];
foreach ($src->query("SELECT name FROM sqlite_master WHERE type='table'")->fetchAll(PDO::FETCH_COLUMN) as $t) {
    $existing[$t] = true;
}

$dst->exec('SET FOREIGN_KEY_CHECKS=0');
$totals = [];
foreach ($tables as $t) {
    if (!isset($existing[$t])) {
        echo "skip $t (not in source)\n";
        continue;
    }
    // Introspect column names portably (orders can differ per dialect).
    $cols = [];
    $colStmt = $src->query("SELECT * FROM \"$t\" LIMIT 1");
    for ($i = 0; $i < $colStmt->columnCount(); $i++) {
        $meta = $colStmt->getColumnMeta($i);
        $cols[] = $meta['name'];
    }
    $quoted = array_map(fn($c) => '`' . str_replace('`', '', $c) . '`', $cols);
    $placeholders = implode(',', array_fill(0, count($cols), '?'));
    $ins = $dst->prepare('INSERT INTO `' . $t . '` (' . implode(',', $quoted) . ') VALUES (' . $placeholders . ')');
    $n = 0;
    $isSettings = $t === 'settings';
    // Source values win over migrate defaults (native upsert).
    $upsert = $isSettings ? $dst->prepare('INSERT INTO `settings` (`key`, `value`) VALUES (?, ?) ON DUPLICATE KEY UPDATE `value`=VALUES(`value`)') : null;
    foreach ($src->query("SELECT * FROM \"$t\"") as $row) {
        $vals = [];
        foreach ($cols as $c) {
            $vals[] = $row[$c];
        }
        try {
            if ($isSettings) {
                // Migrate defaults already exist: source values win.
                $upsert->execute([$row['key'], $row['value']]);
            } else {
                $ins->execute($vals);
            }
            $n++;
        } catch (Throwable $e) {
            // Likely a duplicate from a previous partial run; report and continue.
            fwrite(STDERR, "  skip row in $t: " . $e->getMessage() . "\n");
        }
    }
    $dstCount = (int) $dst->query("SELECT COUNT(*) c FROM `$t`")->fetch()['c'];
    $totals[$t] = [$n, $dstCount];
    echo str_pad($t, 12) . " imported $n, dest now $dstCount\n";
}
$dst->exec('SET FOREIGN_KEY_CHECKS=1');
echo "Done. Point config.php at this database and visit the board.\n";

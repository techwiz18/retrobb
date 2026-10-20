<?php
declare(strict_types=1);
// Re-render stored post/PM HTML from its BBCode source.
//
// One-time repair for boards that predate the bare-[url] XSS fix (issue #13 /
// C1): already-stored `body_html` keeps injected markup until it is rendered
// again. Safe and idempotent to re-run. Take a database backup first anyway.
//
//   php bin/rebuild-html.php --dry-run   # report only, write nothing
//   php bin/rebuild-html.php             # fix rows with the injection signature
//   php bin/rebuild-html.php --all       # re-render every row (renderer drift)
//
// Default is targeted: only suspicious stored HTML is rewritten, so mentions,
// plugin filters and old formatting on legitimate rows are left untouched.
// Plugins are loaded so `post_body_html` filters reproduce the web output.

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$args = $argv ?? [];
if (in_array('--help', $args, true) || in_array('-h', $args, true)) {
    echo "Usage: php bin/rebuild-html.php [--dry-run] [--all] [--batch=N] [--limit=N]\n";
    exit(0);
}

$root = dirname(__DIR__);
require_once $root . '/core/Db.php';
require_once $root . '/core/helpers.php';
require_once $root . '/core/Slug.php';
require_once $root . '/core/Hooks.php';
require_once $root . '/core/BBCode.php';
require_once $root . '/core/Mentions.php';
require_once $root . '/core/Auth.php';
require_once $root . '/core/Plugins.php';
require_once $root . '/core/HtmlRepair.php';
require_once $root . '/models/User.php';

use RetroBB\Core\Db;
use RetroBB\Core\HtmlRepair;
use RetroBB\Core\Plugins;

$dry = in_array('--dry-run', $args, true);
$all = in_array('--all', $args, true);
$batch = 500;
$limit = 0;
foreach ($args as $arg) {
    if (str_starts_with($arg, '--batch=')) {
        $batch = max(1, (int) substr($arg, 8));
    } elseif (str_starts_with($arg, '--limit=')) {
        $limit = max(0, (int) substr($arg, 8));
    }
}

try {
    Db::pdo();
} catch (Throwable $t) {
    fwrite(STDERR, 'Database unavailable: ' . $t->getMessage() . "\n");
    exit(1);
}

// Plugins may add post_body_html filters; load them so a re-render matches
// what the web front controller would produce.
try {
    Plugins::load();
    \RetroBB\Core\Hooks::do_action('boot');
} catch (Throwable $t) {
    fwrite(STDERR, 'Plugins skipped: ' . $t->getMessage() . "\n");
}

$stats = HtmlRepair::run($limit, $dry, $all, $batch);

printf(
    "%sscanned %d, %s%d, skipped %d, conflicted %d, failed %d%s\n",
    $dry ? '[dry-run] ' : '',
    $stats['scanned'],
    $dry ? 'would rebuild ' : 'rebuilt ',
    $stats['changed'],
    $stats['skipped'],
    $stats['conflicted'],
    $stats['failed'],
    $stats['more'] ? ' (more remain — run again)' : ''
);

exit($stats['failed'] > 0 ? 1 : 0);

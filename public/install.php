<?php
declare(strict_types=1);
// Web installer: creates config + runs migrations + seed. Locks via storage/installed.lock.
$root = dirname(__DIR__);
$lock = $root . '/storage/installed.lock';
if (is_file($lock) && !isset($_GET['force'])) {
    http_response_code(403);
    echo '<h1>RetroBB is already installed.</h1><p>Delete storage/installed.lock to re-run.</p>';
    exit;
}
$message = '';
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    require $root . '/core/Db.php';
    // migrate.php reads $argv (not $_SERVER['argv']); same scope via require.
    $argv = ['migrate.php', '--seed'];
    require $root . '/bin/migrate.php';
    file_put_contents($lock, date('c'));
    $message = 'Installed! Admin login: <b>admin / admin123</b>. <a href="/">Visit board</a> (delete <code>public/install.php</code> when done).';
}
?>
<!doctype html><html><head><meta charset="utf-8"><title>Install RetroBB</title>
<style>body{font-family:Verdana,Arial,sans-serif;background:#2b3a55;color:#fff;display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0}.box{background:#fff;color:#222;padding:28px;border-radius:8px;max-width:520px;box-shadow:0 10px 40px rgba(0,0,0,.4)}h1{margin-top:0}.btn{background:#3A6EA5;color:#fff;border:0;padding:10px 18px;border-radius:4px;font-size:15px;cursor:pointer}</style>
</head><body><div class="box">
<h1>Install RetroBB</h1>
<p>Creates SQLite DB, runs migrations, seeds demo boards + <b>admin/admin123</b>.</p>
<?php if ($message): ?><p><?= $message ?></p><?php else: ?>
<form method="post"><button class="btn" type="submit">Install now</button></form>
<?php endif; ?>
<p style="font-size:12px;color:#666">Or via CLI: <code>php bin/migrate.php --seed</code></p>
</div></body></html>

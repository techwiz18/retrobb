<?php
declare(strict_types=1);
// RetroBB web installer wizard: requirements -> database + board + admin -> done.
// Writes config.php, runs migrations, creates the owner account, then locks.
$root = dirname(__DIR__);
require_once $root . '/core/Db.php';
require_once $root . '/core/helpers.php';

use RetroBB\Core\Db;

$lock = $root . '/storage/installed.lock';
$installed = is_file($lock) && !isset($_GET['force']);
if ($installed) {
    // A stale lock (e.g. after switching DB drivers) must never brick setup:
    // only treat the board as installed when the configured DB has users.
    try {
        $n = Db::pdo()->query('SELECT COUNT(*) c FROM users')->fetch()['c'] ?? 0;
        if ((int) $n === 0) {
            $installed = false;
        }
    } catch (Throwable) {
        $installed = false;
    }
}

$step = isset($_GET['step']) ? (string) $_GET['step'] : 'welcome';
$errors = [];
$values = [
    'mysql_host' => '127.0.0.1', 'mysql_port' => '3306', 'mysql_db' => 'retrobb',
    'mysql_user' => 'retrobb', 'mysql_pass' => '',
    'board_name' => 'RetroBB', 'board_tagline' => 'An old-school forum for the modern web',
    'board_url' => '', 'default_skin' => 'classic',
    'admin_user' => '', 'admin_email' => '', 'demo' => '',
];

function req_row(string $label, bool $ok, string $hint = ''): string
{
    return '<div class="recent-row"><span>' . e($label) . '<br><small class="muted">' . e($hint) . '</small></span>'
        . '<b style="color:' . ($ok ? '#080' : '#a00') . '">' . ($ok ? '✓' : '✗') . '</b></div>';
}

$requirements = [
    ['PHP 8.1 or newer', version_compare(PHP_VERSION, '8.1.0', '>='), false, 'running ' . PHP_VERSION],
    ['PDO MySQL driver', extension_loaded('pdo_mysql'), false, 'MySQL 8+ or MariaDB 10.6+ required'],
    ['mbstring', extension_loaded('mbstring'), false, 'for text handling'],
    ['storage/ writable', is_writable($root . '/storage') || (!is_dir($root . '/storage') && is_writable($root)), false, 'holds the install lock'],
    ['config.php writable', !is_file($root . '/config.php') || is_writable($root . '/config.php'), false, 'the installer saves your choices here'],
];
$reqOk = true;
foreach ($requirements as $r) {
    if (!$r[2] && !$r[1]) {
        $reqOk = false;
    }
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && !$installed) {
    foreach ($values as $k => $v) {
        if (isset($_POST[$k])) {
            $values[$k] = is_string($_POST[$k]) ? trim($_POST[$k]) : $v;
        }
    }
    $values['demo'] = isset($_POST['demo']) ? '1' : '';

    // --- validate database ---
    $mysql = null;
    if (!extension_loaded('pdo_mysql')) {
        $errors[] = 'The MySQL driver (pdo_mysql) is not installed on this server.';
    } elseif (!preg_match('/^[A-Za-z0-9_]+$/', $values['mysql_db'])) {
        $errors[] = 'Database name may only contain letters, numbers and underscores.';
    } else {
        try {
            $mysql = new PDO(
                'mysql:host=' . $values['mysql_host'] . ';port=' . ((int) $values['mysql_port'] ?: 3306) . ';charset=utf8mb4',
                $values['mysql_user'],
                $values['mysql_pass'],
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );
        } catch (Throwable $t) {
            $errors[] = 'Could not connect to MySQL with those details — check host, port, username and password. (' . $t->getMessage() . ')';
        }
        if (!$errors) {
            try {
                $mysql->exec('CREATE DATABASE IF NOT EXISTS `' . $values['mysql_db'] . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
            } catch (Throwable $t) {
                $errors[] = 'Connected, but your MySQL user is not allowed to create databases — create "' . $values['mysql_db'] . '" in your hosting panel, then try again.';
            }
        }
    }

    // --- validate board settings ---
    if (mb_strlen($values['board_name']) < 2 || mb_strlen($values['board_name']) > 80) {
        $errors[] = 'Board name must be 2–80 characters.';
    }
    if (mb_strlen($values['board_tagline']) > 200) {
        $errors[] = 'Tagline must be under 200 characters.';
    }
    if ($values['board_url'] !== '' && !preg_match('#^https?://[^/]+#i', $values['board_url'])) {
        $errors[] = 'Board URL must start with http:// or https:// (or leave it blank).';
    }
    if (!in_array($values['default_skin'], ['classic', 'midnight', 'silver'], true)) {
        $values['default_skin'] = 'classic';
    }

    // --- validate admin account ---
    if (!preg_match('/^[A-Za-z0-9_\- ]{3,30}$/', $values['admin_user'])) {
        $errors[] = 'Admin username must be 3–30 chars (letters, numbers, space, _ -).';
    }
    if (!filter_var($values['admin_email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Admin email address looks invalid.';
    }
    $adminPass = (string) ($_POST['admin_pass'] ?? '');
    $adminPass2 = (string) ($_POST['admin_pass2'] ?? '');
    if (strlen($adminPass) < 8) {
        $errors[] = 'Admin password must be at least 8 characters.';
    } elseif ($adminPass !== $adminPass2) {
        $errors[] = 'Admin passwords do not match.';
    }

    if (!$errors) {
        // --- write config.php (var_export keeps user input injection-safe) ---
        $cfg = [
            'mysql_host' => $values['mysql_host'],
            'mysql_port' => (int) $values['mysql_port'] ?: 3306,
            'mysql_db' => $values['mysql_db'],
            'mysql_user' => $values['mysql_user'],
            'mysql_pass' => $values['mysql_pass'],
        ];
        file_put_contents($root . '/config.php', "<?php\ndeclare(strict_types=1);\n\n// Generated by the RetroBB installer — safe to edit by hand.\nreturn " . var_export($cfg, true) . ";\n");
        Db::reset();

        // --- migrate (no demo seed; real boards start empty) ---
        // Silence the CLI progress chatter so our redirect header survives.
        $argv = ['migrate.php'];
        ob_start();
        require_once $root . '/bin/migrate.php';
        ob_end_clean();

        // --- board settings ---
        $pdo = Db::pdo();
        $settings = [
            'board_name' => mb_substr($values['board_name'], 0, 80),
            'board_tagline' => mb_substr($values['board_tagline'], 0, 200),
            'board_url' => rtrim($values['board_url'], '/'),
            'default_skin' => $values['default_skin'],
        ];
        $upsert = $pdo->prepare('INSERT INTO settings (`key`, `value`) VALUES (?, ?) ON DUPLICATE KEY UPDATE `value`=VALUES(`value`)');
        foreach ($settings as $k => $v) {
            $upsert->execute([$k, $v]);
        }

        // --- owner account ---
        require_once $root . '/models/User.php';
        $res = \RetroBB\Models\User::create($values['admin_user'], $values['admin_email'], $adminPass);
        if (!$res['ok']) {
            $errors[] = $res['errors'][0];
        } else {
            Db::pdo()->prepare("UPDATE users SET user_group='admin' WHERE id=?")->execute([$res['id']]);
            if ($values['demo'] === '1') {
                // Demo content only on truly fresh boards — never wipe real posts.
                $existing = (int) Db::pdo()->query('SELECT COUNT(*) c FROM topics')->fetch()['c'];
                if ($existing === 0) {
                    $argv = ['migrate.php', '--seed', '--force'];
                    ob_start();
                    require_once $root . '/bin/seed.php';
                    ob_end_clean();
                }
            }
            file_put_contents($lock, date('c'));
            header('Location: install.php?step=done');
            exit;
        }
    }
    $step = 'form';
}
?>
<!doctype html><html lang="en"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Install RetroBB</title>
<link rel="stylesheet" href="/assets/style-retro.css">
<style>
.steps{display:flex;gap:6px;margin:12px 0}.step{flex:1;text-align:center;font-size:12px;padding:6px;border:1px solid var(--border);border-radius:3px;background:var(--bar-bg);color:var(--muted)}.step.on{background:linear-gradient(180deg,var(--title-b),var(--title-a));color:#fff;font-weight:bold}.installbox{max-width:640px;margin:0 auto}.reqok{color:#080}.reqbad{color:#a00}
</style>
</head><body class="skin-classic theme-auto">
<div class="page-wrap"><div class="topbar"><div class="logo"><a href="/"><svg class="floppy" viewBox="0 0 32 32" aria-hidden="true" style="width:22px;height:22px;vertical-align:-4px"><rect x="3" y="4" width="26" height="25" rx="2" fill="#3A6EA5"/><rect x="9" y="4" width="14" height="9" fill="#c9d2e4"/><rect x="19" y="4" width="4" height="9" fill="#232c40"/><rect x="6" y="17" width="20" height="9" rx="1" fill="#f4f6fb"/><rect x="9" y="20" width="14" height="2" fill="#98a5b3"/></svg> RetroBB</a> <span class="tagline">installation</span></div></div>
<main><div class="installbox">
<?php if ($step === 'done'): ?>
  <div class="steps"><div class="step">1. Requirements</div><div class="step">2. Details</div><div class="step on">3. Done</div></div>
  <div class="maintitle">Welcome aboard! 🎉</div>
  <div class="flash-ok">Your board is installed. Log in with the admin account you just created.</div>
  <p><a class="btn" href="/">Visit your new board</a></p>
  <p class="muted">Housekeeping: delete <code>public/install.php</code> now so nobody can re-run setup.</p>
<?php elseif ($installed): ?>
  <div class="maintitle">Already installed</div>
  <p>This board already has an owner account. <a href="/">Visit the board</a>, or delete <code>storage/installed.lock</code> to re-run.</p>
<?php elseif ($step === 'form'): ?>
  <div class="steps"><div class="step">1. Requirements</div><div class="step on">2. Details</div><div class="step">3. Done</div></div>
  <div class="maintitle">Board details &amp; owner account</div>
  <?php foreach ($errors as $er): ?><div class="flash-error"><?= e($er) ?></div><?php endforeach; ?>
  <form method="post" class="form">
    <div class="cat-row">Database (MySQL 8+ / MariaDB)</div>
    <div class="admin-grid">
      <label>Host<br><input name="mysql_host" value="<?= e($values['mysql_host']) ?>"></label>
      <label>Port<br><input name="mysql_port" value="<?= e($values['mysql_port']) ?>" size="6"></label>
      <label>Database<br><input name="mysql_db" value="<?= e($values['mysql_db']) ?>"></label>
      <label>Username<br><input name="mysql_user" value="<?= e($values['mysql_user']) ?>"></label>
      <label>Password<br><input type="password" name="mysql_pass" value="<?= e($values['mysql_pass']) ?>"></label>
    </div>
    <small class="muted">No database yet? Just pick a name — we'll create it for you. If your host doesn't allow that, create it in your hosting panel first, then come back.</small><br><br>
    <div class="cat-row">Board</div>
    <label>Board name<br><input name="board_name" value="<?= e($values['board_name']) ?>" required style="width:100%"></label><br><br>
    <label>Tagline<br><input name="board_tagline" value="<?= e($values['board_tagline']) ?>" style="width:100%"></label><br><br>
    <label>Board URL (optional, e.g. https://forum.example.com)<br><input name="board_url" value="<?= e($values['board_url']) ?>" placeholder="https://…" style="width:100%"></label><br><br>
    <label>Default skin<br><select name="default_skin">
      <option value="classic" <?= $values['default_skin'] === 'classic' ? 'selected' : '' ?>>Classic</option>
      <option value="midnight" <?= $values['default_skin'] === 'midnight' ? 'selected' : '' ?>>Midnight</option>
      <option value="silver" <?= $values['default_skin'] === 'silver' ? 'selected' : '' ?>>Silver</option>
    </select></label><br><br>
    <div class="cat-row">Owner account</div>
    <label>Username<br><input name="admin_user" value="<?= e($values['admin_user']) ?>" required></label><br><br>
    <label>Email<br><input type="email" name="admin_email" value="<?= e($values['admin_email']) ?>" required style="width:100%"></label><br><br>
    <label>Password (8+ chars)<br><input type="password" name="admin_pass" required></label><br><br>
    <label>Repeat password<br><input type="password" name="admin_pass2" required></label><br><br>
    <label><input type="checkbox" name="demo" value="1" <?= $values['demo'] === '1' ? 'checked' : '' ?>> Install demo boards and posts so I can try things out</label><br><br>
    <button class="btn" type="submit">Install RetroBB</button>
  </form>
<?php else: ?>
  <div class="steps"><div class="step on">1. Requirements</div><div class="step">2. Details</div><div class="step">3. Done</div></div>
  <div class="maintitle">Before we begin…</div>
  <div class="recent-list">
    <?php foreach ($requirements as $r): ?>
    <div class="recent-row"><span><?= e($r[0]) ?><?php if ($r[2]): ?> <small class="muted">(optional)</small><?php endif; ?><br><small class="muted"><?= e($r[3]) ?></small></span><b class="<?= $r[1] ? 'reqok' : 'reqbad' ?>"><?= $r[1] ? '✓' : '✗' ?></b></div>
    <?php endforeach; ?>
  </div>
  <br>
  <?php if ($reqOk): ?>
    <a class="btn" href="install.php?step=form">Continue →</a>
  <?php else: ?>
    <div class="flash-error">Something above needs attention before installing. Fix it, then <a href="install.php">re-check</a>.</div>
  <?php endif; ?>
<?php endif; ?>
</div></main>
<footer class="footer"><div>RetroBB installer · <a href="/">back to board</a></div></footer>
</div></body></html>

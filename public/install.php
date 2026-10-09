<?php
declare(strict_types=1);
// RetroBB web installer wizard: requirements -> database -> board ->
// features -> owner -> done. One concern per screen; values travel in the
// session until the final step writes config.php and migrates.
$root = dirname(__DIR__);
require_once $root . '/core/Db.php';
require_once $root . '/core/helpers.php';
require_once $root . '/core/Auth.php';
require_once $root . '/core/Csrf.php';
require_once $root . '/core/Slug.php';
require_once $root . '/core/Hooks.php';
require_once $root . '/core/Auth.php';
require_once $root . '/core/BBCode.php';
require_once $root . '/core/Mentions.php';
require_once $root . '/models/Topic.php';
require_once $root . '/models/Post.php';
require_once $root . '/models/Notification.php';

use RetroBB\Core\Db;
use RetroBB\Core\Slug;

\RetroBB\Core\Auth::startSession();

$lock = $root . '/storage/installed.lock';
$force = isset($_GET['force']);
// The lock is the install marker. A populated database alone is NOT a block —
// admins legitimately install into pre-created (even used) databases — but it
// requires explicit confirmation on the owner step.
$installed = is_file($lock) && !$force;

$steps = ['database', 'board', 'features', 'owner'];
$step = isset($_GET['step']) ? (string) $_GET['step'] : 'welcome';
if (!in_array($step, ['welcome', 'database', 'board', 'features', 'owner', 'done'], true)) {
    $step = 'welcome';
}
$errors = [];
$installDisabled = false;
$showConfirm = false;

$defaults = [
    'mysql_host' => '127.0.0.1', 'mysql_port' => '3306', 'mysql_db' => 'retrobb',
    'mysql_user' => 'retrobb', 'mysql_pass' => '',
    'board_name' => 'RetroBB', 'board_tagline' => 'An old-school forum for the modern web',
    'board_url' => '', 'default_skin' => 'classic', 'default_theme' => 'auto',
    'admin_user' => '', 'admin_email' => '', 'demo' => '',
    'feature_alerts' => '1', 'feature_mentions' => '1', 'feature_reactions' => '1', 'feature_pms' => '1',
    'skin_selector' => '1', 'theme_light' => '1', 'theme_dark' => '1', 'theme_auto' => '1',
];
$values = $defaults;
foreach (($GLOBALS['_SESSION']['rb_install'] ?? []) as $k => $v) {
    if (array_key_exists($k, $values)) {
        $values[$k] = $v;
    }
}

function req_row(string $label, bool $ok, string $hint = ''): string
{
    return '<div class="recent-row"><span>' . e($label) . '<br><small class="muted">' . e($hint) . '</small></span>'
        . '<b style="color:' . ($ok ? '#080' : '#a00') . '">' . ($ok ? '✓' : '✗') . '</b></div>';
}

function step_bar(string $current): string
{
    $labels = ['database' => '1. Database', 'board' => '2. Board', 'features' => '3. Features', 'owner' => '4. Owner'];
    $order = array_keys($labels);
    $pos = array_search($current, $order, true);
    $html = '<div class="steps">';
    foreach ($labels as $key => $label) {
        $i = array_search($key, $order, true);
        $cls = ($key === $current || $current === 'done') ? 'step on' : ($pos !== false && $i < $pos ? 'step done' : 'step');
        $html .= '<div class="' . $cls . '">' . e($label) . '</div>';
    }
    return $html . '</div>';
}

/** Step order for the progress guard below. */
$step_order = ['database' => 0, 'board' => 1, 'features' => 2, 'owner' => 3];

/**
 * Furthest step the visitor may see: completed steps stay revisitable (Back
 * buttons), but nothing beyond current progress. Returns a step name.
 */
function furthest_step(): string
{
    $sess = $GLOBALS['_SESSION']['rb_install'] ?? [];
    $order = ['database', 'board', 'features', 'owner'];
    $flags = ['database' => 'db_ok', 'board' => 'board_ok', 'features' => 'features_ok'];
    $allow = 'database';
    foreach ($order as $i => $name) {
        if ($i === 0 || !empty($sess[$flags[$order[$i - 1]]])) {
            $allow = $name;
        } else {
            break;
        }
    }
    return $allow;
}

/** Try the database credentials; returns [ok, error?, pdo?]. */
function try_db(array $values): array
{
    if (!extension_loaded('pdo_mysql')) {
        return [false, 'The MySQL driver (pdo_mysql) is not installed on this server.', null];
    }
    if (!preg_match('/^[A-Za-z0-9_]+$/', $values['mysql_db'])) {
        return [false, 'Database name may only contain letters, numbers and underscores.', null];
    }
    try {
        $mysql = new PDO(
            'mysql:host=' . $values['mysql_host'] . ';port=' . ((int) $values['mysql_port'] ?: 3306) . ';charset=utf8mb4',
            $values['mysql_user'],
            $values['mysql_pass'],
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
    } catch (Throwable $t) {
        return [false, 'Could not connect to MySQL with those details — check host, port, username and password. (' . $t->getMessage() . ')', null];
    }
    // Most shared hosts (cPanel etc.) require the database to exist
    // already with the user assigned to it — only try to create it
    // as a convenience, and fall back to just using it.
    try {
        $mysql->exec('CREATE DATABASE IF NOT EXISTS `' . $values['mysql_db'] . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    } catch (Throwable $createError) {
        try {
            new PDO(
                'mysql:host=' . $values['mysql_host'] . ';port=' . ((int) $values['mysql_port'] ?: 3306) . ';dbname=' . $values['mysql_db'] . ';charset=utf8mb4',
                $values['mysql_user'],
                $values['mysql_pass'],
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );
        } catch (Throwable $t) {
            return [false, 'Could not use database "' . $values['mysql_db'] . '" — create it in your hosting panel (and add your MySQL user to it with all privileges), then try again.', null];
        }
    }
    return [true, null, $mysql];
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
    $form = (string) ($_POST['form_step'] ?? '');
    $csrfOk = \RetroBB\Core\Csrf::verify($_POST['csrf'] ?? null);
    if (!$csrfOk) {
        $errors[] = 'Session expired — please try that step again.';
        if (in_array($form, $steps, true)) {
            $step = $form;
        }
    }
    if ($form === 'database' && $csrfOk) {
        foreach (['mysql_host', 'mysql_port', 'mysql_db', 'mysql_user', 'mysql_pass'] as $k) {
            if (isset($_POST[$k])) {
                $values[$k] = is_string($_POST[$k]) ? trim($_POST[$k]) : $values[$k];
            }
        }
        [$ok, $err] = try_db($values);
        if (!$ok) {
            $errors[] = $err;
            $step = 'database';
        } else {
            $_SESSION['rb_install'] = array_merge($_SESSION['rb_install'] ?? [], [
                'mysql_host' => $values['mysql_host'], 'mysql_port' => $values['mysql_port'],
                'mysql_db' => $values['mysql_db'], 'mysql_user' => $values['mysql_user'],
                'mysql_pass' => $values['mysql_pass'], 'db_ok' => '1',
            ]);
            redirect('install.php?step=board' . ($force ? '&force=1' : ''));
        }
    } elseif ($form === 'board' && $csrfOk) {
        foreach (['board_name', 'board_tagline', 'board_url', 'default_skin', 'default_theme'] as $k) {
            if (isset($_POST[$k])) {
                $values[$k] = is_string($_POST[$k]) ? trim($_POST[$k]) : $values[$k];
            }
        }
        foreach (['skin_selector', 'theme_light', 'theme_dark', 'theme_auto'] as $k) {
            $values[$k] = isset($_POST[$k]) ? '1' : '';
        }
        if (!in_array($values['default_skin'], ['classic', 'midnight', 'silver'], true)) {
            $values['default_skin'] = 'classic';
        }
        if (!in_array($values['default_theme'], ['light', 'dark', 'auto'], true)) {
            $values['default_theme'] = 'auto';
        }
        if (mb_strlen($values['board_name']) < 2 || mb_strlen($values['board_name']) > 80) {
            $errors[] = 'Board name must be 2–80 characters.';
        }
        if (mb_strlen($values['board_tagline']) > 200) {
            $errors[] = 'Tagline must be under 200 characters.';
        }
        if ($values['board_url'] !== '' && !preg_match('#^https?://[^/]+#i', $values['board_url'])) {
            $errors[] = 'Board URL must start with http:// or https:// (or leave it blank).';
        }
        // At least one theme mode must stay on, and the default must be one of them.
        if ($values['theme_light'] === '' && $values['theme_dark'] === '' && $values['theme_auto'] === '') {
            $values['theme_auto'] = '1';
        }
        $enabled = [];
        foreach (['light', 'dark', 'auto'] as $t) {
            if ($values['theme_' . $t] === '1') {
                $enabled[] = $t;
            }
        }
        if (!in_array($values['default_theme'], $enabled, true)) {
            foreach (['auto', 'light', 'dark'] as $t) {
                if (in_array($t, $enabled, true)) {
                    $values['default_theme'] = $t;
                    break;
                }
            }
        }
        if (!$errors) {
            $_SESSION['rb_install'] = array_merge($_SESSION['rb_install'] ?? [], [
                'board_name' => $values['board_name'], 'board_tagline' => $values['board_tagline'],
                'board_url' => $values['board_url'], 'default_skin' => $values['default_skin'],
                'default_theme' => $values['default_theme'], 'skin_selector' => $values['skin_selector'],
                'theme_light' => $values['theme_light'], 'theme_dark' => $values['theme_dark'],
                'theme_auto' => $values['theme_auto'], 'board_ok' => '1',
            ]);
            redirect('install.php?step=features' . ($force ? '&force=1' : ''));
        }
        $step = 'board';
    } elseif ($form === 'features' && $csrfOk) {
        foreach (['feature_alerts', 'feature_mentions', 'feature_reactions', 'feature_pms'] as $k) {
            $values[$k] = isset($_POST[$k]) ? '1' : '';
        }
        $_SESSION['rb_install'] = array_merge($_SESSION['rb_install'] ?? [], [
            'feature_alerts' => $values['feature_alerts'], 'feature_mentions' => $values['feature_mentions'],
            'feature_reactions' => $values['feature_reactions'], 'feature_pms' => $values['feature_pms'],
            'features_ok' => '1',
        ]);
        redirect('install.php?step=owner' . ($force ? '&force=1' : ''));
    } elseif ($form === 'owner' && $csrfOk) {
        foreach (['admin_user', 'admin_email'] as $k) {
            if (isset($_POST[$k])) {
                $values[$k] = is_string($_POST[$k]) ? trim($_POST[$k]) : $values[$k];
            }
        }
        $values['demo'] = isset($_POST['demo']) ? '1' : '';
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
        // Existing content? Only proceed with explicit consent — installing
        // keeps every post and user and just adds the new owner account.
        // (Checked against the form's database, not the current config.)
        $mysql = null;
        if (!$errors) {
            [$ok, $err, $mysql] = try_db($values);
            if (!$ok) {
                $errors[] = $err;
            }
        }
        if (!$errors) {
            $dbStats = ['users' => 0, 'topics' => 0];
            try {
                $db = '`' . $values['mysql_db'] . '`';
                $dbStats['users'] = (int) $mysql->query("SELECT COUNT(*) c FROM $db.users")->fetch()['c'];
                $dbStats['topics'] = (int) $mysql->query("SELECT COUNT(*) c FROM $db.topics")->fetch()['c'];
            } catch (Throwable) {
            }
            if (($dbStats['users'] > 0 || $dbStats['topics'] > 0) && empty($_POST['confirm_existing'])) {
                $errors[] = 'This database already holds a board (' . $dbStats['users'] . ' users, ' . $dbStats['topics'] . ' topics).'
                    . ' Installing will keep all of it and add your account as an additional admin.'
                    . ' Tick the confirmation box below if that is what you want.';
                $showConfirm = true;
            }
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

            // --- migrate (silenced so our redirect header survives) ---
            $argv = ['migrate.php'];
            ob_start();
            require_once $root . '/bin/migrate.php';
            ob_end_clean();

            // --- board + feature settings ---
            $pdo = Db::pdo();
            $settings = [
                'board_name' => mb_substr($values['board_name'], 0, 80),
                'board_tagline' => mb_substr($values['board_tagline'], 0, 200),
                'board_url' => rtrim($values['board_url'], '/'),
                'default_skin' => $values['default_skin'],
                'default_theme' => $values['default_theme'],
                'feature_alerts' => $values['feature_alerts'] === '1' ? '1' : '0',
                'feature_mentions' => $values['feature_mentions'] === '1' ? '1' : '0',
                'feature_reactions' => $values['feature_reactions'] === '1' ? '1' : '0',
                'feature_pms' => $values['feature_pms'] === '1' ? '1' : '0',
                'skin_selector' => $values['skin_selector'] === '1' ? '1' : '0',
                'theme_light' => $values['theme_light'] === '1' ? '1' : '0',
                'theme_dark' => $values['theme_dark'] === '1' ? '1' : '0',
                'theme_auto' => $values['theme_auto'] === '1' ? '1' : '0',
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
                // Every fresh board starts with one placeholder category +
                // forum and a welcome thread (skipped for used databases).
                try {
                    $catCount = (int) Db::pdo()->query('SELECT COUNT(*) c FROM categories')->fetch()['c'];
                } catch (Throwable) {
                    $catCount = 1;
                }
                if ($catCount === 0) {
                    Db::pdo()->prepare('INSERT INTO categories (title, sort) VALUES (?,0)')->execute(['Welcome']);
                    $welcomeCat = (int) Db::pdo()->lastInsertId();
                    Db::pdo()->prepare('INSERT INTO forums (category_id, name, slug, description, sort) VALUES (?,?,?,?,0)')
                        ->execute([$welcomeCat, 'General', Slug::make('General'), 'Say hello and read the ground rules.']);
                    $welcomeForum = (int) Db::pdo()->lastInsertId();
                    \RetroBB\Models\Topic::create(
                        $welcomeForum,
                        (int) $res['id'],
                        'Welcome to ' . mb_substr($values['board_name'], 0, 80) . '!',
                        "Welcome! This is the first thread on your new board.\n\n[b]First steps:[/b]\n[list]\n[*] Make your boards in AdminCP → Structure\n[*] Pick the look in AdminCP → Features\n[*] Turn on spam protection before you go public\n[/list]\n\nReply below to test things out. Have fun!"
                    );
                }
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
                unset($_SESSION['rb_install']);
                file_put_contents($lock, date('c'));
                // Neutralize this very file so setup can't be re-run from the web:
                // renaming beats deleting (atomic, visible, reversible). If the
                // rename fails (permissions), the done page says so loudly.
                $disabled = @rename(__FILE__, $root . '/public/install.disabled.php');
                $step = 'done';
                $installDisabled = $disabled;
            }
        }
        if ($step !== 'done') {
            $step = 'owner';
        }
    }
}

// Progress guard: completed steps stay revisitable (Back buttons), but
// nothing beyond current progress.
if (!$installed && isset($step_order[$step]) && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
    $allow = furthest_step();
    if ($step_order[$step] > $step_order[$allow]) {
        $step = $allow;
    }
}
?>
<!doctype html><html lang="en"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Install RetroBB</title>
<link rel="stylesheet" href="/assets/style-retro.css">
<style>
.steps{display:flex;gap:6px;margin:12px 0}.step{flex:1;text-align:center;font-size:12px;padding:6px;border:1px solid var(--border);border-radius:3px;background:var(--bar-bg);color:var(--muted)}.step.on{background:linear-gradient(180deg,var(--title-b),var(--title-a));color:#fff;font-weight:bold}.step.done{background:var(--row2);color:var(--muted)}.installbox{max-width:640px;margin:0 auto}.reqok{color:#080}.reqbad{color:#a00}
.swatches{display:flex;gap:8px;margin:8px 0 12px}
</style>
</head><body class="skin-classic theme-auto">
<div class="page-wrap"><div class="topbar"><div class="logo"><a href="/"><svg class="floppy" viewBox="0 0 32 32" aria-hidden="true" style="width:22px;height:22px;vertical-align:-4px"><rect x="3" y="4" width="26" height="25" rx="2" fill="#3A6EA5"/><rect x="9" y="4" width="14" height="9" fill="#c9d2e4"/><rect x="19" y="4" width="4" height="9" fill="#232c40"/><rect x="6" y="17" width="20" height="9" rx="1" fill="#f4f6fb"/><rect x="9" y="20" width="14" height="2" fill="#98a5b3"/></svg> RetroBB</a> <span class="tagline">installation</span></div></div>
<main><div class="installbox">
<?php if ($step === 'done'): ?>
  <?= step_bar('done') ?>
  <div class="maintitle">Welcome aboard! 🎉</div>
  <div class="flash-ok">Your board is installed. Log in with the admin account you just created.</div>
  <p><a class="btn" href="/">Visit your new board</a></p>
  <?php if (!empty($installDisabled)): ?>
  <p class="muted">Housekeeping handled: this setup file has disabled itself (<code>install.disabled.php</code>) so it can't be re-run.</p>
  <?php else: ?>
  <p class="muted">One last thing: delete <code>public/install.php</code> — setup couldn't remove itself (file permissions), so please do it by hand.</p>
  <?php endif; ?>
<?php elseif ($installed): ?>
  <div class="maintitle">Already installed</div>
  <p>This board already has an owner account. <a href="/">Visit the board</a>.</p>
<?php elseif ($step === 'welcome'): ?>
  <div class="maintitle">Before we begin…</div>
  <div class="recent-list">
    <?php foreach ($requirements as $r): ?>
    <div class="recent-row"><span><?= e($r[0]) ?><?php if ($r[2]): ?> <small class="muted">(optional)</small><?php endif; ?><br><small class="muted"><?= e($r[3]) ?></small></span><b class="<?= $r[1] ? 'reqok' : 'reqbad' ?>"><?= $r[1] ? '✓' : '✗' ?></b></div>
    <?php endforeach; ?>
  </div>
  <br>
  <?php if ($reqOk): ?>
    <a class="btn" href="install.php?step=database<?= $force ? '&force=1' : '' ?>">Continue →</a>
  <?php else: ?>
    <div class="flash-error">Something above needs attention before installing. Fix it, then <a href="install.php">re-check</a>.</div>
  <?php endif; ?>
<?php elseif ($step === 'database'): ?>
  <?= step_bar('database') ?>
  <div class="maintitle">Database</div>
  <?php foreach ($errors as $er): ?><div class="flash-error"><?= e($er) ?></div><?php endforeach; ?>
  <form method="post" class="form">
    <?= \RetroBB\Core\Csrf::field() ?>
    <input type="hidden" name="form_step" value="database">
    <div class="admin-grid">
      <label>Host<br><input name="mysql_host" value="<?= e($values['mysql_host']) ?>"></label>
      <label>Port<br><input name="mysql_port" value="<?= e($values['mysql_port']) ?>" size="6"></label>
      <label>Database<br><input name="mysql_db" value="<?= e($values['mysql_db']) ?>"></label>
      <label>Username<br><input name="mysql_user" value="<?= e($values['mysql_user']) ?>"></label>
      <label>Password<br><input type="password" name="mysql_pass" value="<?= e($values['mysql_pass']) ?>"></label>
    </div>
    <br><small class="muted">On shared hosting (cPanel etc.), create the database first under MySQL Databases and add your user to it with all privileges — then enter it here. If your user is allowed to, we'll create it for you instead.</small><br><br>
    <button class="btn" type="submit">Continue →</button>
  </form>
<?php elseif ($step === 'board'): ?>
  <?= step_bar('board') ?>
  <div class="maintitle">Board &amp; appearance</div>
  <?php foreach ($errors as $er): ?><div class="flash-error"><?= e($er) ?></div><?php endforeach; ?>
  <form method="post" class="form">
    <?= \RetroBB\Core\Csrf::field() ?>
    <input type="hidden" name="form_step" value="board">
    <label>Board name<br><input name="board_name" value="<?= e($values['board_name']) ?>" required style="width:100%"></label><br><br>
    <label>Tagline<br><input name="board_tagline" value="<?= e($values['board_tagline']) ?>" style="width:100%"></label><br><br>
    <label>Board URL (optional, e.g. https://forum.example.com)<br><input name="board_url" value="<?= e($values['board_url']) ?>" placeholder="https://…" style="width:100%"></label><br><br>
    <label>Default skin<br><select name="default_skin">
      <option value="classic" <?= $values['default_skin'] === 'classic' ? 'selected' : '' ?>>Classic</option>
      <option value="midnight" <?= $values['default_skin'] === 'midnight' ? 'selected' : '' ?>>Midnight</option>
      <option value="silver" <?= $values['default_skin'] === 'silver' ? 'selected' : '' ?>>Silver</option>
    </select></label><br>
    <div class="swatches">
      <div style="flex:1;border:1px solid #888;border-radius:4px;overflow:hidden"><div style="background:linear-gradient(180deg,#5b8fd0,#3A6EA5);color:#fff;font-size:11px;padding:4px 6px"><b>Classic</b></div><div style="background:#f4f6fb;color:#222;font-size:11px;padding:6px">blue title bars · light body</div></div>
      <div style="flex:1;border:1px solid #888;border-radius:4px;overflow:hidden"><div style="background:linear-gradient(180deg,#3a3f4d,#23262e);color:#fff;font-size:11px;padding:4px 6px"><b>Midnight</b></div><div style="background:#1c1e24;color:#ddd;font-size:11px;padding:6px">dark chrome · dark body</div></div>
      <div style="flex:1;border:1px solid #888;border-radius:4px;overflow:hidden"><div style="background:linear-gradient(180deg,#e8e8e8,#b9b9b9);color:#222;font-size:11px;padding:4px 6px"><b>Silver</b></div><div style="background:#f0f0f0;color:#222;font-size:11px;padding:6px">grey chrome · light body</div></div>
    </div>
    <label>Default theme<br><select name="default_theme">
      <option value="auto" <?= $values['default_theme'] === 'auto' ? 'selected' : '' ?>>Auto (follows device)</option>
      <option value="light" <?= $values['default_theme'] === 'light' ? 'selected' : '' ?>>Light</option>
      <option value="dark" <?= $values['default_theme'] === 'dark' ? 'selected' : '' ?>>Dark</option>
    </select></label><br><br>
    <label><input type="checkbox" name="skin_selector" value="1" <?= $values['skin_selector'] === '1' ? 'checked' : '' ?>> Let members switch skins</label><br>
    <label>Theme modes members may pick:</label>
    <label><input type="checkbox" name="theme_light" value="1" <?= $values['theme_light'] === '1' ? 'checked' : '' ?>> Light</label>
    <label><input type="checkbox" name="theme_dark" value="1" <?= $values['theme_dark'] === '1' ? 'checked' : '' ?>> Dark</label>
    <label><input type="checkbox" name="theme_auto" value="1" <?= $values['theme_auto'] === '1' ? 'checked' : '' ?>> Auto</label><br><br>
    <a class="smallbtn" href="install.php?step=database<?= $force ? '&force=1' : '' ?>">← Back</a>
    <button class="btn" type="submit">Continue →</button>
  </form>
<?php elseif ($step === 'features'): ?>
  <?= step_bar('features') ?>
  <div class="maintitle">Features</div>
  <p class="muted">Everything is on — uncheck anything you don't want. Core posting, registration and moderation stay on: they would break the board. Anything here can be re-enabled later in AdminCP → Features. Note: turning off Alerts also stops mention/reply/reaction notifications.</p>
  <form method="post" class="form">
    <?= \RetroBB\Core\Csrf::field() ?>
    <input type="hidden" name="form_step" value="features">
    <label><input type="checkbox" name="feature_alerts" value="1" <?= $values['feature_alerts'] === '1' ? 'checked' : '' ?>> Alerts (mention / reply / reaction notifications)</label><br>
    <label><input type="checkbox" name="feature_mentions" value="1" <?= $values['feature_mentions'] === '1' ? 'checked' : '' ?>> @mentions (link @usernames to profiles)</label><br>
    <label><input type="checkbox" name="feature_reactions" value="1" <?= $values['feature_reactions'] === '1' ? 'checked' : '' ?>> Reactions (👍 🙏 😄 on posts)</label><br>
    <label><input type="checkbox" name="feature_pms" value="1" <?= $values['feature_pms'] === '1' ? 'checked' : '' ?>> Private messages</label><br><br>
    <a class="smallbtn" href="install.php?step=board<?= $force ? '&force=1' : '' ?>">← Back</a>
    <button class="btn" type="submit">Continue →</button>
  </form>
<?php elseif ($step === 'owner'): ?>
  <?= step_bar('owner') ?>
  <div class="maintitle">Owner account</div>
  <?php foreach ($errors as $er): ?><div class="flash-error"><?= e($er) ?></div><?php endforeach; ?>
  <form method="post" class="form">
    <?= \RetroBB\Core\Csrf::field() ?>
    <input type="hidden" name="form_step" value="owner">
    <label>Username<br><input name="admin_user" value="<?= e($values['admin_user']) ?>" required></label><br><br>
    <label>Email<br><input type="email" name="admin_email" value="<?= e($values['admin_email']) ?>" required style="width:100%"></label><br><br>
    <label>Password (8+ chars)<br><input type="password" name="admin_pass" required></label><br><br>
    <label>Repeat password<br><input type="password" name="admin_pass2" required></label><br><br>
    <label><input type="checkbox" name="demo" value="1" <?= $values['demo'] === '1' ? 'checked' : '' ?>> Install demo boards and posts so I can try things out</label><br><br>
    <?php if (!empty($showConfirm)): ?>
    <div class="flash-error" style="margin-bottom:10px"><label><input type="checkbox" name="confirm_existing" value="1"> Yes, install into the existing database and keep its content</label></div>
    <?php endif; ?>
    <br><a class="smallbtn" href="install.php?step=features<?= $force ? '&force=1' : '' ?>">← Back</a>
    <button class="btn" type="submit">Install RetroBB</button>
  </form>
<?php endif; ?>
</div></main>
<footer class="footer"><div>RetroBB installer · <a href="/">back to board</a></div></footer>
</div></body></html>

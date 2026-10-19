<?php /** @var string $stage */ /** @var string|null $error */ ?>
<?php
// The chunked importer advances by POST, not a URL (issue #2): step/offset/t
// stay in the body so the session token never lands in Referer or logs.
$nextStep = is_array($next ?? null) ? (string) ($next['step'] ?? 'users') : 'users';
$nextOffset = is_array($next ?? null) ? (int) ($next['offset'] ?? 0) : 0;
$importToken = (string) ($token ?? ($_SESSION['import_token'] ?? ''));
?>
<div class="maintitle">AdminCP — Import</div>
<?php \RetroBB\Core\View::partial('admin/_nav', ['active' => 'import']); ?>
<?php if (!empty($error)): ?><div class="flash-error"><?= e($error) ?></div><?php endif; ?>
<?php if ($stage === 'form'): ?>
<p class="muted">Bring over users (passwords keep working), categories, forums, topics and posts from phpBB 3.x or SMF 2.x. Your existing board stays put — imports only add. Skipped: polls, attachments, PMs, bans, avatars, permissions. Don't run twice: re-running imports everything again.</p>
<form method="post" action="<?= e(url('/admin/import/test')) ?>" class="form" style="max-width:560px">
<?= \RetroBB\Core\Csrf::field() ?>
<label>Source board<br><select name="source"><option value="phpbb">phpBB 3.x</option><option value="smf">SMF 2.x</option></select></label><br><br>
<div class="cat-row">Source database (read-only access is enough)</div>
<div class="admin-grid">
  <label>Host<br><input name="host" value="127.0.0.1"></label>
  <label>Port<br><input name="port" value="3306" size="6"></label>
  <label>Database<br><input name="db" required></label>
  <label>Username<br><input name="user" required></label>
  <label>Password<br><input type="password" name="pass"></label>
  <label>Table prefix<br><input name="prefix" value="phpbb_" placeholder="phpbb_"></label>
</div>
<br><small class="muted">The password is used for this import only — it is kept in your session while the import runs and never saved in settings.</small><br><br>
<button class="btn" type="submit">Check &amp; count →</button>
</form>
<?php elseif ($stage === 'counts'): ?>
<?php /** @var array $counts */ /** @var string $source */ ?>
<div class="recent-list">
  <div class="recent-row"><span>Users</span><b><?= (int) $counts['users'] ?></b></div>
  <div class="recent-row"><span>Categories + forums</span><b><?= (int) $counts['forums'] ?></b></div>
  <div class="recent-row"><span>Topics</span><b><?= (int) $counts['topics'] ?></b></div>
  <div class="recent-row"><span>Posts</span><b><?= (int) $counts['posts'] ?></b></div>
</div>
<form method="post" action="<?= e(url('/admin/import/run')) ?>" class="imp-next">
<?= \RetroBB\Core\Csrf::field() ?>
<input type="hidden" name="step" value="<?= e($nextStep) ?>">
<input type="hidden" name="offset" value="<?= $nextOffset ?>">
<input type="hidden" name="t" value="<?= e($importToken) ?>">
<button class="btn" type="submit">Start <?= $source === 'smf' ? 'SMF' : 'phpBB' ?> import →</button>
</form>
<?php elseif ($stage === 'run'): ?>
<?php /** @var string $step */ /** @var array $tally */ ?>
<div class="maintitle">Importing: <?= e($step) ?>…</div>
<div class="recent-list">
  <div class="recent-row"><span>Users</span><b><?= (int) ($tally['users'] ?? 0) ?></b></div>
  <div class="recent-row"><span>Forums</span><b><?= (int) ($tally['forums'] ?? 0) ?></b></div>
  <div class="recent-row"><span>Topics</span><b><?= (int) ($tally['topics'] ?? 0) ?></b></div>
  <div class="recent-row"><span>Posts</span><b><?= (int) ($tally['posts'] ?? 0) ?></b></div>
  <div class="recent-row"><span>Skipped</span><b><?= (int) ($tally['skipped'] ?? 0) ?></b></div>
</div>
<form method="post" action="<?= e(url('/admin/import/run')) ?>" id="imp-next" class="imp-next">
<?= \RetroBB\Core\Csrf::field() ?>
<input type="hidden" name="step" value="<?= e($nextStep) ?>">
<input type="hidden" name="offset" value="<?= $nextOffset ?>">
<input type="hidden" name="t" value="<?= e($importToken) ?>">
<button class="btn" type="submit">Continue →</button>
</form>
<script>setTimeout(function () { var f = document.getElementById('imp-next'); if (f) { f.submit(); } }, 800);</script>
<?php elseif ($stage === 'done'): ?>
<?php /** @var array $tally */ /** @var string $source */ ?>
<div class="flash-ok">Import finished.</div>
<div class="recent-list">
  <div class="recent-row"><span>Users</span><b><?= (int) ($tally['users'] ?? 0) ?></b></div>
  <div class="recent-row"><span>Forums</span><b><?= (int) ($tally['forums'] ?? 0) ?></b></div>
  <div class="recent-row"><span>Topics</span><b><?= (int) ($tally['topics'] ?? 0) ?></b></div>
  <div class="recent-row"><span>Posts</span><b><?= (int) ($tally['posts'] ?? 0) ?></b></div>
  <div class="recent-row"><span>Skipped</span><b><?= (int) ($tally['skipped'] ?? 0) ?></b></div>
</div>
<p class="muted">Imported members log in with their old <?= $source === 'smf' ? 'SMF' : 'phpBB' ?> passwords (upgraded silently on first login). Guest posts belong to the Guest account.</p>
<p><a class="btn" href="<?= e(url('/')) ?>">Visit the board</a></p>
<?php endif; ?>

<?php /** @var array $stats */ /** @var int $openReports */ /** @var int $activeBans */ ?>
<div class="maintitle">AdminCP — Dashboard</div>
<?php \RetroBB\Core\View::partial('admin/_nav', ['active' => 'dashboard']); ?>
<?php if ($openReports > 0): ?><div class="flash-error"><a href="<?= e(url('/mod/reports')) ?>"><b><?= $openReports ?> open report<?= $openReports === 1 ? '' : 's' ?></b> in the mod queue</a></div><?php endif; ?>
<div class="dash-grid">
  <a class="dash-card" href="<?= e(url('/admin/settings')) ?>"><b>⚙ Settings</b><small>Board name, tagline, flood, edit window</small></a>
  <a class="dash-card" href="<?= e(url('/admin/features')) ?>"><b>✨ Features</b><small>Alerts, PMs, reactions, mentions, skins &amp; themes</small></a>
  <a class="dash-card" href="<?= e(url('/admin/spam')) ?>"><b>🛡 Spam protection</b><small>Honeypot + CAPTCHA providers</small></a>
  <a class="dash-card" href="<?= e(url('/admin/structure')) ?>"><b>🗂 Structure</b><small>Categories &amp; forums, ordering</small></a>
  <a class="dash-card" href="<?= e(url('/admin/bans')) ?>"><b>⛔ Bans<?= $activeBans > 0 ? ' (' . $activeBans . ' active)' : '' ?></b><small>Ban list &amp; unban</small></a>
  <a class="dash-card" href="<?= e(url('/admin/users')) ?>"><b>👥 Users (<?= (int) $stats['users'] ?>)</b><small>Groups &amp; roles</small></a>
  <a class="dash-card" href="<?= e(url('/admin/modlog')) ?>"><b>📜 Mod log</b><small>Every moderation action</small></a>
  <a class="dash-card" href="<?= e(url('/admin/import')) ?>"><b>📥 Import</b><small>From phpBB / SMF</small></a>
</div>
<div class="statsbar"><b><?= (int) $stats['topics'] ?></b> topics · <b><?= (int) $stats['posts'] ?></b> posts · <b><?= (int) $stats['users'] ?></b> members · newest: <b><?= e($stats['newest']) ?></b></div>
<?php if (isset($_GET['migrated'])): ?><div class="flash-ok">Database updates ran clean.</div><?php endif; ?>
<div class="cat-row">Database</div>
<p class="muted">After uploading a new release, run the database updates here (same as <code>php bin/migrate.php</code>). Safe to run any time — updates only ever add.</p>
<form method="post" action="<?= e(url('/admin/migrate')) ?>"><?= \RetroBB\Core\Csrf::field() ?><button class="btn" type="submit">Run database updates</button></form>

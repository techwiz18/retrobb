<?php /** @var array $stats */ /** @var int $openReports */ /** @var int $activeBans */ /** @var bool $installPresent */ /** @var array $update */ /** @var bool $canonicalUnset */ ?>
<div class="maintitle">AdminCP — Dashboard</div>
<?php \RetroBB\Core\View::partial('admin/_nav', ['active' => 'dashboard']); ?>
<?php [$latest, $upToDate] = $update + [null, null]; ?>
<div class="statsbar">Running <b>RetroBB <?= e(RETROBB_VERSION) ?></b><?php if ($latest !== null): ?> · latest release: <b><?= e($latest) ?></b><?php if ($upToDate === true): ?> (up to date)<?php elseif ($upToDate === false): ?> — <a href="https://github.com/techwiz18/retrobb/releases"><b>update available</b></a><?php endif; ?><?php else: ?> · <span class="muted">could not check for updates</span><?php endif; ?></div>
<?php if (!empty($installPresent)): ?><div class="flash-error"><b>Security:</b> <code>public/install.php</code> is still on the server — anyone reaching it can probe your install. Delete it via your hosting panel. A future reinstall also needs <code>storage/installed.lock</code> removed.</div><?php endif; ?>
<?php if (!empty($canonicalUnset)): ?><div class="flash-error"><b>Action needed:</b> no <a href="<?= e(url('/admin/settings')) ?>">Board URL</a> is set. Canonical tags fall back to relative URLs, the sitemap omits its links, and <b>password-reset email is disabled</b> (it will not send a link built from a request's Host). Set the board's real address to restore them.</div><?php endif; ?>
<?php if ($openReports > 0): ?><div class="flash-error"><a href="<?= e(url('/mod/reports')) ?>"><b><?= $openReports ?> open report<?= $openReports === 1 ? '' : 's' ?></b> in the mod queue</a></div><?php endif; ?>
<div class="dash-grid">
  <?php $dashSuper = \RetroBB\Core\Auth::isSuper(); ?>
  <?php if ($dashSuper): ?><a class="dash-card" href="<?= e(url('/admin/settings')) ?>"><b>⚙ Settings</b><small>Board name, tagline, flood, edit window</small></a>
  <a class="dash-card" href="<?= e(url('/admin/features')) ?>"><b>✨ Features</b><small>Alerts, PMs, reactions, mentions, skins &amp; themes</small></a><?php endif; ?>
  <a class="dash-card" href="<?= e(url('/admin/spam')) ?>"><b>🛡 Spam protection</b><small>Honeypot + CAPTCHA providers</small></a>
  <a class="dash-card" href="<?= e(url('/admin/structure')) ?>"><b>🗂 Structure</b><small>Categories &amp; forums, ordering</small></a>
  <a class="dash-card" href="<?= e(url('/admin/bans')) ?>"><b>⛔ Bans<?= $activeBans > 0 ? ' (' . $activeBans . ' active)' : '' ?></b><small>Ban list &amp; unban</small></a>
  <a class="dash-card" href="<?= e(url('/admin/users')) ?>"><b>👥 Users (<?= (int) $stats['users'] ?>)</b><small>Groups &amp; roles</small></a>
  <a class="dash-card" href="<?= e(url('/admin/modlog')) ?>"><b>📜 Mod log</b><small>Every moderation action</small></a>
  <?php if ($dashSuper): ?><a class="dash-card" href="<?= e(url('/admin/import')) ?>"><b>📥 Import</b><small>From phpBB / SMF</small></a><?php endif; ?>
  <?php if ($dashSuper): ?><a class="dash-card" href="<?= e(url('/admin/plugins')) ?>"><b>🧩 Plugins</b><small>Enable / disable extensions</small></a><?php endif; ?>
</div>
<div class="statsbar"><b><?= (int) $stats['topics'] ?></b> topics · <b><?= (int) $stats['posts'] ?></b> posts · <b><?= (int) $stats['users'] ?></b> members · newest: <b><?= e($stats['newest']) ?></b></div>
<?php if (isset($_GET['migrated'])): ?><div class="flash-ok">Database updates ran clean.</div><?php endif; ?>
<?php if (\RetroBB\Core\Auth::isSuper()): ?><div class="cat-row">Database</div>
<p class="muted">After uploading a new release, run the database updates here (same as <code>php bin/migrate.php</code>). Safe to run any time — updates only ever add.</p>
<form method="post" action="<?= e(url('/admin/migrate')) ?>"><?= \RetroBB\Core\Csrf::field() ?><button class="btn" type="submit">Run database updates</button></form>
<div class="cat-row">Maintenance</div>
<p class="muted">Re-render stored post and PM HTML from its BBCode. Run once after upgrading a board that predates the link-sanitising fix; only affected rows change, and it is safe to repeat.</p>
<form method="post" action="<?= e(url('/admin/repair-html')) ?>"><?= \RetroBB\Core\Csrf::field() ?><button class="btn" type="submit">Repair rendered posts</button></form><?php endif; ?>

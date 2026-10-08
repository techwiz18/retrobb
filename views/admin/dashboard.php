<?php /** @var array $stats */ /** @var int $openReports */ /** @var int $activeBans */ ?>
<div class="maintitle">AdminCP — Dashboard</div>
<?php \RetroBB\Core\View::partial('admin/_nav', ['active' => 'dashboard']); ?>
<?php if ($openReports > 0): ?><div class="flash-error"><a href="/mod/reports"><b><?= $openReports ?> open report<?= $openReports === 1 ? '' : 's' ?></b> in the mod queue</a></div><?php endif; ?>
<div class="dash-grid">
  <a class="dash-card" href="/admin/settings"><b>⚙ Settings</b><small>Board name, tagline, skin, flood, edit window</small></a>
  <a class="dash-card" href="/admin/spam"><b>🛡 Spam protection</b><small>Honeypot + CAPTCHA providers</small></a>
  <a class="dash-card" href="/admin/structure"><b>🗂 Structure</b><small>Categories &amp; forums, ordering</small></a>
  <a class="dash-card" href="/admin/bans"><b>⛔ Bans<?= $activeBans > 0 ? ' (' . $activeBans . ' active)' : '' ?></b><small>Ban list &amp; unban</small></a>
  <a class="dash-card" href="/admin/users"><b>👥 Users (<?= (int) $stats['users'] ?>)</b><small>Groups &amp; roles</small></a>
  <a class="dash-card" href="/admin/modlog"><b>📜 Mod log</b><small>Every moderation action</small></a>
</div>
<div class="statsbar"><b><?= (int) $stats['topics'] ?></b> topics · <b><?= (int) $stats['posts'] ?></b> posts · <b><?= (int) $stats['users'] ?></b> members · newest: <b><?= e($stats['newest']) ?></b></div>

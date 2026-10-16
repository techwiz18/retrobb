<?php /** @var int $openReports */ /** @var int $activeBans */ ?>
<div class="breadcrumb"><a href="<?= e(url('/')) ?>">Index</a> &raquo; ModCP</div>
<div class="maintitle">ModCP — Dashboard</div>
<?php \RetroBB\Core\View::partial('mod/_nav', ['active' => 'dashboard']); ?>
<div class="dash-grid">
  <a class="dash-card" href="<?= e(url('/mod/reports')) ?>"><b>📥 Mod queue<?= $openReports > 0 ? ' (' . $openReports . ' open)' : '' ?></b><small>Member reports to review</small></a>
  <a class="dash-card" href="<?= e(url('/mod/bans')) ?>"><b>⛔ Bans<?= $activeBans > 0 ? ' (' . $activeBans . ' active)' : '' ?></b><small>Ban history &amp; unban</small></a>
</div>

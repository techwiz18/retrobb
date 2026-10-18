<?php /** @var array $bans */ /** @var int $page */ /** @var int $pages */ ?>
<div class="breadcrumb"><a href="<?= e(url('/')) ?>">Index</a> &raquo; <a href="<?= e(url('/mod/reports')) ?>">Mod queue</a> &raquo; Bans</div>
<div class="maintitle">ModCP — Bans</div>
<?php \RetroBB\Core\View::partial('mod/_nav', ['active' => 'bans']); ?>
<div class="recent-list">
<?php $nowTs = time(); ?>
<?php foreach ($bans as $b): ?>
<?php
$lifted = !empty($b['lifted_at']);
$expired = !$lifted && !empty($b['expires_at']) && strtotime($b['expires_at']) <= $nowTs;
$active = !$lifted && !$expired;
$span = '';
if (!empty($b['expires_at']) && !empty($b['created_at'])) {
    $days = (int) floor((strtotime($b['expires_at']) - strtotime($b['created_at'])) / 86400);
    $span = $days . 'd ban from ' . $b['created_at'];
}
?>
<div class="recent-row" style="align-items:flex-start"><span><b><?= e($b['username']) ?></b>
<?php if ($active): ?><b style="color:#a00">[ACTIVE]</b>
<?php elseif ($expired): ?><span class="muted">[expired — <?= e($span) ?>]</span>
<?php else: ?><span class="muted">[ended]</span><?php endif; ?><br>
<small class="muted"><?= e($b['reason']) ?> · by <?= e($b['banned_by_name']) ?> · <?= $b['expires_at'] ? 'until ' . e($b['expires_at']) : 'permanent' ?></small></span>
<span><?php if ($active): ?><form method="post" action="<?= e(url('/members/unban/' . (int) $b['id'])) ?>" style="white-space:nowrap"><?= \RetroBB\Core\Csrf::field() ?><input name="reason" maxlength="200" placeholder="Unban note (optional)" style="width:180px"> <button class="smallbtn">Unban</button></form><?php endif; ?></span></div>
<?php endforeach; ?>
<?php if (!$bans): ?><div class="empty">No bans on record.</div><?php endif; ?>
</div>
<?php \RetroBB\Core\View::partial('partials/pagination', ['page' => $page, 'pages' => $pages]); ?>

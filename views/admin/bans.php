<?php /** @var array $bans */ /** @var int $page */ /** @var int $pages */ ?>
<div class="maintitle">AdminCP — Bans</div>
<?php \RetroBB\Core\View::partial('admin/_nav', ['active' => 'bans']); ?>
<div class="recent-list">
<?php $nowTs = time(); ?>
<?php foreach ($bans as $b): ?>
<?php
$lifted = !empty($b['lifted_at']);
$expired = !$lifted && !empty($b['expires_at']) && strtotime($b['expires_at']) <= $nowTs;
$active = !$lifted && !$expired;
?>
<div class="recent-row"><span><b><?= e($b['username']) ?></b> <?= $active ? '<b style="color:#a00">[ACTIVE]</b>' : '<span class="muted">[ended]</span>' ?><?php if ($expired): ?> <span class="muted">(expired)</span><?php endif; ?><br>
<small class="muted"><?= e($b['reason']) ?> · by <?= e($b['banned_by_name']) ?> · <?= $b['expires_at'] ? 'until ' . e($b['expires_at']) : 'permanent' ?></small></span>
<span><?php if ($active): ?><form method="post" action="<?= e(url('/admin/unban/' . (int) $b['id'])) ?>" style="white-space:nowrap"><?= \RetroBB\Core\Csrf::field() ?><input name="reason" maxlength="200" placeholder="Note (optional)" style="width:150px"> <button class="smallbtn">Unban</button></form><?php endif; ?></span></div>
<?php endforeach; ?>
<?php if (!$bans): ?><div class="empty">No bans on record.</div><?php endif; ?>
</div>
<?php \RetroBB\Core\View::partial('partials/pagination', ['page' => $page, 'pages' => $pages]); ?>

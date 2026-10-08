<?php /** @var array $bans */ ?>
<div class="maintitle">AdminCP — Bans</div>
<?php \RetroBB\Core\View::partial('admin/_nav', ['active' => 'bans']); ?>
<div class="recent-list">
<?php $nowTs = time(); ?>
<?php foreach ($bans as $b): ?>
<?php $active = empty($b['lifted_at']) && (empty($b['expires_at']) || strtotime($b['expires_at']) > $nowTs); ?>
<div class="recent-row"><span><b><?= e($b['username']) ?></b> <?= $active ? '<b style="color:#a00">[ACTIVE]</b>' : '<span class="muted">[ended]</span>' ?><br>
<small class="muted"><?= e($b['reason']) ?> · by <?= e($b['banned_by_name']) ?> · <?= $b['expires_at'] ? 'until ' . e($b['expires_at']) : 'permanent' ?></small></span>
<span><?php if ($active): ?><form method="post" action="/admin/unban/<?= (int) $b['id'] ?>"><?= \RetroBB\Core\Csrf::field() ?><button class="smallbtn">Unban</button></form><?php endif; ?></span></div>
<?php endforeach; ?>
<?php if (!$bans): ?><div class="empty">No bans on record.</div><?php endif; ?>
</div>

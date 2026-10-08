<?php /** @var array $modlog */ /** @var int $modtotal */ /** @var int $modpage */ /** @var int $modpages */ ?>
<div class="maintitle">AdminCP — Mod log (<?= $modtotal ?>)</div>
<?php \RetroBB\Core\View::partial('admin/_nav', ['active' => 'modlog']); ?>
<div class="recent-list">
<?php foreach ($modlog as $m): ?>
<div class="recent-row"><span><b><?= e($m['actor'] ?? '?') ?></b> <span class="group"><?= e($m['action']) ?></span> <?= e($m['target_type']) ?>#<?= (int) $m['target_id'] ?> <small class="muted"><?= e($m['detail']) ?></small></span><span class="recent-date"><?= e(time_ago($m['created_at'])) ?></span></div>
<?php endforeach; ?>
<?php if (!$modlog): ?><div class="empty">Nothing logged yet.</div><?php endif; ?>
</div>
<?php if ($modpages > 1): ?>
<div class="pagination">Pages:
<?php for ($i = 1; $i <= $modpages; $i++): ?>
  <?php if ($i === $modpage): ?><b><?= $i ?></b>
  <?php else: ?><a href="?modpage=<?= $i ?>"><?= $i ?></a><?php endif; ?>
<?php endfor; ?>
</div>
<?php endif; ?>

<?php /** @var array $items */ /** @var int $total */ /** @var int $page */ /** @var int $pages */ /** @var array $tabs */ /** @var int $me */ ?>
<div class="breadcrumb"><a href="/">Index</a> &raquo; <a href="/pm">Messages</a> &raquo; Trash</div>
<div class="maintitle">Trash (<?= (int) $total ?>)</div>
<?php \RetroBB\Core\View::partial('pm/_tabs', ['tabs' => $tabs, 'active' => 'trash']); ?>
<p class="muted">Deleted messages stay here until both sides deleted them — then they're gone for good.</p>
<?php if (!$items): ?>
<p class="muted">Trash is empty.</p>
<?php else: ?>
<div class="recent-list">
<?php foreach ($items as $m): ?>
  <div class="recent-row"><span>
    <?= e($m['subject']) ?>
    <br><small class="muted"><?= (int) $m['sender_id'] === (int) $me ? 'to ' . e($m['recipient_name']) : 'from ' . e($m['sender_name']) ?> · <?= e($m['created_at']) ?></small>
  </span></div>
<?php endforeach; ?>
</div>
<?php \RetroBB\Core\View::partial('partials/pagination', ['page' => $page, 'pages' => $pages]); ?>
<?php endif; ?>

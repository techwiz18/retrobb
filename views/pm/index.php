<?php /** @var array $items */ /** @var int $total */ /** @var int $page */ /** @var int $pages */ /** @var array $tabs */ ?>
<div class="breadcrumb"><a href="/">Index</a> &raquo; Private messages</div>
<div class="maintitle">Inbox (<?= (int) $total ?>)</div>
<?php \RetroBB\Core\View::partial('pm/_tabs', ['tabs' => $tabs, 'active' => 'inbox']); ?>
<?php if (!$items): ?>
<p class="muted">No messages yet.</p>
<?php else: ?>
<div class="recent-list">
<?php foreach ($items as $m): ?>
  <div class="recent-row"><span>
    <?= empty($m['read_at']) ? '<b>' : '' ?><a href="/pm/<?= (int) $m['id'] ?>"><?= e($m['subject']) ?></a><?= empty($m['read_at']) ? '</b>' : '' ?>
    <br><small class="muted">from <?= e($m['sender_name']) ?> · <?= e($m['created_at']) ?><?= empty($m['read_at']) ? ' · <b>unread</b>' : '' ?></small>
  </span></div>
<?php endforeach; ?>
</div>
<?php \RetroBB\Core\View::partial('partials/pagination', ['page' => $page, 'pages' => $pages]); ?>
<?php endif; ?>

<?php /** @var array $items */ /** @var int $total */ /** @var int $page */ /** @var int $pages */ /** @var array $tabs */ ?>
<div class="breadcrumb"><a href="/">Index</a> &raquo; <a href="/pm">Messages</a> &raquo; Sent</div>
<div class="maintitle">Sent (<?= (int) $total ?>)</div>
<?php \RetroBB\Core\View::partial('pm/_tabs', ['tabs' => $tabs, 'active' => 'sent']); ?>
<?php if (!$items): ?>
<p class="muted">Nothing sent yet.</p>
<?php else: ?>
<div class="recent-list">
<?php foreach ($items as $m): ?>
  <div class="recent-row"><span>
    <a href="/pm/<?= (int) $m['id'] ?>"><?= e($m['subject']) ?></a>
    <br><small class="muted">to <?= e($m['recipient_name']) ?> · <?= e($m['created_at']) ?><?= empty($m['read_at']) ? ' · <b>unread</b>' : ' · read' ?></small>
  </span></div>
<?php endforeach; ?>
</div>
<?php \RetroBB\Core\View::partial('partials/pagination', ['page' => $page, 'pages' => $pages]); ?>
<?php endif; ?>

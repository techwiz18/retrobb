<?php /** @var array $items */ /** @var int $total */ /** @var int $page */ /** @var int $pages */ ?>
<div class="breadcrumb"><a href="<?= e(url('/')) ?>">Index</a> &raquo; Alerts</div>
<div class="maintitle">Alerts (<?= (int) $total ?>)</div>
<?php if (!$items): ?>
<p class="muted">No alerts yet. Mentions, replies and reactions will show up here.</p>
<?php else: ?>
<div class="recent-list">
<?php foreach ($items as $n): ?>
  <div class="recent-row"><span>
    <?php if ($n['type'] === 'mention'): ?>👋 <b><?= username_html(['user_id' => $n['actor_id'] ?? 0, 'username' => $n['actor_name'] ?? '?'], false) ?></b> mentioned you
    <?php elseif ($n['type'] === 'reply'): ?>💬 <b><?= username_html(['user_id' => $n['actor_id'] ?? 0, 'username' => $n['actor_name'] ?? '?'], false) ?></b> replied
    <?php elseif ($n['type'] === 'warning'): ?>⚠ <b><?= username_html(['user_id' => $n['actor_id'] ?? 0, 'username' => $n['actor_name'] ?? '?'], false) ?></b> warned you<?php if (!empty($n['detail'])): ?>: <?= e($n['detail']) ?><?php endif; ?>
    <?php else: ?>👍 <b><?= username_html(['user_id' => $n['actor_id'] ?? 0, 'username' => $n['actor_name'] ?? '?'], false) ?></b> reacted to your post<?php endif; ?>
    <?php if (!empty($n['topic_id'])): ?> in <?php if (!empty($n['topic_title'])): ?><a href="<?= e(\RetroBB\Core\Slug::topicUrl(['id' => (int) $n['topic_id'], 'title' => $n['topic_title']])) ?><?= !empty($n['post_id']) ? '#p' . (int) $n['post_id'] : '' ?>"><?= e($n['topic_title']) ?></a><?php else: ?>a deleted topic<?php endif; ?><?php endif; ?>
    <br><small class="muted"><?= e($n['created_at']) ?></small>
  </span></div>
<?php endforeach; ?>
</div>
<?php \RetroBB\Core\View::partial('partials/pagination', ['page' => $page, 'pages' => $pages]); ?>
<?php endif; ?>

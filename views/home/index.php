<?php /** @var array $cats */ /** @var array $stats */ /** @var array $unreadForums */ ?>
<div class="maintitle">Board Index</div>
<?php foreach ($cats as $cat): ?>
<div class="cat-row"><?= e($cat['title']) ?></div>
<div class="forum-list">
  <?php foreach ($cat['forums'] as $f): ?>
  <?php $hasNew = !empty($unreadForums[(int) $f['id']]); ?>
  <div class="forum-row">
    <div class="forum-icon" title="<?= $hasNew ? 'New posts' : 'No new posts' ?>"><?= $hasNew ? '🆕' : '📁' ?></div>
    <div class="forum-main">
      <a class="forum-name" href="<?= e(\RetroBB\Core\Slug::forumUrl($f)) ?>"><?= e($f['name']) ?></a>
      <div class="forum-desc"><?= e($f['description']) ?></div>
    </div>
    <div class="forum-stats"><b><?= (int) $f['posts_count'] ?></b> posts<br><b><?= (int) $f['topics_count'] ?></b> topics</div>
    <div class="forum-last">
      <?php if (!empty($f['last_topic_id'])): ?>
        <?php $lastUrl = url('/topic/' . ($f['last_slug'] ?: 'topic') . '.t' . (int) $f['last_topic_id']); ?>
        <a class="lastpost-title" href="<?= e($lastUrl) ?>"><?= e($f['last_title'] ?? 'View') ?></a>
        <small class="muted">by <?= username_html(['user_id' => $f['last_user_id'] ?? 0, 'username' => $f['last_user'] ?? '?']) ?> <a href="<?= e($lastUrl) ?>" title="Jump to last post">»</a><br><?= e(post_date((string) ($f['last_at'] ?? ''))) ?></small>
      <?php else: ?><small>No posts yet</small><?php endif; ?>
    </div>
  </div>
  <?php endforeach; ?>
</div>
<?php endforeach; ?>

<div class="maintitle">Info Center</div>
<div class="infocenter">
  <div class="recent-row"><span>📊 <b><?= (int) $stats['posts'] ?></b> posts in <b><?= (int) $stats['topics'] ?></b> topics by <b><?= (int) $stats['users'] ?></b> members. Newest member: <?= username_html(['user_id' => $stats['newest_id'] ?? 0, 'username' => $stats['newest']]) ?></span></div>
</div>
<?php \RetroBB\Core\Hooks::render_template_hook('board_index_after', ['cats' => $cats]); ?>

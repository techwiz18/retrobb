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
        <small class="muted">Last post by <b><?php if (!empty($f['last_user_id'])): ?><a href="<?= e(\RetroBB\Core\Slug::memberUrl(['id' => $f['last_user_id'], 'username' => $f['last_user'] ?? 'user'])) ?>"><?= e($f['last_user'] ?? '?') ?></a><?php else: ?><?= e($f['last_user'] ?? '?') ?><?php endif; ?></b> in <a href="/topic/<?= e($f['last_slug'] ?: 'topic') ?>.t<?= (int) $f['last_topic_id'] ?>"><?= e($f['last_title'] ?? 'View') ?></a><?php if (!empty($f['last_at'])): ?><br><?= e(time_ago($f['last_at'])) ?><?php endif; ?></small>
      <?php else: ?><small>No posts yet</small><?php endif; ?>
    </div>
  </div>
  <?php endforeach; ?>
</div>
<?php endforeach; ?>

<div class="maintitle">Info Center</div>
<div class="infocenter">
  <div class="recent-row"><span>📊 <b><?= (int) $stats['posts'] ?></b> posts in <b><?= (int) $stats['topics'] ?></b> topics by <b><?= (int) $stats['users'] ?></b> members. Newest member: <?php if (!empty($stats['newest_id'])): ?><a href="<?= e(\RetroBB\Core\Slug::memberUrl(['id' => $stats['newest_id'], 'username' => $stats['newest']])) ?>"><b><?= e($stats['newest']) ?></b></a><?php else: ?><b><?= e($stats['newest']) ?></b><?php endif; ?></span></div>
</div>
<?php \RetroBB\Core\Hooks::render_template_hook('board_index_after', ['cats' => $cats]); ?>

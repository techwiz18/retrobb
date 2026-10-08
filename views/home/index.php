<?php /** @var array $cats */ /** @var array $stats */ ?>
<div class="maintitle">Board Index</div>
<?php foreach ($cats as $cat): ?>
<div class="cat-row"><?= e($cat['title']) ?></div>
<div class="forum-list">
  <?php foreach ($cat['forums'] as $f): ?>
  <div class="forum-row">
    <div class="forum-icon">📁</div>
    <div class="forum-main">
      <a class="forum-name" href="<?= e(\RetroBB\Core\Slug::forumUrl($f)) ?>"><?= e($f['name']) ?></a>
      <div class="forum-desc"><?= e($f['description']) ?></div>
    </div>
    <div class="forum-stats"><b><?= (int) $f['topics_count'] ?></b> topics<br><b><?= (int) $f['posts_count'] ?></b> posts</div>
    <div class="forum-last">
      <?php if (!empty($f['last_topic_id'])): ?>
        <a href="/topic/<?= e($f['last_slug'] ?: 'topic') ?>.t<?= (int) $f['last_topic_id'] ?>"><?= e($f['last_title'] ?? 'View') ?></a><br>
        <small>by <?= e($f['last_user'] ?? '?') ?></small>
      <?php else: ?><small>No posts yet</small><?php endif; ?>
    </div>
  </div>
  <?php endforeach; ?>
</div>
<?php endforeach; ?>

<div class="statsbar">
  <b><?= (int) $stats['topics'] ?></b> topics · <b><?= (int) $stats['posts'] ?></b> posts · <b><?= (int) $stats['users'] ?></b> members · Newest: <b><?= e($stats['newest']) ?></b>
</div>
<?php \RetroBB\Core\Hooks::render_template_hook('board_index_after', ['cats' => $cats]); ?>

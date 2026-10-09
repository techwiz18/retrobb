<?php /** @var array $forum */ /** @var array $topics */ /** @var array $unread */ /** @var int $page */ /** @var int $pages */ /** @var int $total */ ?>
<div class="breadcrumb"><a href="/">Index</a> &raquo; <?= e($forum['name']) ?></div>
<div class="maintitle"><?= e($forum['name']) ?></div>
<div class="subtitle"><?= e($forum['description']) ?></div>
<div class="actionrow">
  <?php if (\RetroBB\Core\Auth::check()): ?>
    <a class="btn" href="/new-topic/<?= (int) $forum['id'] ?>">+ New Topic</a>
  <?php else: ?>
    <a class="btn" href="/login?next=<?= urlencode('/new-topic/' . $forum['id']) ?>">Log in to post</a>
  <?php endif; ?>
  <span class="muted"><?= $total ?> topics · page <?= $page ?>/<?= max(1, $pages) ?></span>
</div>
<div class="topic-list">
  <div class="topic-head"><span></span><span>Topic</span><span class="c">Replies</span><span class="c">Views</span><span class="r">Last post</span></div>
  <?php foreach ($topics as $t): ?>
  <?php
    if ((int) $t['locked'] === 1) { $icon = '🔒'; $iconTitle = 'Locked'; }
    elseif ((int) $t['pinned'] === 1) { $icon = '📌'; $iconTitle = 'Pinned'; }
    elseif ((int) $t['posts_count'] >= 10 || (int) $t['views'] >= 100) { $icon = '🔥'; $iconTitle = 'Hot topic'; }
    else { $icon = '💬'; $iconTitle = 'Topic'; }
  ?>
  <div class="topic-row">
    <div class="topic-icon" title="<?= $iconTitle ?>"><?= $icon ?></div>
    <div><a href="<?= e(\RetroBB\Core\Slug::topicUrl($t)) ?>"><?= e($t['title']) ?></a><?php if (!empty($unread[(int) $t['id']])): ?> <span class="pill">New</span><?php endif; ?><br><small>by <a href="<?= e(\RetroBB\Core\Slug::memberUrl(['id' => $t['user_id'], 'username' => $t['author']])) ?>"><?= e($t['author']) ?></a> · <?= e(time_ago($t['created_at'])) ?></small></div>
    <div class="c"><?= max(0, (int) $t['posts_count'] - 1) ?></div>
    <div class="c"><?= (int) $t['views'] ?></div>
    <div class="r"><small><?php if (!empty($t['last_user_id'])): ?><a href="<?= e(\RetroBB\Core\Slug::memberUrl(['id' => $t['last_user_id'], 'username' => $t['last_user'] ?? 'user'])) ?>"><?= e($t['last_user'] ?? $t['author']) ?></a><?php else: ?><?= e($t['last_user'] ?? $t['author']) ?><?php endif; ?><br><?= e(time_ago($t['last_post_at'])) ?></small></div>
  </div>
  <?php endforeach; ?>
  <?php if (!$topics): ?><div class="empty">No topics yet. Be the first!</div><?php endif; ?>
</div>
<?php \RetroBB\Core\View::partial('partials/pagination', ['page' => $page, 'pages' => $pages]); ?>

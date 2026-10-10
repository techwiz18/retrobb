<?php /** @var string $q */ /** @var array $terms */ /** @var array $topics */ /** @var int $total */ /** @var int $page */ /** @var int $pages */ /** @var string|null $error */ ?>
<div class="breadcrumb"><a href="/">Index</a> &raquo; Search</div>
<div class="maintitle">Search</div>
<form method="get" action="/search" class="form">
  <input name="q" value="<?= e($q) ?>" maxlength="100" style="width:70%" placeholder="Two or more characters…">
  <button class="btn" type="submit">Search</button>
</form>
<?php if (!empty($error)): ?><div class="flash-error"><?= e($error) ?></div><?php endif; ?>
<?php if ($q !== '' && empty($error)): ?>
<div class="maintitle"><?= (int) $total ?> result<?= (int) $total === 1 ? '' : 's' ?> for “<?= e($q) ?>”</div>
<?php if (!$topics): ?><p class="muted">Nothing matched. Try fewer or different words.</p><?php else: ?>
<div class="recent-list">
<?php foreach ($topics as $t): ?>
  <div class="recent-row"><span>
    <a href="<?= e(\RetroBB\Core\Slug::topicUrl($t)) ?>"><?= e($t['title']) ?></a><br><small class="muted"><?= e(\RetroBB\Core\BBCode::excerpt($t['snippet'] ?? '')) ?></small><br><small>in <a href="<?= e(\RetroBB\Core\Slug::forumUrl(['id' => $t['forum_id'], 'name' => $t['forum_name']])) ?>"><?= e($t['forum_name']) ?></a> · <?= (int) $t['posts_count'] ?> posts</small>
  </span><span class="recent-date"><?= e(time_ago($t['last_post_at'])) ?></span></div>
<?php endforeach; ?>
</div>
<?php \RetroBB\Core\View::partial('partials/pagination', ['page' => $page, 'pages' => $pages, 'query' => '&q=' . urlencode($q)]); ?>
<?php endif; ?>
<?php endif; ?>

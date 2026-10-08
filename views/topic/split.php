<?php /** @var array $topic */ /** @var array $posts */ /** @var string|null $error */ ?>
<div class="breadcrumb"><a href="/">Index</a> &raquo; <a href="<?= e(\RetroBB\Core\Slug::topicUrl($topic)) ?>"><?= e($topic['title']) ?></a> &raquo; Split</div>
<div class="maintitle">Split posts into a new topic</div>
<p class="muted">Tick the posts to move out. The first post cannot be left alone — at least one post must stay.</p>
<?php if (!empty($error)): ?><div class="flash-error"><?= e($error) ?></div><?php endif; ?>
<form method="post" class="form">
  <?= \RetroBB\Core\Csrf::field() ?>
  <label>New topic title<br><input type="text" name="title" maxlength="120" required style="width:100%"></label><br><br>
  <?php foreach ($posts as $p): ?>
  <div class="recent-row">
    <span><label><input type="checkbox" name="post_ids[]" value="<?= (int) $p['id'] ?>"> <b><?= e($p['username']) ?></b> — <?= e(\RetroBB\Core\BBCode::excerpt($p['body_bbcode'], 90)) ?></label></span>
    <span class="recent-date"><?= e(time_ago($p['created_at'])) ?></span>
  </div>
  <?php endforeach; ?>
  <br><button class="btn" type="submit">Split selected</button>
</form>

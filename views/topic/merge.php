<?php /** @var array $topic */ /** @var string $q */ /** @var array $candidates */ ?>
<div class="breadcrumb"><a href="/">Index</a> &raquo; <a href="<?= e(\RetroBB\Core\Slug::topicUrl($topic)) ?>"><?= e($topic['title']) ?></a> &raquo; Merge</div>
<div class="maintitle">Merge “<?= e($topic['title']) ?>” into…</div>
<p class="muted">Pick the topic that survives. All posts from this topic move over, then this topic is removed.</p>
<form method="get" class="form" style="margin-bottom:12px">
  <input type="text" name="q" value="<?= e($q) ?>" placeholder="Search by title…" style="width:60%">
  <button class="smallbtn" type="submit">Search</button>
</form>
<form method="post" onsubmit="return confirm('Merge this topic into the selected one? This topic will be removed.')">
  <?= \RetroBB\Core\Csrf::field() ?>
  <div class="recent-list">
  <?php foreach ($candidates as $c): ?>
    <div class="recent-row"><span><label><input type="radio" name="target" value="<?= (int) $c['id'] ?>" required> <b><?= e($c['title']) ?></b> <small class="muted">in <?= e($c['forum_name']) ?></small></label></span></div>
  <?php endforeach; ?>
  <?php if (!$candidates): ?><div class="empty">No other topics found.</div><?php endif; ?>
  </div>
  <br><button class="btn" type="submit">Merge selected</button>
</form>

<?php /** @var array $report */ /** @var array|null $post */ /** @var array|null $topic */ /** @var string|null $error */ ?>
<div class="breadcrumb"><a href="/">Index</a> &raquo; <a href="/mod/reports">Mod queue</a> &raquo; Resolve #<?= (int) $report['id'] ?></div>
<div class="maintitle">Resolve report #<?= (int) $report['id'] ?></div>
<?php if ($post && $topic): ?>
<div class="recent-list"><div class="recent-row"><span>
  Post by <b><?= e($post['username']) ?></b> in <a href="<?= e(\RetroBB\Core\Slug::topicUrl(['id' => $topic['id'], 'title' => $topic['title']])) ?>#p<?= (int) $post['id'] ?>"><?= e($topic['title']) ?></a><br>
  <small class="muted">Reported reason: <?= e($report['reason']) ?></small>
</span></div></div>
<?php endif; ?>
<?php if (!empty($error)): ?><div class="flash-error"><?= e($error) ?></div><?php endif; ?>
<form method="post" action="/mod/report/<?= (int) $report['id'] ?>/handle" class="form">
  <?= \RetroBB\Core\Csrf::field() ?>
  <input type="hidden" name="status" value="resolved">
  <label>What action did you take? (required — recorded in the mod log)<br>
  <textarea name="note" rows="3" required maxlength="500" style="width:100%" placeholder="Warned the author, deleted the post, spoke to both sides..."></textarea></label><br><br>
  <button class="btn" type="submit">Resolve report</button>
</form>

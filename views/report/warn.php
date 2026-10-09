<?php /** @var array $report */ /** @var array|null $post */ /** @var array|null $topic */ /** @var string|null $error */ ?>
<div class="breadcrumb"><a href="/">Index</a> &raquo; <a href="/mod/reports">Mod queue</a> &raquo; Warn author #<?= (int) $report['id'] ?></div>
<div class="maintitle">Warn author (report #<?= (int) $report['id'] ?>)</div>
<?php if ($post && $topic): ?>
<div class="recent-list"><div class="recent-row"><span>
  Post by <b><?= e($post['username']) ?></b> in <a href="<?= e(\RetroBB\Core\Slug::topicUrl(['id' => $topic['id'], 'title' => $topic['title']])) ?>#p<?= (int) $post['id'] ?>"><?= e($topic['title']) ?></a><br>
  <small class="muted">Reported reason (for reference, not the warning): <?= e($report['reason']) ?></small>
</span></div></div>
<?php endif; ?>
<?php if (!empty($error)): ?><div class="flash-error"><?= e($error) ?></div><?php endif; ?>
<form method="post" action="/mod/report/<?= (int) $report['id'] ?>/warn-author" class="form">
  <?= \RetroBB\Core\Csrf::field() ?>
  <label>Warning reason (required — the user sees this, and it lands in their alerts)<br>
  <textarea name="reason" rows="3" required maxlength="500" style="width:100%" placeholder="Why is this behaviour not OK?"></textarea></label><br><br>
  <button class="btn" type="submit">Issue warning</button>
</form>

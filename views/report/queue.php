<?php /** @var array $reports */ /** @var int $total */ /** @var int $page */ /** @var int $pages */ ?>
<div class="breadcrumb"><a href="/">Index</a> &raquo; Mod queue</div>
<div class="maintitle">Mod queue — <?= $total ?> open report<?= $total === 1 ? '' : 's' ?></div>
<div class="recent-list">
<?php foreach ($reports as $r): ?>
  <div class="recent-row" style="align-items:flex-start">
    <div>
      <b>#<?= (int) $r['id'] ?></b> — post by <b><?= e($r['post_author']) ?></b> in
      <a href="/topic/<?= e($r['topic_slug'] ?: 'topic') ?>.t<?= (int) $r['topic_id'] ?>#p<?= (int) $r['post_id'] ?>"><?= e($r['topic_title']) ?></a><br>
      <small>reported by <?= e($r['reporter']) ?> · <?= e(time_ago($r['created_at'])) ?></small><br>
      <span><?= e($r['reason']) ?></span>
    </div>
    <span style="white-space:nowrap">
      <form method="post" action="/mod/report/<?= (int) $r['id'] ?>/handle" style="display:inline"><?= \RetroBB\Core\Csrf::field() ?>
        <input type="hidden" name="status" value="resolved"><button class="smallbtn">Resolve</button></form>
      <form method="post" action="/mod/report/<?= (int) $r['id'] ?>/handle" style="display:inline"><?= \RetroBB\Core\Csrf::field() ?>
        <input type="hidden" name="status" value="dismissed"><button class="smallbtn">Dismiss</button></form>
    </span>
  </div>
<?php endforeach; ?>
<?php if (!$reports): ?><div class="empty">Queue is clear. Touch grass. 🌿</div><?php endif; ?>
</div>
<?php \RetroBB\Core\View::partial('partials/pagination', ['page' => $page, 'pages' => $pages]); ?>

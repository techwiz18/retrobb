<?php /** @var array $reports */ /** @var int $total */ /** @var int $page */ /** @var int $pages */ ?>
<div class="breadcrumb"><a href="/">Index</a> &raquo; Mod queue</div>
<div class="maintitle">Mod queue — <?= $total ?> open report<?= $total === 1 ? '' : 's' ?></div>
<?php if (\RetroBB\Core\Auth::isAdmin()): ?><?php \RetroBB\Core\View::partial('admin/_nav', ['active' => 'queue']); ?><?php endif; ?>
<div class="recent-list">
<?php foreach ($reports as $r): ?>
  <div class="recent-row" style="align-items:flex-start">
    <div>
      <b>#<?= (int) $r['id'] ?></b> — post by <b><a href="<?= e(\RetroBB\Core\Slug::memberUrl(['id' => $r['author_id'], 'username' => $r['post_author']])) ?>"><?= e($r['post_author']) ?></a></b> in
      <a href="/topic/<?= e($r['topic_slug'] ?: 'topic') ?>.t<?= (int) $r['topic_id'] ?>#p<?= (int) $r['post_id'] ?>"><?= e($r['topic_title']) ?></a><br>
      <small>reported by <a href="<?= e(\RetroBB\Core\Slug::memberUrl(['id' => $r['reporter_id'], 'username' => $r['reporter']])) ?>"><?= e($r['reporter']) ?></a> · <?= e(time_ago($r['created_at'])) ?></small><br>
      <span><?= e($r['reason']) ?></span>
    </div>
    <span style="white-space:nowrap">
      <form method="post" action="/mod/report/<?= (int) $r['id'] ?>/handle" style="display:inline"><?= \RetroBB\Core\Csrf::field() ?>
        <input type="hidden" name="status" value="resolved"><button class="smallbtn">Resolve</button></form>
      <form method="post" action="/mod/report/<?= (int) $r['id'] ?>/handle" style="display:inline"><?= \RetroBB\Core\Csrf::field() ?>
        <input type="hidden" name="status" value="dismissed"><button class="smallbtn">Dismiss</button></form>
      <form method="post" action="/mod/report/<?= (int) $r['id'] ?>/delete-post" style="display:inline" onsubmit="return confirm('Delete the reported post?')"><?= \RetroBB\Core\Csrf::field() ?><button class="smallbtn danger">Delete post</button></form>
      <form method="post" action="/mod/report/<?= (int) $r['id'] ?>/warn-author" style="display:inline"><?= \RetroBB\Core\Csrf::field() ?><button class="smallbtn">Warn author</button></form>
    </span>
  </div>
<?php endforeach; ?>
<?php if (!$reports): ?><div class="empty">Queue is clear. Touch grass. 🌿</div><?php endif; ?>
</div>
<?php \RetroBB\Core\View::partial('partials/pagination', ['page' => $page, 'pages' => $pages]); ?>

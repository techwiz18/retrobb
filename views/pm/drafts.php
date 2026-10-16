<?php /** @var array $items */ /** @var array $tabs */ ?>
<div class="breadcrumb"><a href="<?= e(url('/')) ?>">Index</a> &raquo; <a href="<?= e(url('/pm')) ?>">Messages</a> &raquo; Drafts</div>
<div class="maintitle">Drafts (<?= count($items) ?>)</div>
<?php \RetroBB\Core\View::partial('pm/_tabs', ['tabs' => $tabs, 'active' => 'drafts']); ?>
<p><a class="btn" href="<?= e(url('/pm/draft/new')) ?>">New draft</a></p>
<?php if (!$items): ?>
<p class="muted">No drafts. Start one any time — it waits here until you send it.</p>
<?php else: ?>
<div class="recent-list">
<?php foreach ($items as $d): ?>
  <div class="recent-row"><span>
    <a href="<?= e(url('/pm/draft/' . (int) $d['id'])) ?>"><?= e($d['subject'] !== '' ? $d['subject'] : '(no subject)') ?></a>
    <br><small class="muted"><?= $d['to_name'] !== '' ? 'to ' . e($d['to_name']) . ' · ' : '' ?>edited <?= e($d['updated_at']) ?></small>
  </span><span>
    <form method="post" action="<?= e(url('/pm/draft/' . (int) $d['id'] . '/discard')) ?>" style="display:inline" onsubmit="return confirm('Discard this draft?')"><?= \RetroBB\Core\Csrf::field() ?><button class="smallbtn danger" type="submit">Discard</button></form>
  </span></div>
<?php endforeach; ?>
</div>
<?php endif; ?>

<?php /** @var array $pm */ /** @var array|null $parent */ /** @var array $replies */ /** @var string $otherName */ ?>
<div class="breadcrumb"><a href="/">Index</a> &raquo; <a href="/pm">Messages</a> &raquo; <?= e($pm['subject']) ?></div>
<div class="maintitle"><?= e($pm['subject']) ?></div>
<div class="actionrow"><span class="muted">from <b><?= e($pm['sender_name']) ?></b> to <b><?= e($pm['recipient_name']) ?></b> · <?= e($pm['created_at']) ?></span></div>
<?php if ($parent): ?><div class="actionrow"><span class="muted">In reply to <a href="/pm/<?= (int) $parent['id'] ?>"><?= e($parent['subject']) ?></a> from <b><?= e($parent['sender_name']) ?></b></span></div><?php endif; ?>
<div class="postbit"><div class="postbody"><div class="postright" style="width:100%"><?= \RetroBB\Core\BBCode::needsRepair($pm['body_html']) ? \RetroBB\Core\BBCode::toHtml($pm['body_bbcode']) : $pm['body_html'] ?></div></div></div>
<?php if ($replies): ?>
<div class="maintitle">Replies in this thread (<?= count($replies) ?>)</div>
<div class="recent-list">
<?php foreach ($replies as $r): ?>
  <div class="recent-row"><span><a href="/pm/<?= (int) $r['id'] ?>"><?= e($r['subject']) ?></a><br><small class="muted">from <?= e($r['sender_name']) ?> · <?= e($r['created_at']) ?></small></span></div>
<?php endforeach; ?>
</div>
<?php endif; ?>
<p><a class="smallbtn" href="/pm/new?to=<?= urlencode($otherName) ?>&reply_to=<?= (int) $pm['id'] ?>">Reply</a> <a class="smallbtn" href="/pm">Back to inbox</a>
<form method="post" action="/pm/<?= (int) $pm['id'] ?>/delete" style="display:inline" onsubmit="return confirm('Delete this message?')"><?= \RetroBB\Core\Csrf::field() ?><button class="smallbtn danger" type="submit">Delete</button></form></p>

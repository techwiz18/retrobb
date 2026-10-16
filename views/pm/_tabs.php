<?php /** @var array $tabs */ /** @var string $active */ ?>
<p><a class="btn" href="<?= e(url('/pm/new')) ?>">New message</a></p>
<nav class="adminnav">
  <a href="<?= e(url('/pm')) ?>" class="<?= ($active ?? '') === 'inbox' ? 'on' : '' ?>">Inbox<?= ($tabs['unread'] ?? 0) > 0 ? ' (' . (int) $tabs['unread'] . ')' : '' ?></a>
  <a href="<?= e(url('/pm/sent')) ?>" class="<?= ($active ?? '') === 'sent' ? 'on' : '' ?>">Sent<?= ($tabs['sent'] ?? 0) > 0 ? ' (' . (int) $tabs['sent'] . ')' : '' ?></a>
  <a href="<?= e(url('/pm/drafts')) ?>" class="<?= ($active ?? '') === 'drafts' ? 'on' : '' ?>">Drafts<?= ($tabs['drafts'] ?? 0) > 0 ? ' (' . (int) $tabs['drafts'] . ')' : '' ?></a>
  <a href="<?= e(url('/pm/trash')) ?>" class="<?= ($active ?? '') === 'trash' ? 'on' : '' ?>">Trash<?= ($tabs['trash'] ?? 0) > 0 ? ' (' . (int) $tabs['trash'] . ')' : '' ?></a>
</nav>

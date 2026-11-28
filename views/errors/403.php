<?php /** @var string|null $message */ ?>
<div class="maintitle">403 — Forbidden</div>
<?php if (!empty($message)): ?><div class="flash-error"><?= e($message) ?></div><?php endif; ?>
<p>You don't have permission. <a href="<?= e(url('/')) ?>">Back to index</a>.</p>

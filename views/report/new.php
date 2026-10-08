<?php /** @var array $post */ /** @var string|null $error */ ?>
<div class="breadcrumb"><a href="/">Index</a> &raquo; Report post</div>
<div class="maintitle">Report post by <?= e($post['username']) ?></div>
<?php if (!empty($error)): ?><div class="flash-error"><?= e($error) ?></div><?php endif; ?>
<form method="post" class="form">
  <?= \RetroBB\Core\Csrf::field() ?>
  <label>Why does this post need moderator attention?<br>
  <textarea name="reason" rows="4" required maxlength="500" style="width:100%" placeholder="Spam, abuse, off-topic..."></textarea></label><br><br>
  <button class="btn" type="submit">Send report</button>
</form>

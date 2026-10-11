<?php /** @var bool $sent */ /** @var bool|null $badlink */ ?>
<div class="maintitle">Forgot password</div>
<?php if (!empty($badlink)): ?><div class="flash-error">That reset link is invalid or expired — request a fresh one below.</div><?php endif; ?>
<?php if ($sent): ?>
<div class="flash-ok">If an account matches, mail with a one-hour reset link is on its way. Nothing arrived? Check spam, or ask the board admin.</div>
<?php else: ?>
<form method="post" class="form narrow">
  <?= \RetroBB\Core\Csrf::field() ?>
  <label>Username or email<br><input type="text" name="login" required></label><br><br>
  <button class="btn" type="submit">Send reset link</button>
</form>
<?php endif; ?>

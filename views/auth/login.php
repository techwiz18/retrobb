<?php /** @var string|null $error */ ?>
<div class="maintitle">Log in</div>
<?php if (!empty($error)): ?><div class="flash-error"><?= e($error) ?></div><?php endif; ?>
<form method="post" class="form narrow">
  <?= \RetroBB\Core\Csrf::field() ?>
  <input type="hidden" name="next" value="<?= e($_GET['next'] ?? '/') ?>">
  <label>Username or email<br><input type="text" name="login" required></label><br><br>
  <label>Password<br><input type="password" name="password" required></label><br><br>
  <button class="btn" type="submit">Log in</button>
  <p class="muted">No account? <a href="/register">Register</a></p>
</form>

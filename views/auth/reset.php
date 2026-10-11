<?php /** @var string $token */ /** @var string|null $error */ ?>
<div class="maintitle">Set new password</div>
<?php if (!empty($error)): ?><div class="flash-error"><?= e($error) ?></div><?php endif; ?>
<form method="post" class="form narrow">
  <?= \RetroBB\Core\Csrf::field() ?>
  <input type="hidden" name="token" value="<?= e($token) ?>">
  <label>New password (8+ chars)<br><input type="password" name="password" required></label><br><br>
  <label>Repeat password<br><input type="password" name="password2" required></label><br><br>
  <button class="btn" type="submit">Set password</button>
</form>

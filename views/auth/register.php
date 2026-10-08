<?php /** @var array $errors */ ?>
<div class="maintitle">Register</div>
<?php foreach ($errors as $er): ?><div class="flash-error"><?= e($er) ?></div><?php endforeach; ?>
<form method="post" class="form narrow">
  <?= \RetroBB\Core\Csrf::field() ?>
  <label>Username<br><input type="text" name="username" required></label><br><br>
  <label>Email<br><input type="email" name="email" required></label><br><br>
  <label>Password (8+ chars)<br><input type="password" name="password" required></label><br><br>
  <button class="btn" type="submit">Create account</button>
</form>

<?php /** @var array $edituser */ /** @var string|null $error */ ?>
<div class="breadcrumb"><a href="/">Index</a> &raquo; <a href="<?= e(\RetroBB\Core\Slug::memberUrl($edituser)) ?>"><?= e($edituser['username']) ?></a> &raquo; Edit profile</div>
<div class="maintitle">Edit profile</div>
<?php if (!empty($error)): ?><div class="flash-error"><?= e($error) ?></div><?php endif; ?>
<div class="admin-grid">
<form method="post" class="form">
  <?= \RetroBB\Core\Csrf::field() ?>
  <input type="hidden" name="form" value="profile">
  <label>Email<br><input type="email" name="email" value="<?= e($edituser['email']) ?>" required style="width:100%"></label><br><br>
  <label>Bio (shown on your profile)<br><textarea name="bio" rows="4" maxlength="1000" style="width:100%"><?= e($edituser['bio'] ?? '') ?></textarea></label><br><br>
  <button class="btn" type="submit">Save profile</button>
</form>
<form method="post" class="form narrow">
  <?= \RetroBB\Core\Csrf::field() ?>
  <input type="hidden" name="form" value="password">
  <label>Current password<br><input type="password" name="current" required></label><br><br>
  <label>New password (8+ chars)<br><input type="password" name="new" required></label><br><br>
  <button class="btn" type="submit">Change password</button>
</form>
</div>

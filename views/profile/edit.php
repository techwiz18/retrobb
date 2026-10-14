<?php /** @var array $edituser */ /** @var string|null $error */ ?>
<div class="breadcrumb"><a href="<?= e(url('/')) ?>">Index</a> &raquo; <a href="<?= e(\RetroBB\Core\Slug::memberUrl($edituser)) ?>"><?= e($edituser['username']) ?></a> &raquo; Edit profile</div>
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
<?php if (feature('avatars_upload') || feature('avatars_link')): ?>
<form method="post" class="form" enctype="multipart/form-data">
  <?= \RetroBB\Core\Csrf::field() ?>
  <input type="hidden" name="form" value="avatar">
  <div class="maintitle">Avatar</div><br>
  <div><?= avatar_html($edituser) ?></div><br>
  <label><input type="radio" name="avatar_kind" value="none" <?= ($edituser['avatar_kind'] ?? 'none') === 'none' ? 'checked' : '' ?>> No avatar</label><br>
  <?php if (feature('avatars_upload')): ?>
  <?php $lim = avatar_limits(); ?>
  <label><input type="radio" name="avatar_kind" value="upload" <?= ($edituser['avatar_kind'] ?? '') === 'upload' ? 'checked' : '' ?>> Upload (JPG/PNG/GIF, max <?= (int) round($lim['bytes'] / 1024) ?> KB<?= avatar_resize_available() ? ', larger images are shrunk to fit' : '' ?>, max <?= $lim['width'] ?>×<?= $lim['height'] ?>px)</label><br>
  <input type="file" name="avatar" accept=".jpg,.jpeg,.png,.gif"><br>
  <?php endif; ?>
  <?php if (feature('avatars_link')): ?>
  <label><input type="radio" name="avatar_kind" value="link" <?= ($edituser['avatar_kind'] ?? '') === 'link' ? 'checked' : '' ?>> Link an image</label><br>
  <input type="url" name="avatar_url" value="<?= e($edituser['avatar_url'] ?? '') ?>" placeholder="https://…" style="width:100%"><br>
  <small class="muted">Hotlinked images reveal visitor IPs to the image host.</small><br>
  <?php endif; ?>
  <br><button class="btn" type="submit">Save avatar</button>
</form>
<?php endif; ?>
<form method="post" class="form narrow">
  <?= \RetroBB\Core\Csrf::field() ?>
  <input type="hidden" name="form" value="password">
  <label>Current password<br><input type="password" name="current" required></label><br><br>
  <label>New password (8+ chars)<br><input type="password" name="new" required></label><br><br>
  <label>Repeat new password<br><input type="password" name="new2" required></label><br><br>
  <button class="btn" type="submit">Change password</button>
</form>
</div>

<?php /** @var array $profile */ /** @var array $recent */ /** @var array $warnings */ /** @var array|null $activeBan */ ?>
<?php $hue = abs(crc32($profile['username'])) % 360; $initial = mb_strtoupper(mb_substr($profile['username'], 0, 1)); ?>
<div class="breadcrumb"><a href="/">Index</a> &raquo; <a href="/members">Members</a> &raquo; <?= e($profile['username']) ?></div>
<div class="profile-card">
  <span class="avatar avatar-lg" style="background:hsl(<?= $hue ?>,45%,55%)"><?= e($initial) ?></span>
  <div class="profile-id">
    <div class="profile-name"><?= e($profile['username']) ?></div>
    <div><span class="group group-<?= e($profile['user_group']) ?>"><?= e($profile['user_group']) ?></span>
    <?php $me2 = \RetroBB\Core\Auth::user(); ?>
    <?php if ($me2 && (int) $me2['id'] === (int) $profile['id']): ?> <a class="smallbtn" href="/settings/profile">Edit profile</a><?php elseif ($me2 && feature('pms')): ?> <a class="smallbtn" href="/pm/new?to=<?= urlencode($profile['username']) ?>">Message</a><?php endif; ?></div>
    <?php if (!empty($profile['bio'])): ?><div class="profile-bio"><?= nl2br(e($profile['bio'])) ?></div><?php endif; ?>
    <div class="profile-stats">
      <div class="stat"><span class="num"><?= (int) $profile['posts_count'] ?></span><span class="lbl">Posts</span></div>
      <div class="stat"><span class="num">#<?= (int) $profile['id'] ?></span><span class="lbl">Member No.</span></div>
      <div class="stat"><span class="num"><?= e(substr($profile['created_at'], 0, 10)) ?></span><span class="lbl">Joined</span></div>
    </div>
  </div>
</div>
<div class="maintitle">Recent posts by <?= e($profile['username']) ?></div>
<div class="recent-list">
<?php foreach ($recent as $r): ?>
  <div class="recent-row">
    <a href="/topic/<?= e($r['slug'] ?: 'topic') ?>.t<?= (int) $r['id'] ?>"><?= e($r['title']) ?></a>
    <span class="recent-date"><?= e(time_ago($r['created_at'])) ?></span>
  </div>
<?php endforeach; ?>
<?php if (!$recent): ?><div class="empty">No posts yet.</div><?php endif; ?>
</div>
<?php if (!empty($warnings)): ?>
<div class="maintitle">Warnings (<?= count($warnings) ?>)</div>
<div class="recent-list">
<?php foreach ($warnings as $w): ?>
  <div class="recent-row"><span><?= e($w['reason']) ?><br><small class="muted">by <?= e($w['warned_by_name']) ?></small></span><span class="recent-date"><?= e(time_ago($w['created_at'])) ?></span></div>
<?php endforeach; ?>
</div>
<?php endif; ?>
<?php if (!empty($activeBan)): ?>
<div class="flash-error">⛔ Banned <?= $activeBan['expires_at'] ? 'until ' . e($activeBan['expires_at']) : 'permanently' ?> — <?= e($activeBan['reason']) ?></div>
<?php endif; ?>
<?php if (\RetroBB\Core\Auth::isMod() && \RetroBB\Core\Auth::user()['id'] !== (int) $profile['id']): ?>
<div class="maintitle">Moderate <?= e($profile['username']) ?></div>
<div class="admin-grid">
<form method="post" action="/members/<?= (int) $profile['id'] ?>/warn" class="form">
  <?= \RetroBB\Core\Csrf::field() ?>
  <label>Warn reason<br><input type="text" name="reason" maxlength="500" required style="width:100%"></label><br><br>
  <button class="smallbtn" type="submit">Issue warning</button>
</form>
<form method="post" action="/members/<?= (int) $profile['id'] ?>/ban" class="form" onsubmit="return confirm('Ban this user?')">
  <?= \RetroBB\Core\Csrf::field() ?>
  <label>Ban reason<br><input type="text" name="reason" maxlength="500" required style="width:100%"></label><br><br>
  <label>Days (blank = permanent)<br><input type="number" name="days" min="1" max="3650" style="width:120px"></label><br><br>
  <button class="smallbtn danger" type="submit">Ban user</button>
</form>
</div>
<?php endif; ?>

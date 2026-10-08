<?php /** @var array $profile */ /** @var array $recent */ ?>
<?php $hue = abs(crc32($profile['username'])) % 360; $initial = mb_strtoupper(mb_substr($profile['username'], 0, 1)); ?>
<div class="breadcrumb"><a href="/">Index</a> &raquo; <a href="/members">Members</a> &raquo; <?= e($profile['username']) ?></div>
<div class="profile-card">
  <span class="avatar avatar-lg" style="background:hsl(<?= $hue ?>,45%,55%)"><?= e($initial) ?></span>
  <div class="profile-id">
    <div class="profile-name"><?= e($profile['username']) ?></div>
    <div><span class="group group-<?= e($profile['user_group']) ?>"><?= e($profile['user_group']) ?></span></div>
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

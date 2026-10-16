<?php /** @var array $users */ ?>
<div class="maintitle">Members</div>
<div class="recent-list">
<?php foreach ($users as $u): ?>
  <div class="recent-row"><span><?= avatar_html($u, 'mini') ?> <a href="<?= e(\RetroBB\Core\Slug::memberUrl($u)) ?>"><?= e($u['username']) ?></a> <span class="group group-<?= e($u['user_group']) ?>"><?= e($u['user_group']) ?></span></span><span class="recent-date"><?= (int) $u['posts_count'] ?> posts</span></div>
<?php endforeach; ?>
</div>

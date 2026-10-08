<?php /** @var array $users */ ?>
<div class="maintitle">AdminCP — Users (latest 50)</div>
<?php \RetroBB\Core\View::partial('admin/_nav', ['active' => 'users']); ?>
<div class="recent-list">
<?php foreach ($users as $u): ?>
<div class="recent-row"><span><a href="<?= e(\RetroBB\Core\Slug::memberUrl($u)) ?>"><?= e($u['username']) ?></a> <span class="group group-<?= e($u['user_group']) ?>"><?= e($u['user_group']) ?></span></span>
<span><form method="post" action="/admin/user/<?= (int) $u['id'] ?>/group"><?= \RetroBB\Core\Csrf::field() ?>
<select name="group"><option value="member">member</option><option value="mod" <?= $u['user_group'] === 'mod' ? 'selected' : '' ?>>mod</option><option value="admin" <?= $u['user_group'] === 'admin' ? 'selected' : '' ?>>admin</option></select>
<button class="smallbtn">Set</button></form></span></div>
<?php endforeach; ?>
</div>

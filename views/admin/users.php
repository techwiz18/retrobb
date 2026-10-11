<?php /** @var array $users */ ?>
<div class="maintitle">AdminCP — Users (latest 50)</div>
<?php \RetroBB\Core\View::partial('admin/_nav', ['active' => 'users']); ?>
<?php $me = \RetroBB\Core\Auth::user(); $iAmSuper = \RetroBB\Core\Auth::isSuper(); ?>
<div class="recent-list">
<?php foreach ($users as $u): ?>
<div class="recent-row"><span><a href="<?= e(\RetroBB\Core\Slug::memberUrl($u)) ?>"><?= e($u['username']) ?></a> <span class="group group-<?= e($u['user_group']) ?>"><?= e($u['user_group']) ?></span><?php if (!empty($u['is_super'])): ?> <b title="Unrestricted tier">★super</b><?php endif; ?></span>
<span><?php if ($iAmSuper && (int) $u['id'] !== (int) ($me['id'] ?? 0) && $u['user_group'] === 'admin'): ?><form method="post" action="<?= e(url('/admin/user/' . (int) $u['id'] . '/super')) ?>" style="display:inline"><?= \RetroBB\Core\Csrf::field() ?><button class="smallbtn"><?= !empty($u['is_super']) ? 'Revoke super' : 'Make super' ?></button></form> <?php endif; ?><?php if ($iAmSuper): ?><form method="post" action="<?= e(url('/admin/user/' . (int) $u['id'] . '/group')) ?>" style="display:inline"><?= \RetroBB\Core\Csrf::field() ?>
<select name="group"><option value="member">member</option><option value="mod" <?= $u['user_group'] === 'mod' ? 'selected' : '' ?>>mod</option><option value="admin" <?= $u['user_group'] === 'admin' ? 'selected' : '' ?>>admin</option></select>
<button class="smallbtn">Set</button></form><?php endif; ?></span></div>
<?php endforeach; ?>
</div>

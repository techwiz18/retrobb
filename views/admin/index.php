<?php /** @var array $cats */ /** @var array $users */ /** @var array $settings */ ?>
<div class="maintitle">AdminCP</div>
<?php if (isset($_GET['saved'])): ?><div class="flash-ok">Settings saved.</div><?php endif; ?>
<div class="admin-grid">
<div>
<div class="cat-row">Settings</div>
<form method="post" action="/admin/settings" class="form">
<?= \RetroBB\Core\Csrf::field() ?>
<?php $map = []; foreach ($settings as $s) $map[$s['key']] = $s['value']; ?>
<label>Board name<br><input name="board_name" value="<?= e($map['board_name'] ?? 'RetroBB') ?>"></label><br><br>
<label>Tagline<br><input name="board_tagline" value="<?= e($map['board_tagline'] ?? '') ?>" style="width:100%"></label><br><br>
<label>Default skin<br><select name="default_skin"><option value="classic">Classic</option><option value="midnight" <?= ($map['default_skin'] ?? '') === 'midnight' ? 'selected' : '' ?>>Midnight</option><option value="silver" <?= ($map['default_skin'] ?? '') === 'silver' ? 'selected' : '' ?>>Silver</option></select></label><br><br>
<button class="btn" type="submit">Save</button>
</form>
<div class="cat-row">Add category</div>
<form method="post" action="/admin/add-category" class="form"><?= \RetroBB\Core\Csrf::field() ?><input name="title" placeholder="Category title" required> <button class="btn" type="submit">Add</button></form>
<div class="cat-row">Add forum</div>
<form method="post" action="/admin/add-forum" class="form"><?= \RetroBB\Core\Csrf::field() ?>
<select name="category_id"><?php foreach ($cats as $c): ?><option value="<?= (int) $c['id'] ?>"><?= e($c['title']) ?></option><?php endforeach; ?></select>
<input name="name" placeholder="Forum name" required>
<input name="description" placeholder="Description" style="width:100%">
<button class="btn" type="submit">Add forum</button></form>
</div>
<div>
<div class="cat-row">Structure</div>
<?php foreach ($cats as $c): ?>
<div class="struct-cat"><?= e($c['title']) ?></div>
<div class="recent-list">
<?php foreach ($c['forums'] as $f): ?>
<div class="recent-row"><span><?= e($f['name']) ?></span><span class="recent-date"><?= (int) $f['topics_count'] ?> topics / <?= (int) $f['posts_count'] ?> posts</span></div>
<?php endforeach; ?>
</div>
<?php endforeach; ?>
<div class="cat-row">Users (latest 50)</div>
<div class="recent-list">
<?php foreach ($users as $u): ?>
<div class="recent-row"><span><?= e($u['username']) ?> <span class="group group-<?= e($u['user_group']) ?>"><?= e($u['user_group']) ?></span></span>
<span><form method="post" action="/admin/user/<?= (int) $u['id'] ?>/group"><?= \RetroBB\Core\Csrf::field() ?>
<select name="group"><option value="member">member</option><option value="mod" <?= $u['user_group'] === 'mod' ? 'selected' : '' ?>>mod</option><option value="admin" <?= $u['user_group'] === 'admin' ? 'selected' : '' ?>>admin</option></select>
<button class="smallbtn">Set</button></form></span></div>
<?php endforeach; ?>
</div>
</div>
</div>

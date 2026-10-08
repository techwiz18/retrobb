<?php /** @var array $cats */ ?>
<div class="maintitle">AdminCP — Structure</div>
<?php \RetroBB\Core\View::partial('admin/_nav', ['active' => 'structure']); ?>
<div class="admin-grid">
<div>
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
<div class="cat-row">Order (↑↓ to reorder)</div>
<?php foreach ($cats as $c): ?>
<div class="struct-cat">
  <form method="post" action="/admin/category/<?= (int) $c['id'] ?>/move/up" style="display:inline"><?= \RetroBB\Core\Csrf::field() ?><button class="smallbtn" title="Move up">↑</button></form>
  <form method="post" action="/admin/category/<?= (int) $c['id'] ?>/move/down" style="display:inline"><?= \RetroBB\Core\Csrf::field() ?><button class="smallbtn" title="Move down">↓</button></form>
  <?= e($c['title']) ?>
</div>
<div class="recent-list">
<?php foreach ($c['forums'] as $f): ?>
<div class="recent-row"><span>
  <form method="post" action="/admin/forum/<?= (int) $f['id'] ?>/move/up" style="display:inline"><?= \RetroBB\Core\Csrf::field() ?><button class="smallbtn" title="Move up">↑</button></form>
  <form method="post" action="/admin/forum/<?= (int) $f['id'] ?>/move/down" style="display:inline"><?= \RetroBB\Core\Csrf::field() ?><button class="smallbtn" title="Move down">↓</button></form>
  <?= e($f['name']) ?></span><span class="recent-date"><?= (int) $f['topics_count'] ?> topics / <?= (int) $f['posts_count'] ?> posts</span></div>
<?php endforeach; ?>
</div>
<?php endforeach; ?>
</div>
</div>

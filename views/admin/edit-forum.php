<?php /** @var array $forum */ /** @var array $cats */ /** @var string|null $error */ ?>
<div class="maintitle">AdminCP — Edit forum</div>
<?php \RetroBB\Core\View::partial('admin/_nav', ['active' => 'structure']); ?>
<?php if (!empty($error)): ?><div class="flash-error"><?= e($error) ?></div><?php endif; ?>
<form method="post" action="/admin/forum/<?= (int) $forum['id'] ?>/rename" class="form" style="max-width:560px">
<?= \RetroBB\Core\Csrf::field() ?>
<label>Name<br><input name="name" value="<?= e($forum['name']) ?>" required maxlength="190" style="width:100%"></label><br><br>
<label>Description<br><input name="description" value="<?= e($forum['description']) ?>" maxlength="500" style="width:100%"></label><br><br>
<label>Category<br><select name="category_id"><?php foreach ($cats as $c): ?><option value="<?= (int) $c['id'] ?>" <?= (int) $c['id'] === (int) $forum['category_id'] ? 'selected' : '' ?>><?= e($c['title']) ?></option><?php endforeach; ?></select></label><br><br>
<button class="btn" type="submit">Save</button>
<a class="smallbtn" href="/admin/structure">Back to structure</a>
</form>

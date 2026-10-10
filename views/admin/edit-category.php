<?php /** @var array $cat */ /** @var string|null $error */ ?>
<div class="maintitle">AdminCP — Edit category</div>
<?php \RetroBB\Core\View::partial('admin/_nav', ['active' => 'structure']); ?>
<?php if (!empty($error)): ?><div class="flash-error"><?= e($error) ?></div><?php endif; ?>
<form method="post" action="/admin/category/<?= (int) $cat['id'] ?>/rename" class="form" style="max-width:560px">
<?= \RetroBB\Core\Csrf::field() ?>
<label>Title<br><input name="title" value="<?= e($cat['title']) ?>" required maxlength="190" style="width:100%"></label><br><br>
<button class="btn" type="submit">Save</button>
<a class="smallbtn" href="/admin/structure">Back to structure</a>
</form>

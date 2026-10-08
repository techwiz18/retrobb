<?php /** @var array $topic */ /** @var array $cats */ /** @var string|null $error */ ?>
<div class="breadcrumb"><a href="/">Index</a> &raquo; <a href="<?= e(\RetroBB\Core\Slug::topicUrl($topic)) ?>"><?= e($topic['title']) ?></a> &raquo; Move</div>
<div class="maintitle">Move topic</div>
<?php if (!empty($error)): ?><div class="flash-error"><?= e($error) ?></div><?php endif; ?>
<form method="post" class="form">
  <?= \RetroBB\Core\Csrf::field() ?>
  <label>Destination forum<br>
  <select name="forum_id">
    <?php foreach ($cats as $c): ?>
    <optgroup label="<?= e($c['title']) ?>">
      <?php foreach ($c['forums'] as $f): ?>
      <option value="<?= (int) $f['id'] ?>" <?= (int) $f['id'] === (int) $topic['forum_id'] ? 'disabled' : '' ?>><?= e($f['name']) ?></option>
      <?php endforeach; ?>
    </optgroup>
    <?php endforeach; ?>
  </select></label><br><br>
  <label><input type="checkbox" name="ghost" value="1" checked> Leave a "Moved" ghost link in the old forum</label><br><br>
  <button class="btn" type="submit">Move topic</button>
</form>

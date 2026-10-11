<?php /** @var array $forum */ /** @var string|null $error */ ?>
<div class="breadcrumb"><a href="<?= e(url('/')) ?>">Index</a> &raquo; <a href="<?= e(\RetroBB\Core\Slug::forumUrl($forum)) ?>"><?= e($forum['name']) ?></a> &raquo; New topic</div>
<div class="maintitle">New topic in <?= e($forum['name']) ?></div>
<?php if (!empty($error)): ?><div class="flash-error"><?= e($error) ?></div><?php endif; ?>
<form method="post" class="form">
  <?= \RetroBB\Core\Csrf::field() ?>
  <label>Subject<br><input type="text" name="title" maxlength="120" required style="width:100%"></label><br><br>
  <div class="bbtoolbar">
    <button type="button" data-bb="[b][/b]"><b>B</b></button>
    <button type="button" data-bb="[i][/i]"><i>i</i></button>
    <button type="button" data-bb="[url][/url]">url</button>
    <button type="button" data-bb="[quote][/quote]">quote</button>
    <button type="button" data-bb="[code][/code]">code</button>
  </div>
  <label>Message (BBCode)<br><textarea name="body" rows="10" required style="width:100%"></textarea></label><br><br>
  <button class="btn" type="submit">Post topic</button>
</form>

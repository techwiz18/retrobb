<?php /** @var array $post */ /** @var bool $is_first */ /** @var string|null $error */ ?>
<div class="breadcrumb"><a href="/">Index</a> &raquo; <a href="<?= e(\RetroBB\Core\Slug::topicUrl(['id' => $post['topic_id'], 'title' => $post['topic_slug']])) ?>"><?= e($post['topic_title']) ?></a> &raquo; Edit post</div>
<div class="maintitle">Edit post</div>
<?php if (!empty($error)): ?><div class="flash-error"><?= e($error) ?></div><?php endif; ?>
<form method="post" class="form">
  <?= \RetroBB\Core\Csrf::field() ?>
  <?php if ($is_first): ?>
  <label>Subject (renames the thread)<br><input type="text" name="title" value="<?= e($post['topic_title']) ?>" maxlength="120" required style="width:100%"></label><br><br>
  <?php endif; ?>
  <div class="bbtoolbar">
    <button type="button" data-bb="[b][/b]"><b>B</b></button>
    <button type="button" data-bb="[i][/i]"><i>i</i></button>
    <button type="button" data-bb="[url][/url]">url</button>
    <button type="button" data-bb="[quote][/quote]">quote</button>
    <button type="button" data-bb="[code][/code]">code</button>
  </div>
  <textarea name="body" rows="8" required style="width:100%"><?= e($post['body_bbcode']) ?></textarea><br><br>
  <button class="btn" type="submit">Save edit</button>
</form>

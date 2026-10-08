<?php /** @var array $post */ /** @var string|null $error */ ?>
<div class="breadcrumb"><a href="/">Index</a> &raquo; <a href="<?= e(\RetroBB\Core\Slug::topicUrl(['id' => $post['topic_id'], 'title' => $post['topic_slug']])) ?>"><?= e($post['topic_title']) ?></a> &raquo; Edit post</div>
<div class="maintitle">Edit post</div>
<?php if (!empty($error)): ?><div class="flash-error"><?= e($error) ?></div><?php endif; ?>
<form method="post" class="form">
  <?= \RetroBB\Core\Csrf::field() ?>
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

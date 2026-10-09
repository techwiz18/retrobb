<?php /** @var array $draft */ /** @var string|null $error */ ?>
<div class="breadcrumb"><a href="/">Index</a> &raquo; <a href="/pm">Messages</a> &raquo; <a href="/pm/drafts">Drafts</a> &raquo; <?= (int) $draft['id'] > 0 ? 'Edit' : 'New' ?></div>
<div class="maintitle"><?= (int) $draft['id'] > 0 ? 'Edit draft' : 'New draft' ?></div>
<?php if (!empty($error)): ?><div class="flash-error"><?= e($error) ?></div><?php endif; ?>
<form method="post" class="form" data-compose-guard action="<?= (int) $draft['id'] > 0 ? '/pm/draft/' . (int) $draft['id'] : '/pm/draft/new' ?>">
  <?= \RetroBB\Core\Csrf::field() ?>
  <input type="hidden" name="reply_to" value="<?= (int) ($draft['reply_to_id'] ?? 0) ?>">
  <label>To (username)<br><input name="to" value="<?= e($draft['to_name']) ?>" style="width:100%"></label><br><br>
  <label>Subject<br><input name="subject" value="<?= e($draft['subject']) ?>" maxlength="120" style="width:100%"></label><br><br>
  <label>Message (BBCode)<br><textarea name="body" rows="8" style="width:100%"><?= e($draft['body_bbcode']) ?></textarea></label><br><br>
  <button class="btn" type="submit" name="save" value="1">Save draft</button>
  <button class="btn" type="submit" name="send" value="1">Send</button>
</form>

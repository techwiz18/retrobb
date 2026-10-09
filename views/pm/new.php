<?php /** @var string $to */ /** @var string $subject */ /** @var string $body */ /** @var int $reply_to */ /** @var string|null $error */ ?>
<div class="breadcrumb"><a href="/">Index</a> &raquo; <a href="/pm">Messages</a> &raquo; New</div>
<div class="maintitle">New private message</div>
<?php if (!empty($error)): ?><div class="flash-error"><?= e($error) ?></div><?php endif; ?>
<form method="post" class="form" data-compose-guard>
  <?= \RetroBB\Core\Csrf::field() ?>
  <input type="hidden" name="reply_to" value="<?= (int) $reply_to ?>">
  <label>To (username)<br><input name="to" value="<?= e($to) ?>" required style="width:100%"></label><br><br>
  <label>Subject<br><input name="subject" value="<?= e($subject) ?>" maxlength="120" style="width:100%"></label><br><br>
  <label>Message (BBCode)<br><textarea name="body" rows="8" required style="width:100%"><?= e($body) ?></textarea></label><br><br>
  <button class="btn" type="submit">Send</button>
  <button class="btn" type="submit" name="save" value="1">Save draft</button>
</form>

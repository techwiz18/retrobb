<?php /** @var string $title */ /** @var string $message */ /** @var string $action */ /** @var string $cancel */ /** @var array<string,string> $fields */ ?>
<div class="maintitle"><?= e($title) ?></div>
<div class="flash-error"><?= e($message) ?></div>
<form method="post" action="<?= e(url($action)) ?>" class="form">
<?= \RetroBB\Core\Csrf::field() ?>
<?php foreach ($fields as $name => $value): ?>
<input type="hidden" name="<?= e($name) ?>" value="<?= e($value) ?>">
<?php endforeach; ?>
<button class="btn" type="submit">Try again</button>
<a class="smallbtn" href="<?= e(url($cancel)) ?>">Cancel</a>
</form>

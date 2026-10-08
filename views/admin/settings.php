<?php /** @var array $settings */ ?>
<div class="maintitle">AdminCP — Settings</div>
<?php \RetroBB\Core\View::partial('admin/_nav', ['active' => 'settings']); ?>
<?php if (isset($_GET['saved'])): ?><div class="flash-ok">Settings saved.</div><?php endif; ?>
<?php $map = []; foreach ($settings as $s) $map[$s['key']] = $s['value']; ?>
<form method="post" action="/admin/settings" class="form" style="max-width:560px">
<?= \RetroBB\Core\Csrf::field() ?>
<input type="hidden" name="return" value="settings">
<label>Board name<br><input name="board_name" value="<?= e($map['board_name'] ?? 'RetroBB') ?>" style="width:100%"></label><br><br>
<label>Tagline<br><input name="board_tagline" value="<?= e($map['board_tagline'] ?? '') ?>" style="width:100%"></label><br><br>
<label>Default skin<br><select name="default_skin"><option value="classic">Classic</option><option value="midnight" <?= ($map['default_skin'] ?? '') === 'midnight' ? 'selected' : '' ?>>Midnight</option><option value="silver" <?= ($map['default_skin'] ?? '') === 'silver' ? 'selected' : '' ?>>Silver</option></select></label><br><br>
<label>Flood control (seconds between posts, 0 = off)<br><input type="number" name="flood_seconds" min="0" max="3600" value="<?= e($map['flood_seconds'] ?? '30') ?>" style="width:120px"></label><br><br>
<label>Edit window (minutes, 0 = no limit)<br><input type="number" name="edit_window_mins" min="0" max="3600" value="<?= e($map['edit_window_mins'] ?? '30') ?>" style="width:120px"></label><br><br>
<label>Topics per page<br><input type="number" name="topics_per_page" min="5" max="50" value="<?= e($map['topics_per_page'] ?? '25') ?>" style="width:120px"></label><br><br>
<label>Posts per page<br><input type="number" name="posts_per_page" min="5" max="50" value="<?= e($map['posts_per_page'] ?? '15') ?>" style="width:120px"></label><br><br>
<button class="btn" type="submit">Save settings</button>
</form>

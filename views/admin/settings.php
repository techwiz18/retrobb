<?php /** @var array $settings */ ?>
<div class="maintitle">AdminCP — Settings</div>
<?php \RetroBB\Core\View::partial('admin/_nav', ['active' => 'settings']); ?>
<?php if (isset($_GET['saved'])): ?><div class="flash-ok">Settings saved.</div><?php endif; ?>
<?php $map = []; foreach ($settings as $s) $map[$s['key']] = $s['value']; ?>
<form method="post" action="<?= e(url('/admin/settings')) ?>" class="form" style="max-width:560px">
<?= \RetroBB\Core\Csrf::field() ?>
<input type="hidden" name="return" value="settings">
<label>Board name<br><input name="board_name" value="<?= e($map['board_name'] ?? 'RetroBB') ?>" style="width:100%"></label><br><br>
<label>Tagline<br><input name="board_tagline" value="<?= e($map['board_tagline'] ?? '') ?>" style="width:100%"></label><br><br>
<label>Board URL (canonical address, optional)<br><input name="board_url" value="<?= e($map['board_url'] ?? '') ?>" placeholder="https://…" style="width:100%"></label><br><br>
<label>Flood control (seconds between posts and PMs, 0 = off)<br><input type="number" name="flood_seconds" min="0" max="3600" value="<?= e($map['flood_seconds'] ?? '30') ?>" style="width:120px"></label><br><br>
<label>Edit window (minutes, 0 = no limit)<br><input type="number" name="edit_window_mins" min="0" max="3600" value="<?= e($map['edit_window_mins'] ?? '30') ?>" style="width:120px"></label><br><br>
<label>Delete window (own posts, minutes, 0 = no limit)<br><input type="number" name="delete_window_mins" min="0" max="3600" value="<?= e($map['delete_window_mins'] ?? '30') ?>" style="width:120px"></label><br><br>
<label>Topics per page<br><input type="number" name="topics_per_page" min="5" max="50" value="<?= e($map['topics_per_page'] ?? '25') ?>" style="width:120px"></label><br><br>
<label>Posts per page<br><input type="number" name="posts_per_page" min="5" max="50" value="<?= e($map['posts_per_page'] ?? '15') ?>" style="width:120px"></label><br><br>
<label>Mail sender (password resets; blank = automatic)<br><input type="email" name="mail_from" value="<?= e($map['mail_from'] ?? '') ?>" placeholder="noreply@example.com" style="width:100%"></label><br><br>
<label>Avatar max file size (KB, capped by host limits)<br><input type="number" name="avatar_max_kb" min="10" max="2048" value="<?= e($map['avatar_max_kb'] ?? '100') ?>" style="width:120px"></label><br>
<?php $effKb = (int) round(avatar_limits()['bytes'] / 1024); ?>
<small class="muted">Effective limit after host caps: <?= $effKb ?> KB — this is the number members see.</small><br><br>
<label>Avatar max dimensions (px)<?php if (!avatar_resize_available()): ?> — oversized uploads are rejected (PHP GD unavailable, so no auto-resize)<?php endif; ?><br><input type="number" name="avatar_max_width" min="16" max="2000" value="<?= e($map['avatar_max_width'] ?? '200') ?>" style="width:100px"> × <input type="number" name="avatar_max_height" min="16" max="2000" value="<?= e($map['avatar_max_height'] ?? '200') ?>" style="width:100px"></label><br><br>
<button class="btn" type="submit">Save settings</button>
</form>

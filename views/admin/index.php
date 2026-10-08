<?php /** @var array $cats */ /** @var array $users */ /** @var array $settings */ /** @var array $bans */ /** @var array $modlog */ /** @var int $modtotal */ /** @var int $modpage */ /** @var int $modpages */ /** @var int $openReports */ ?>
<div class="maintitle">AdminCP</div>
<?php if (isset($_GET['saved'])): ?><div class="flash-ok">Settings saved.</div><?php endif; ?>
<?php if ($openReports > 0): ?><div class="flash-error"><a href="/mod/reports"><b><?= $openReports ?> open report<?= $openReports === 1 ? '' : 's' ?></b> in the mod queue</a></div><?php endif; ?>
<div class="actionrow" style="padding-top:10px">
  <small class="muted">Jump to: <a href="#settings">Settings</a> · <a href="#spam">Spam protection</a> · <a href="#structure">Structure</a> · <a href="#bans">Bans</a> · <a href="#users">Users</a> · <a href="#modlog">Mod log</a></small>
</div>
<div class="admin-grid">
<div>
<div class="cat-row" id="settings">Settings</div>
<form method="post" action="/admin/settings" class="form">
<?= \RetroBB\Core\Csrf::field() ?>
<?php $map = []; foreach ($settings as $s) $map[$s['key']] = $s['value']; ?>
<label>Board name<br><input name="board_name" value="<?= e($map['board_name'] ?? 'RetroBB') ?>"></label><br><br>
<label>Tagline<br><input name="board_tagline" value="<?= e($map['board_tagline'] ?? '') ?>" style="width:100%"></label><br><br>
<label>Default skin<br><select name="default_skin"><option value="classic">Classic</option><option value="midnight" <?= ($map['default_skin'] ?? '') === 'midnight' ? 'selected' : '' ?>>Midnight</option><option value="silver" <?= ($map['default_skin'] ?? '') === 'silver' ? 'selected' : '' ?>>Silver</option></select></label><br><br>
<label>Flood control (seconds between posts, 0 = off)<br><input type="number" name="flood_seconds" min="0" max="3600" value="<?= e($map['flood_seconds'] ?? '30') ?>" style="width:100px"></label><br><br>
<label>Edit window (minutes, 0 = no limit)<br><input type="number" name="edit_window_mins" min="0" max="3600" value="<?= e($map['edit_window_mins'] ?? '30') ?>" style="width:100px"></label><br><br>
<button class="btn" type="submit">Save</button>
</form>
<div class="cat-row" id="spam">Spam protection</div>
<form method="post" action="/admin/settings" class="form">
<?= \RetroBB\Core\Csrf::field() ?>
<?php $cp = $map['captcha_provider'] ?? 'honeypot'; ?>
<label>CAPTCHA provider<br><select name="captcha_provider">
<option value="honeypot" <?= $cp === 'honeypot' ? 'selected' : '' ?>>Honeypot only (invisible)</option>
<option value="builtin" <?= $cp === 'builtin' ? 'selected' : '' ?>>Built-in math question</option>
<option value="turnstile" <?= $cp === 'turnstile' ? 'selected' : '' ?>>Cloudflare Turnstile (external)</option>
<option value="hcaptcha" <?= $cp === 'hcaptcha' ? 'selected' : '' ?>>hCaptcha (external)</option>
<option value="recaptcha" <?= $cp === 'recaptcha' ? 'selected' : '' ?>>reCAPTCHA v2 (external)</option>
</select></label><br><br>
<label>Site key (external providers)<br><input name="captcha_sitekey" value="<?= e($map['captcha_sitekey'] ?? '') ?>" style="width:100%"></label><br><br>
<label>Secret key (external providers)<br><input name="captcha_secret" value="<?= e($map['captcha_secret'] ?? '') ?>" style="width:100%"></label><br><br>
<button class="btn" type="submit">Save</button>
</form>
<div class="cat-row">Add category</div>
<form method="post" action="/admin/add-category" class="form"><?= \RetroBB\Core\Csrf::field() ?><input name="title" placeholder="Category title" required> <button class="btn" type="submit">Add</button></form>
<div class="cat-row">Add forum</div>
<form method="post" action="/admin/add-forum" class="form"><?= \RetroBB\Core\Csrf::field() ?>
<select name="category_id"><?php foreach ($cats as $c): ?><option value="<?= (int) $c['id'] ?>"><?= e($c['title']) ?></option><?php endforeach; ?></select>
<input name="name" placeholder="Forum name" required>
<input name="description" placeholder="Description" style="width:100%">
<button class="btn" type="submit">Add forum</button></form>
</div>
<div>
<div class="cat-row" id="structure">Structure (use ↑↓ to reorder)</div>
<?php foreach ($cats as $c): ?>
<div class="struct-cat">
  <form method="post" action="/admin/category/<?= (int) $c['id'] ?>/move/up" style="display:inline"><?= \RetroBB\Core\Csrf::field() ?><button class="smallbtn" title="Move up">↑</button></form>
  <form method="post" action="/admin/category/<?= (int) $c['id'] ?>/move/down" style="display:inline"><?= \RetroBB\Core\Csrf::field() ?><button class="smallbtn" title="Move down">↓</button></form>
  <?= e($c['title']) ?>
</div>
<div class="recent-list">
<?php foreach ($c['forums'] as $f): ?>
<div class="recent-row"><span>
  <form method="post" action="/admin/forum/<?= (int) $f['id'] ?>/move/up" style="display:inline"><?= \RetroBB\Core\Csrf::field() ?><button class="smallbtn" title="Move up">↑</button></form>
  <form method="post" action="/admin/forum/<?= (int) $f['id'] ?>/move/down" style="display:inline"><?= \RetroBB\Core\Csrf::field() ?><button class="smallbtn" title="Move down">↓</button></form>
  <?= e($f['name']) ?></span><span class="recent-date"><?= (int) $f['topics_count'] ?> topics / <?= (int) $f['posts_count'] ?> posts</span></div>
<?php endforeach; ?>
</div>
<?php endforeach; ?>
<div class="cat-row" id="bans">Bans</div>
<div class="recent-list">
<?php $nowTs = time(); ?>
<?php foreach ($bans as $b): ?>
<?php $active = empty($b['lifted_at']) && (empty($b['expires_at']) || strtotime($b['expires_at']) > $nowTs); ?>
<div class="recent-row"><span><b><?= e($b['username']) ?></b> <?= $active ? '<b style="color:#a00">[ACTIVE]</b>' : '<span class="muted">[ended]</span>' ?><br>
<small class="muted"><?= e($b['reason']) ?> · by <?= e($b['banned_by_name']) ?> · <?= $b['expires_at'] ? 'until ' . e($b['expires_at']) : 'permanent' ?></small></span>
<span><?php if ($active): ?><form method="post" action="/admin/unban/<?= (int) $b['id'] ?>"><?= \RetroBB\Core\Csrf::field() ?><button class="smallbtn">Unban</button></form><?php endif; ?></span></div>
<?php endforeach; ?>
<?php if (!$bans): ?><div class="empty">No bans on record.</div><?php endif; ?>
</div>
<div class="cat-row" id="users">Users (latest 50)</div>
<div class="recent-list">
<?php foreach ($users as $u): ?>
<div class="recent-row"><span><?= e($u['username']) ?> <span class="group group-<?= e($u['user_group']) ?>"><?= e($u['user_group']) ?></span></span>
<span><form method="post" action="/admin/user/<?= (int) $u['id'] ?>/group"><?= \RetroBB\Core\Csrf::field() ?>
<select name="group"><option value="member">member</option><option value="mod" <?= $u['user_group'] === 'mod' ? 'selected' : '' ?>>mod</option><option value="admin" <?= $u['user_group'] === 'admin' ? 'selected' : '' ?>>admin</option></select>
<button class="smallbtn">Set</button></form></span></div>
<?php endforeach; ?>
</div>
<div class="cat-row" id="modlog">Mod log (<?= $modtotal ?>)</div>
<div class="recent-list">
<?php foreach ($modlog as $m): ?>
<div class="recent-row"><span><b><?= e($m['actor']) ?></b> <span class="group"><?= e($m['action']) ?></span> <?= e($m['target_type']) ?>#<?= (int) $m['target_id'] ?> <small class="muted"><?= e($m['detail']) ?></small></span><span class="recent-date"><?= e(time_ago($m['created_at'])) ?></span></div>
<?php endforeach; ?>
<?php if (!$modlog): ?><div class="empty">Nothing logged yet.</div><?php endif; ?>
</div>
<?php if ($modpages > 1): ?>
<div class="pagination">Mod log pages:
<?php for ($i = 1; $i <= $modpages; $i++): ?>
  <?php if ($i === $modpage): ?><b><?= $i ?></b>
  <?php else: ?><a href="?modpage=<?= $i ?>#bans"><?= $i ?></a><?php endif; ?>
<?php endfor; ?>
</div>
<?php endif; ?>
</div>
</div>

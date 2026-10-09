<?php /** @var array $settings */ ?>
<div class="maintitle">AdminCP — Features</div>
<?php \RetroBB\Core\View::partial('admin/_nav', ['active' => 'features']); ?>
<?php if (isset($_GET['saved'])): ?><div class="flash-ok">Features saved.</div><?php endif; ?>
<?php if (isset($_GET['fixed-theme'])): ?><div class="flash-error">Default theme wasn't one members may pick, so it was set to an allowed mode instead.</div><?php endif; ?>
<?php $map = []; foreach ($settings as $s) $map[$s['key']] = $s['value']; ?>
<form method="post" action="/admin/features" class="form" style="max-width:560px">
<?= \RetroBB\Core\Csrf::field() ?>
<div class="cat-row">Functionality</div>
<p class="muted">Core posting, registration and moderation are always on — they would break the board. Everything below can be toggled freely.</p>
<label><input type="checkbox" name="feature_alerts" value="1" <?= ($map['feature_alerts'] ?? '1') === '1' ? 'checked' : '' ?>> Alerts (mention / reply / reaction notifications)</label><br>
<small class="muted">Master switch: off also stops mention, reply and reaction notifications. Mention links and reactions keep working.</small><br><br>
<label><input type="checkbox" name="feature_mentions" value="1" <?= ($map['feature_mentions'] ?? '1') === '1' ? 'checked' : '' ?>> @mentions (link @usernames to profiles + notify)</label><br><br>
<label><input type="checkbox" name="feature_reactions" value="1" <?= ($map['feature_reactions'] ?? '1') === '1' ? 'checked' : '' ?>> Reactions (👍 🙏 😄 on posts)</label><br><br>
<label><input type="checkbox" name="feature_pms" value="1" <?= ($map['feature_pms'] ?? '1') === '1' ? 'checked' : '' ?>> Private messages</label><br><br>
<div class="cat-row">Appearance</div>
<label>Default skin<br><select name="default_skin"><option value="classic" <?= ($map['default_skin'] ?? 'classic') === 'classic' ? 'selected' : '' ?>>Classic</option><option value="midnight" <?= ($map['default_skin'] ?? '') === 'midnight' ? 'selected' : '' ?>>Midnight</option><option value="silver" <?= ($map['default_skin'] ?? '') === 'silver' ? 'selected' : '' ?>>Silver</option></select></label><br><br>
<label>Default theme<br><select name="default_theme"><option value="auto" <?= ($map['default_theme'] ?? 'auto') === 'auto' ? 'selected' : '' ?>>Auto (follows device)</option><option value="light" <?= ($map['default_theme'] ?? '') === 'light' ? 'selected' : '' ?>>Light</option><option value="dark" <?= ($map['default_theme'] ?? '') === 'dark' ? 'selected' : '' ?>>Dark</option></select></label><br><br>
<label><input type="checkbox" name="skin_selector" value="1" <?= ($map['skin_selector'] ?? '1') === '1' ? 'checked' : '' ?>> Let members switch skins</label><br><br>
<label>Theme modes members may pick (at least one stays on):</label><br>
<label><input type="checkbox" name="theme_light" value="1" <?= ($map['theme_light'] ?? '1') === '1' ? 'checked' : '' ?>> Light</label>
<label><input type="checkbox" name="theme_dark" value="1" <?= ($map['theme_dark'] ?? '1') === '1' ? 'checked' : '' ?>> Dark</label>
<label><input type="checkbox" name="theme_auto" value="1" <?= ($map['theme_auto'] ?? '1') === '1' ? 'checked' : '' ?>> Auto</label><br><br>
<button class="btn" type="submit">Save features</button>
</form>

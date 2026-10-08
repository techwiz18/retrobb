<?php /** @var array $settings */ ?>
<div class="maintitle">AdminCP — Spam protection</div>
<?php \RetroBB\Core\View::partial('admin/_nav', ['active' => 'spam']); ?>
<?php if (isset($_GET['saved'])): ?><div class="flash-ok">Settings saved.</div><?php endif; ?>
<p class="muted" style="max-width:560px">The invisible honeypot is always on. Pick a visible challenge for registration — built-in math for private boards, or an external service (needs site key + secret) for public forums.</p>
<?php $map = []; foreach ($settings as $s) $map[$s['key']] = $s['value']; $cp = $map['captcha_provider'] ?? 'honeypot'; ?>
<form method="post" action="/admin/settings" class="form" style="max-width:560px">
<?= \RetroBB\Core\Csrf::field() ?>
<input type="hidden" name="return" value="spam">
<input type="hidden" name="board_name" value="<?= e($map['board_name'] ?? 'RetroBB') ?>">
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

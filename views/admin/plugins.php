<?php /** @var array $plugins */ ?>
<div class="maintitle">AdminCP — Plugins</div>
<?php \RetroBB\Core\View::partial('admin/_nav', ['active' => 'plugins']); ?>
<p class="muted">Drop-in extensions from <code>plugins/</code>. Code runs as the site owner — only enable what you trust. Takes effect on the next page load.</p>
<div class="recent-list">
<?php foreach ($plugins as $p): ?>
<div class="recent-row"><span><b><?= e($p['name']) ?></b><?php if ($p['version'] !== ''): ?> <span class="muted"><?= e($p['version']) ?></span><?php endif; ?> <?= $p['enabled'] ? '<b style="color:#080">[on]</b>' : '<span class="muted">[off]</span>' ?><br>
<small class="muted"><?= e($p['description']) ?><?php if ($p['author'] !== ''): ?> · by <?= e($p['author']) ?><?php endif; ?></small></span>
<span><form method="post" action="<?= e(url('/admin/plugin/' . $p['name'])) ?>"><?= \RetroBB\Core\Csrf::field() ?><button class="smallbtn"><?= $p['enabled'] ? 'Disable' : 'Enable' ?></button></form></span></div>
<?php endforeach; ?>
<?php if (!$plugins): ?><div class="empty">No plugins installed.</div><?php endif; ?>
</div>

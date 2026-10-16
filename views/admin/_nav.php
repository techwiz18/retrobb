<?php /** @var string|null $active */ ?>
<?php $isSuperNav = \RetroBB\Core\Auth::isSuper(); ?>
<nav class="adminnav">
  <a href="<?= e(url('/admin')) ?>" class="<?= ($active ?? '') === 'dashboard' ? 'on' : '' ?>">Dashboard</a>
  <?php if ($isSuperNav): ?><a href="<?= e(url('/admin/settings')) ?>" class="<?= ($active ?? '') === 'settings' ? 'on' : '' ?>">Settings</a>
  <a href="<?= e(url('/admin/features')) ?>" class="<?= ($active ?? '') === 'features' ? 'on' : '' ?>">Features</a><?php endif; ?>
  <a href="<?= e(url('/admin/spam')) ?>" class="<?= ($active ?? '') === 'spam' ? 'on' : '' ?>">Spam protection</a>
  <a href="<?= e(url('/admin/structure')) ?>" class="<?= ($active ?? '') === 'structure' ? 'on' : '' ?>">Structure</a>
  <a href="<?= e(url('/admin/bans')) ?>" class="<?= ($active ?? '') === 'bans' ? 'on' : '' ?>">Bans</a>
  <a href="<?= e(url('/admin/users')) ?>" class="<?= ($active ?? '') === 'users' ? 'on' : '' ?>">Users</a>
  <a href="<?= e(url('/admin/modlog')) ?>" class="<?= ($active ?? '') === 'modlog' ? 'on' : '' ?>">Mod log</a>
  <?php if ($isSuperNav): ?><a href="<?= e(url('/admin/import')) ?>" class="<?= ($active ?? '') === 'import' ? 'on' : '' ?>">Import</a><?php endif; ?>
  <a href="<?= e(url('/mod/reports')) ?>" class="<?= ($active ?? '') === 'queue' ? 'on' : '' ?>">Mod queue</a>
</nav>

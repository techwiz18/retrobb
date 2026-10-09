<?php /** @var string|null $active */ ?>
<nav class="adminnav">
  <a href="/admin" class="<?= ($active ?? '') === 'dashboard' ? 'on' : '' ?>">Dashboard</a>
  <a href="/admin/settings" class="<?= ($active ?? '') === 'settings' ? 'on' : '' ?>">Settings</a>
  <a href="/admin/features" class="<?= ($active ?? '') === 'features' ? 'on' : '' ?>">Features</a>
  <a href="/admin/spam" class="<?= ($active ?? '') === 'spam' ? 'on' : '' ?>">Spam protection</a>
  <a href="/admin/structure" class="<?= ($active ?? '') === 'structure' ? 'on' : '' ?>">Structure</a>
  <a href="/admin/bans" class="<?= ($active ?? '') === 'bans' ? 'on' : '' ?>">Bans</a>
  <a href="/admin/users" class="<?= ($active ?? '') === 'users' ? 'on' : '' ?>">Users</a>
  <a href="/admin/modlog" class="<?= ($active ?? '') === 'modlog' ? 'on' : '' ?>">Mod log</a>
  <a href="/mod/reports" class="<?= ($active ?? '') === 'queue' ? 'on' : '' ?>">Mod queue</a>
</nav>

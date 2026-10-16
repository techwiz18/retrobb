<?php /** @var string|null $active */ ?>
<nav class="adminnav">
  <a href="<?= e(url('/mod')) ?>" class="<?= ($active ?? '') === 'dashboard' ? 'on' : '' ?>">ModCP</a>
  <a href="<?= e(url('/mod/reports')) ?>" class="<?= ($active ?? '') === 'queue' ? 'on' : '' ?>">Mod queue</a>
  <a href="<?= e(url('/mod/bans')) ?>" class="<?= ($active ?? '') === 'bans' ? 'on' : '' ?>">Bans</a>
</nav>

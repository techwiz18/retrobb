<?php /** @var int $page */ /** @var int $pages */ /** @var string|null $query */ /** @var string|null $base */ ?>
<?php if ($pages > 1): ?>
<div class="pagination">Pages:
<?php for ($i = 1; $i <= $pages; $i++): ?>
  <?php if ($i === $page): ?><b><?= $i ?></b>
  <?php elseif (!empty($base)): ?><a href="<?= e($base . ($i > 1 ? '/page-' . $i : '')) ?>"><?= $i ?></a>
  <?php else: ?><a href="?page=<?= $i ?><?= e($query ?? '') ?>"><?= $i ?></a><?php endif; ?>
<?php endfor; ?>
</div>
<?php endif; ?>

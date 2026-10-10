<?php /** @var int $page */ /** @var int $pages */ /** @var string|null $query */ ?>
<?php if ($pages > 1): ?>
<div class="pagination">Pages:
<?php for ($i = 1; $i <= $pages; $i++): ?>
  <?php if ($i === $page): ?><b><?= $i ?></b>
  <?php else: ?><a href="?page=<?= $i ?><?= e($query ?? '') ?>"><?= $i ?></a><?php endif; ?>
<?php endfor; ?>
</div>
<?php endif; ?>

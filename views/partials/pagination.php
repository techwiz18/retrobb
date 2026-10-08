<?php /** @var int $page */ /** @var int $pages */ ?>
<?php if ($pages > 1): ?>
<div class="pagination">Pages:
<?php for ($i = 1; $i <= $pages; $i++): ?>
  <?php if ($i === $page): ?><b><?= $i ?></b>
  <?php else: ?><a href="?page=<?= $i ?>"><?= $i ?></a><?php endif; ?>
<?php endfor; ?>
</div>
<?php endif; ?>

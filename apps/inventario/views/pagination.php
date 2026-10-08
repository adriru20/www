<?php // Paginador. Variables: $list['pages'], $list['page'] ?>
<?php if ($list['pages'] > 1): ?>
  <nav class="mt-4" aria-label="Páginas">
    <ul class="pagination justify-content-center flex-wrap">
      <?php for ($i = 1; $i <= $list['pages']; $i++): ?>
        <li class="page-item <?= $i === $list['page'] ? 'active' : '' ?>"><a class="page-link" href="<?= h(inv_url(['p' => $i])) ?>"><?= $i ?></a></li>
      <?php endfor; ?>
    </ul>
  </nav>
<?php endif; ?>

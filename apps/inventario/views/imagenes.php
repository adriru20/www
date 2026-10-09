<?php // Pestaña Imágenes. Variables: $q_img, $list (rows = nombres), $usage ?>
<form method="GET" action="index.php" class="mb-3">
  <input type="hidden" name="tab" value="imagenes">
  <div class="search-container d-flex gap-2">
    <input type="search" name="q_img" class="form-control" placeholder="Buscar nombre de imagen…" value="<?= h($q_img) ?>">
    <button type="submit" class="btn btn-primary" title="Buscar"><i class="fa-solid fa-magnifying-glass"></i></button>
    <a href="?tab=imagenes" class="btn btn-outline-danger">Limpiar</a>
  </div>
</form>

<?php if (!$list['rows']): ?>
  <div class="empty-state">
    <p>No hay imágenes.</p>
    <?php if ($perm['add']): ?><button class="btn btn-primary" type="button" data-bs-toggle="modal" data-bs-target="#modalUploadImg">Subir imágenes</button><?php endif; ?>
  </div>
<?php else: ?>
  <div class="row row-cols-2 row-cols-md-4 row-cols-lg-6 g-3">
    <?php foreach ($list['rows'] as $name): $n = $usage[$name] ?? 0; ?>
      <div class="col">
        <div class="card memento-card no-lift">
          <div class="position-absolute top-0 end-0 p-2" style="z-index:10">
            <span class="badge <?= $n ? 'badge-loc-chip' : 'bg-warning text-dark' ?>"><?= $n ? "usada en $n" : 'sin usar' ?></span>
          </div>
          <img src="<?= h(inv_img_url($name, true)) ?>" data-full="<?= h(inv_img_url($name)) ?>" class="card-img-top memento-img" alt="<?= h($name) ?>" loading="lazy"
               style="cursor:zoom-in" data-lightbox="<?= h(inv_img_url($name)) ?>" data-caption="<?= h($name) ?>">
          <div class="card-body p-2 text-center">
            <div class="small text-truncate mb-2" title="<?= h($name) ?>"><?= h($name) ?></div>
            <div class="d-flex gap-1 justify-content-center">
              <?php if ($perm['edit']): ?><button type="button" class="btn btn-sm btn-outline-primary" data-rename-img="<?= h($name) ?>" title="Renombrar"><i class="fa-solid fa-pen"></i></button><?php endif; ?>
              <?php if ($perm['delete']): ?><button type="button" class="btn btn-sm btn-outline-danger" title="Eliminar"
                      data-confirm="delete_img" data-name="<?= h($name) ?>" data-label="la imagen «<?= h($name) ?>»"
                      data-warn="<?= $n ? "Está usada en $n elemento(s): se quedarán sin foto." : '' ?>"><i class="fa-solid fa-trash"></i></button><?php endif; ?>
            </div>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
  <?php include __DIR__ . '/pagination.php'; ?>
<?php endif; ?>

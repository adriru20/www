<?php // Pestaña Imágenes. Variables: $q_img, $ver_img, $img_stats, $list (rows = nombres), $usage ?>
<form method="GET" action="index.php" class="mb-3">
  <input type="hidden" name="tab" value="imagenes">
  <?php if ($ver_img): ?><input type="hidden" name="ver_img" value="<?= h($ver_img) ?>"><?php endif; ?>
  <div class="search-container d-flex gap-2">
    <input type="search" name="q_img" class="form-control" placeholder="Buscar nombre de imagen…" value="<?= h($q_img) ?>">
    <button type="submit" class="btn btn-primary" title="Buscar"><i class="fa-solid fa-magnifying-glass"></i></button>
    <a href="?tab=imagenes" class="btn btn-outline-danger">Limpiar</a>
  </div>
</form>

<?php if ($img_stats['unused'] === null): ?>
  <div class="alert alert-warning small">No se pudo comprobar qué imágenes se usan; el filtro y el borrado de sin usar están desactivados.</div>
<?php else: ?>
  <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
    <div class="btn-group" role="group" aria-label="Filtrar imágenes">
      <a href="<?= h(inv_url(['ver_img' => null, 'p' => null])) ?>" class="btn btn-sm btn-outline-secondary <?= $ver_img === '' ? 'active' : '' ?>">Todas <span class="badge bg-secondary"><?= (int) $img_stats['total'] ?></span></a>
      <a href="<?= h(inv_url(['ver_img' => 'sin_usar', 'p' => null])) ?>" class="btn btn-sm btn-outline-secondary <?= $ver_img === 'sin_usar' ? 'active' : '' ?>">Sin usar <span class="badge bg-warning text-dark"><?= (int) $img_stats['unused'] ?></span></a>
      <a href="<?= h(inv_url(['ver_img' => 'usadas', 'p' => null])) ?>" class="btn btn-sm btn-outline-secondary <?= $ver_img === 'usadas' ? 'active' : '' ?>">Usadas <span class="badge bg-secondary"><?= (int) $img_stats['total'] - (int) $img_stats['unused'] ?></span></a>
    </div>
    <?php if ($img_stats['unused'] > 0): ?>
      <span class="small text-muted"><?= (int) $img_stats['unused'] ?> sin usar ocupan <strong><?= h(inv_format_bytes($img_stats['unused_size'])) ?></strong></span>
      <?php if ($perm['delete']): ?>
        <button type="button" class="btn btn-sm btn-outline-danger ms-sm-auto" data-confirm="delete_unused_img"
                data-label="las <?= (int) $img_stats['unused'] ?> imágenes sin usar (<?= h(inv_format_bytes($img_stats['unused_size'])) ?>)"
                data-warn="Se borran del servidor y no se pueden recuperar. Si acabas de subir alguna para usarla más tarde, también se borrará.">
          <i class="fa-solid fa-broom"></i> Eliminar todas las sin usar
        </button>
      <?php endif; ?>
    <?php endif; ?>
  </div>
<?php endif; ?>

<?php if (!$list['rows']): ?>
  <div class="empty-state">
    <p><?= $ver_img === 'sin_usar' && $q_img === '' ? 'No hay imágenes sin usar. 🎉' : ($ver_img === 'usadas' && $q_img === '' ? 'Ninguna imagen está en uso.' : ($q_img !== '' || $ver_img ? 'Ninguna imagen coincide con el filtro.' : 'No hay imágenes.')) ?></p>
    <?php if ($perm['add'] && !$ver_img && $q_img === ''): ?><button class="btn btn-primary" type="button" data-bs-toggle="modal" data-bs-target="#modalUploadImg">Subir imágenes</button><?php endif; ?>
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

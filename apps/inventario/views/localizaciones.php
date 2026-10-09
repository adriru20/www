<?php // Pestaña Localizaciones. Variables: $f, $list, $opts, $locUsage ?>
<form method="GET" action="index.php" class="mb-3">
  <input type="hidden" name="tab" value="localizaciones">
  <div class="search-container d-flex gap-2">
    <input type="search" name="q_loc" class="form-control" placeholder="Buscar nombre, contenido o categoría…" value="<?= h($f['q']) ?>">
    <button type="submit" class="btn btn-primary" title="Buscar"><i class="fa-solid fa-magnifying-glass"></i></button>
    <button class="btn btn-outline-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#filtrosLoc" title="Filtros"><i class="fa-solid fa-sliders"></i></button>
  </div>
  <div class="collapse <?= ($f['cat'] !== '' || $f['sort'] !== 'cat_nombre') ? 'show' : '' ?> mt-2" id="filtrosLoc">
    <div class="toolbar">
      <div class="row g-2 align-items-end">
        <div class="col-12 col-md-4">
          <label class="form-label small text-muted mb-1">Categoría</label>
          <select name="f_cat_loc" class="form-select form-select-sm">
            <option value="">Todas</option>
            <?php foreach ($opts['loc_cats'] as $c): ?><option value="<?= h($c) ?>" <?= $f['cat'] === $c ? 'selected' : '' ?>><?= h($c) ?></option><?php endforeach; ?>
          </select>
        </div>
        <div class="col-12 col-md-4">
          <label class="form-label small text-muted mb-1">Ordenar</label>
          <select name="sort_loc" class="form-select form-select-sm">
            <option value="cat_nombre" <?= $f['sort'] === 'cat_nombre' ? 'selected' : '' ?>>📁 Categoría y nombre</option>
            <option value="nombre_asc" <?= $f['sort'] === 'nombre_asc' ? 'selected' : '' ?>>🔤 Nombre (A-Z)</option>
            <option value="nombre_desc" <?= $f['sort'] === 'nombre_desc' ? 'selected' : '' ?>>🔤 Nombre (Z-A)</option>
            <option value="newest" <?= $f['sort'] === 'newest' ? 'selected' : '' ?>>🕒 Recientes</option>
          </select>
        </div>
        <div class="col-12 col-md-4 d-grid"><a href="?tab=localizaciones" class="btn btn-outline-danger btn-sm">Limpiar</a></div>
      </div>
    </div>
  </div>
</form>

<?php if (!$list['rows']): ?>
  <div class="empty-state">
    <p>No se han encontrado localizaciones.</p>
    <?php if ($perm['add']): ?><button class="btn btn-primary" type="button" data-inv-new="loc">Añadir la primera</button><?php endif; ?>
  </div>
<?php else: ?>
  <div class="row row-cols-2 row-cols-md-4 row-cols-lg-6 g-3">
    <?php foreach ($list['rows'] as $l):
      $thumb = inv_img_url($l['foto_http'], true);
      $n = $locUsage[$l['nombre']] ?? 0;
    ?>
      <div class="col">
        <div class="card memento-card" role="button" tabindex="0" data-edit="loc" data-id="<?= (int) $l['id'] ?>" aria-label="Ver qué hay en <?= h($l['nombre']) ?>">
          <div class="position-absolute top-0 end-0 p-2" style="z-index:10">
            <span class="badge badge-loc-chip"><?= $n ?> <?= $n === 1 ? 'objeto' : 'objetos' ?></span>
          </div>
          <img src="<?= h($thumb ?: INV_FALLBACK_SVG) ?>" data-full="<?= h(inv_img_url($l['foto_http'])) ?>" class="card-img-top memento-img" alt="" loading="lazy">
          <div class="card-body p-2 text-center">
            <div class="card-title text-truncate mb-1"><?= inv_emoji($l['categoria']) ?> <?= h($l['nombre']) ?></div>
            <small class="text-muted"><?= h($l['categoria']) ?></small>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
  <?php include __DIR__ . '/pagination.php'; ?>
<?php endif; ?>

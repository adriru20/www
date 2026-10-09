<?php // Pestaña Colección. Variables: $f, $list, $opts, $locMap ?>
<form method="GET" action="index.php" class="mb-3">
  <input type="hidden" name="tab" value="objetos">
  <div class="search-container d-flex gap-2">
    <input type="search" name="q" class="form-control" placeholder="Buscar por título, categoría, localización…" value="<?= h($f['q']) ?>">
    <button type="submit" class="btn btn-primary" title="Buscar"><i class="fa-solid fa-magnifying-glass"></i></button>
    <button class="btn btn-outline-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#filtros" title="Filtros"><i class="fa-solid fa-sliders"></i></button>
  </div>
  <?php $filtrosAbiertos = $f['f_loc'] || $f['f_tipo'] !== '' || $f['f_cat'] !== '' || $f['ver'] !== '' || $f['sort'] !== 'newest'; ?>
  <div class="collapse <?= $filtrosAbiertos ? 'show' : '' ?> mt-2" id="filtros">
    <div class="toolbar">
      <div class="row g-2 align-items-end">
        <div class="col-12 col-md-3">
          <label class="form-label small text-muted mb-1">Localizaciones <small>(Ctrl+clic para varias)</small></label>
          <select name="f_loc[]" class="form-select form-select-sm" multiple size="4">
            <?php foreach ($opts['locs'] as $loc): ?>
              <option value="<?= h($loc) ?>" <?= in_array($loc, $f['f_loc'], true) ? 'selected' : '' ?>><?= h($loc) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-6 col-md-2">
          <label class="form-label small text-muted mb-1">Tipo</label>
          <select name="f_tipo" class="form-select form-select-sm">
            <option value="">Todos</option>
            <?php foreach ($opts['tipos'] as $t): ?><option value="<?= h($t) ?>" <?= $f['f_tipo'] === $t ? 'selected' : '' ?>><?= h($t) ?></option><?php endforeach; ?>
          </select>
        </div>
        <div class="col-6 col-md-2">
          <label class="form-label small text-muted mb-1">Categoría</label>
          <select name="f_t_obj" class="form-select form-select-sm">
            <option value="">Todas</option>
            <?php foreach ($opts['cats'] as $c): ?><option value="<?= h($c) ?>" <?= $f['f_cat'] === $c ? 'selected' : '' ?>><?= h($c) ?></option><?php endforeach; ?>
          </select>
        </div>
        <div class="col-6 col-md-2">
          <label class="form-label small text-muted mb-1">Mostrar</label>
          <select name="ver" class="form-select form-select-sm">
            <option value="">Todo</option>
            <option value="venta" <?= $f['ver'] === 'venta' ? 'selected' : '' ?>>💶 A la venta</option>
            <option value="sin_foto" <?= $f['ver'] === 'sin_foto' ? 'selected' : '' ?>>🖼️ Sin foto</option>
            <option value="sin_loc" <?= $f['ver'] === 'sin_loc' ? 'selected' : '' ?>>📍 Sin localización</option>
          </select>
        </div>
        <div class="col-6 col-md-2">
          <label class="form-label small text-muted mb-1">Ordenar</label>
          <select name="sort" class="form-select form-select-sm">
            <option value="newest" <?= $f['sort'] === 'newest' ? 'selected' : '' ?>>🕒 Recientes</option>
            <option value="nombre_asc" <?= $f['sort'] === 'nombre_asc' ? 'selected' : '' ?>>🔤 Nombre (A-Z)</option>
            <option value="tipo_nombre" <?= $f['sort'] === 'tipo_nombre' ? 'selected' : '' ?>>📁 Tipo y nombre</option>
            <option value="precio_desc" <?= $f['sort'] === 'precio_desc' ? 'selected' : '' ?>>💶 Precio (mayor)</option>
          </select>
        </div>
        <div class="col-12 col-md-1 d-grid"><a href="?tab=objetos" class="btn btn-outline-danger btn-sm">Limpiar</a></div>
      </div>
    </div>
  </div>
</form>

<?php if (!$list['rows']): ?>
  <div class="empty-state">
    <p>No se han encontrado objetos.</p>
    <button class="btn btn-primary" type="button" data-inv-new="obj">Añadir el primero</button>
  </div>
<?php else: ?>
  <div class="row row-cols-2 row-cols-md-4 row-cols-lg-6 g-3">
    <?php foreach ($list['rows'] as $o):
      $titulo = $o['objeto'] !== '' && $o['objeto'] !== null ? $o['objeto'] : 'Sin título';
      $thumb = inv_img_url($o['portada_http'], true);
      $precio = (float) $o['precio_de_venta'];
    ?>
      <div class="col">
        <div class="card memento-card" role="button" tabindex="0" data-edit="obj" data-id="<?= (int) $o['id'] ?>" aria-label="Editar <?= h($titulo) ?>">
          <div class="position-absolute top-0 start-0 p-2 d-flex flex-column gap-1 align-items-start" style="z-index:10">
            <?php if ((int) $o['cantidad'] > 1): ?><span class="badge bg-primary">x<?= (int) $o['cantidad'] ?></span><?php endif; ?>
            <?php if ($precio > 0): ?><span class="badge bg-success"><?= number_format($precio, 2, ',', '.') ?> €</span><?php endif; ?>
          </div>
          <div class="position-absolute top-0 end-0 p-2 d-flex flex-column gap-1 align-items-end" style="z-index:10">
            <?php foreach (inv_split_list($o['localizacion']) as $l): ?>
              <span class="badge badge-loc-chip"><?= inv_emoji($locMap[$l] ?? '') ?> <?= h($l) ?></span>
            <?php endforeach; ?>
          </div>
          <img src="<?= h($thumb ?: INV_FALLBACK_SVG) ?>" data-full="<?= h(inv_img_url($o['portada_http'])) ?>" class="card-img-top memento-img" alt="" loading="lazy">
          <div class="card-body p-2 text-center">
            <div class="card-title text-truncate mb-1" title="<?= h($titulo) ?>"><?= h($titulo) ?></div>
            <small class="text-muted"><?= h($o['tipo']) ?><?= $o['tipo_de_objeto'] ? ' · ' . h($o['tipo_de_objeto']) : '' ?></small>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
  <?php include __DIR__ . '/pagination.php'; ?>
<?php endif; ?>

<?php // Cabecera de la app: título, botón de añadir, enlaces y pestañas. Variables: $tab, $count ?>
<div class="page-header">
  <h1 class="h3">📦 Inventario <span class="count-pill"><?= (int) $count ?></span></h1>
  <div class="d-flex gap-2 flex-wrap">
    <?php if ($tab === 'objetos' || $tab === 'resumen'): ?>
      <button class="btn btn-primary" type="button" data-inv-new="obj"><i class="fa-solid fa-plus"></i> Añadir objeto</button>
    <?php elseif ($tab === 'localizaciones'): ?>
      <button class="btn btn-primary" type="button" data-inv-new="loc"><i class="fa-solid fa-plus"></i> Añadir localización</button>
    <?php elseif ($tab === 'imagenes'): ?>
      <button class="btn btn-primary" type="button" data-bs-toggle="modal" data-bs-target="#modalUploadImg"><i class="fa-solid fa-upload"></i> Subir imágenes</button>
    <?php endif; ?>
    <a href="csv.php" class="btn btn-outline-success" title="Copias de seguridad e importación"><i class="fa-solid fa-floppy-disk"></i> Backups</a>
  </div>
</div>

<?php foreach ($flash as $aviso): ?>
  <div class="alert alert-<?= h($aviso['type']) ?> alert-dismissible fade show" role="alert">
    <?= h($aviso['msg']) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
  </div>
<?php endforeach; ?>

<ul class="nav nav-tabs inv-tabs mb-3">
  <?php foreach (['resumen' => ['Resumen', 'fa-chart-pie'], 'objetos' => ['Colección', 'fa-boxes-stacked'],
                  'localizaciones' => ['Localizaciones', 'fa-location-dot'], 'imagenes' => ['Imágenes', 'fa-images']] as $key => [$label, $icon]): ?>
    <li class="nav-item">
      <a class="nav-link <?= $tab === $key ? 'active' : '' ?>" href="?tab=<?= $key ?>"><i class="fa-solid <?= $icon ?>"></i> <?= $label ?></a>
    </li>
  <?php endforeach; ?>
</ul>

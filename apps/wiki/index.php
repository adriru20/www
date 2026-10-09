<?php
require_once __DIR__ . '/../../backend/config/bootstrap.php';
require_app('wiki');
require_once __DIR__ . '/../../backend/lib/onedrive.php';
$od = od_status();                            // ¿está conectada la sincronización con OneDrive?
$page_title = 'Wiki';
$page_styles = ['/styles/wiki.css'];
$page_scripts = ['/js/vendor/marked.min.js', '/js/vendor/purify.min.js', '/apps/wiki/wiki.js'];
?>
<!DOCTYPE html>
<html lang="es" data-bs-theme="dark">
<?php include __DIR__ . '/../../backend/config/ini.php'; ?>
<body>
  <?php include __DIR__ . '/../../frontend/menu.php'; ?>
  <div id="app">
    <aside id="sidebar" data-sync="<?= $od['connected'] ? '1' : '0' ?>" data-csrf="<?= e(csrf_token()) ?>">
      <h3>Notas
        <?php if ($od['connected']): ?><button type="button" id="sync-btn" class="sync-btn" title="Actualizar desde OneDrive" aria-label="Actualizar desde OneDrive"><i class="fa-solid fa-rotate"></i></button><?php endif; ?>
      </h3>
      <?php if ($od['connected']): ?><div id="sync-status" class="sync-status" role="status" aria-live="polite"></div><?php endif; ?>
      <input type="search" id="search-input" placeholder="Buscar nota..." autocomplete="off">
      <ul id="file-list"></ul>
    </aside>
    <main id="content">
      <div id="viewer"><p class="text-muted">Selecciona una nota de la lista.</p></div>
    </main>
  </div>
  <?php include __DIR__ . '/../../frontend/footer.php'; ?>
</body>
</html>

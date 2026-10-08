<?php
require_once __DIR__ . '/../../backend/config/bootstrap.php';
require_login();
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
    <aside id="sidebar">
      <h3>Notas</h3>
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

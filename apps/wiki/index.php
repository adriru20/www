<!DOCTYPE html>
<html lang="es" data-bs-theme="dark">
<?php include "../../backend/config/ini.php"; ?>
<body>
  <?php include "{$src}frontend/menu.php"; ?>
  <div id="app">
    <aside id="sidebar">
      <h3>Notas</h3>
      <input type="text" id="search-input" placeholder="Buscar nota...">
      <ul id="file-list"></ul>
    </aside>
    <main id="content">
      <div id="viewer">Selecciona una nota de la izquierda</div>
    </main>
  </div>
  <?php include "{$src}frontend/footer.php"; ?>
  <script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>
</body>
</html>
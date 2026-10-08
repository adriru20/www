<?php
require_once __DIR__ . '/../../backend/config/bootstrap.php';
require_login();
$page_title = 'Parking';
$page_scripts = ['/apps/parking/parking.js'];
?>
<!DOCTYPE html>
<html lang="es" data-bs-theme="dark">
<?php include __DIR__ . '/../../backend/config/ini.php'; ?>
<body>
  <?php include __DIR__ . '/../../frontend/menu.php'; ?>
  <main class="page-container">
    <div class="page-header">
      <h1 class="h3">🚗 ¿Dónde he aparcado?</h1>
    </div>
    <p class="text-muted">Guarda la ubicación de tu coche y vuelve a él fácilmente. Se guarda solo en este dispositivo.</p>
    <div class="parking-actions">
      <button class="btn btn-primary btn-lg" onclick="guardar()"><i class="fa-solid fa-location-dot"></i> Guardar ubicación</button>
      <button class="btn btn-success btn-lg" onclick="mostrar()"><i class="fa-solid fa-car-side"></i> Ver coche</button>
      <button class="btn btn-outline-danger btn-lg" onclick="borrar()"><i class="fa-solid fa-trash"></i> Borrar</button>
    </div>
    <div id="info" class="mt-3" aria-live="polite"></div>
  </main>
  <?php include __DIR__ . '/../../frontend/footer.php'; ?>
</body>
</html>

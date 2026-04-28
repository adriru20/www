<!DOCTYPE html>
<html lang="es" data-bs-theme="dark">
<?php include "../../backend/config/ini.php"; ?>
<body>
  <?php include "{$src}frontend/menu.php"; ?>
  <nav class="container card-body">
    <h1 class="h3 mb-3">🚗 ¿Dónde he aparcado?</h1>
    <p class="text-muted">Guarda la ubicación de tu coche y vuelve a él fácilmente.</p>
    <button class="btn btn-primary btn-lg" onclick="guardar()">Guardar ubicación</button>
    <button class="btn btn-success btn-lg" onclick="mostrar()">Ver coche</button>
    <button class="btn btn-outline-danger btn-lg" onclick="borrar()">Borrar</button>
    <div id="info" class="small"></div>
  </nav>
  <?php include "{$src}frontend/footer.php"; ?>
</body>
</html>
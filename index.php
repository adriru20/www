<?php
require_once __DIR__ . '/backend/config/bootstrap.php';
require_login();
save_ip();
$page_title = 'Inicio';
?>
<!DOCTYPE html>
<html lang="es" data-bs-theme="dark">
<?php include __DIR__ . '/backend/config/ini.php'; ?>
<body>
  <?php include __DIR__ . '/frontend/menu.php'; ?>
  <main class="page-container">
    <section class="hero">
      <img src="/img/Astronauta-flotador.png" alt="Astronauta flotando en un flotador rosa">
      <h1>Hola, <span class="text-gradient"><?= htmlspecialchars($_SESSION['username'] ?? '') ?></span></h1>
      <p>¿A dónde vamos hoy?</p>
    </section>

    <section class="app-grid" aria-label="Aplicaciones">
      <a class="app-card" href="/apps/wiki/">
        <span class="app-icon"><i class="fa-solid fa-book-open"></i></span>
        <h2>Wiki</h2><p>Mis notas y apuntes de Obsidian.</p>
      </a>
      <a class="app-card" href="/apps/parking/">
        <span class="app-icon"><i class="fa-solid fa-car"></i></span>
        <h2>Parking</h2><p>Guarda dónde has aparcado el coche.</p>
      </a>
      <a class="app-card" href="/apps/giftlist/">
        <span class="app-icon"><i class="fa-solid fa-gift"></i></span>
        <h2>Gift list</h2><p>Listas de regalos de la familia.</p>
      </a>
      <a class="app-card" href="/apps/inventario/">
        <span class="app-icon"><i class="fa-solid fa-boxes-stacked"></i></span>
        <h2>Inventario</h2><p>Objetos, juegos y dónde está cada cosa.</p>
      </a>
    </section>
  </main>
  <?php include __DIR__ . '/frontend/footer.php'; ?>
</body>
</html>

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

    <?php $apps = allowed_apps(); ?>
    <?php if ($apps): ?>
    <section class="app-grid" aria-label="Aplicaciones">
      <?php foreach ($apps as $app): ?>
        <a class="app-card" href="<?= $app['href'] ?>">
          <span class="app-icon"><i class="fa-solid <?= $app['icon'] ?>"></i></span>
          <h2><?= htmlspecialchars($app['label']) ?></h2><p><?= htmlspecialchars($app['desc']) ?></p>
        </a>
      <?php endforeach; ?>
      <?php if (is_admin()): ?>
        <a class="app-card" href="/src/admin/">
          <span class="app-icon"><i class="fa-solid fa-users-gear"></i></span>
          <h2>Usuarios</h2><p>Roles y secciones de cada persona.</p>
        </a>
      <?php endif; ?>
    </section>
    <?php else: ?>
    <div class="empty-state"><p>Todavía no tienes acceso a ninguna sección.<br>Pídeselo al administrador.</p></div>
    <?php endif; ?>
  </main>
  <?php include __DIR__ . '/frontend/footer.php'; ?>
</body>
</html>

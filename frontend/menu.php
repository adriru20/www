<?php
// Barra de navegación común. Requiere bootstrap.php (sesión).
$nav_items = [['/', 'Inicio', 'fa-house']];
foreach (allowed_apps() as $key => $app) $nav_items[] = [$app['href'], $app['label'], $app['icon']];
if (is_logged_in() && is_admin()) { $nav_items[] = ['/src/admin/', 'Usuarios', 'fa-users-gear']; $nav_items[] = ['/src/onedrive/', 'OneDrive', 'fa-cloud']; }
if (is_logged_in()) $nav_items[] = ['/src/cuenta/', 'Mi cuenta', 'fa-user-gear'];
$current_path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$is_active = fn(string $href) => $href === '/' ? $current_path === '/' || $current_path === '/index.php'
                                                : str_starts_with($current_path, $href);
?>
<header class="site-header">
  <nav class="navbar navbar-expand-lg app-navbar">
    <div class="container-xxl">
      <a class="navbar-brand" href="/">
        <img src="/img/Astronauta-flotador.png" alt="" width="44" height="44">
        <span>adriru</span>
      </a>
      <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav"
              aria-controls="mainNav" aria-expanded="false" aria-label="Abrir menú">
        <span class="navbar-toggler-icon"></span>
      </button>
      <div class="collapse navbar-collapse" id="mainNav">
        <ul class="navbar-nav ms-auto align-items-lg-center gap-lg-1">
          <?php foreach ($nav_items as [$href, $label, $icon]): ?>
            <li class="nav-item">
              <a class="nav-link <?= $is_active($href) ? 'active' : '' ?>" href="<?= $href ?>"
                 <?= $is_active($href) ? 'aria-current="page"' : '' ?>>
                <i class="fa-solid <?= $icon ?>"></i> <?= $label ?>
              </a>
            </li>
          <?php endforeach; ?>
          <li class="nav-item ms-lg-2">
            <?php if (is_logged_in()): ?>
              <a class="nav-link nav-cta" href="/src/login/logout.php">
                <i class="fa-solid fa-right-from-bracket"></i> Salir
              </a>
            <?php else: ?>
              <a class="nav-link nav-cta" href="/src/login/">
                <i class="fa-solid fa-right-to-bracket"></i> Entrar
              </a>
            <?php endif; ?>
          </li>
        </ul>
      </div>
    </div>
  </nav>
</header>

<?php
// Barra de navegación común. Requiere bootstrap.php (sesión).
// Escritorio: menú arriba. Móvil: arriba solo el logo y las secciones van en una barra inferior (alcance del pulgar).
$nav_items = [['/', 'Inicio', 'fa-house']];
foreach (allowed_apps() as $key => $app) $nav_items[] = [$app['href'], $app['label'], $app['icon']];
$logged = is_logged_in();
// Desplegable «Mi cuenta»: su página, administración (solo admin) y Salir
$account_items = [['/src/cuenta/', 'Mi cuenta', 'fa-user-gear']];
if ($logged && is_admin()) { $account_items[] = ['/src/admin/', 'Usuarios', 'fa-users-gear']; $account_items[] = ['/src/onedrive/', 'OneDrive', 'fa-cloud']; }
$current_path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$is_active = fn(string $href) => $href === '/' ? $current_path === '/' || $current_path === '/index.php'
                                                : str_starts_with($current_path, $href);
$account_active = false;
foreach ($account_items as [$href]) if ($is_active($href)) $account_active = true;

// Contenido del desplegable (el mismo en escritorio y móvil)
$account_menu = function (string $id) use ($account_items, $is_active) { ?>
  <ul class="dropdown-menu dropdown-menu-end account-menu" aria-labelledby="<?= $id ?>">
    <?php foreach ($account_items as [$href, $label, $icon]): ?>
      <li><a class="dropdown-item <?= $is_active($href) ? 'active' : '' ?>" href="<?= $href ?>" <?= $is_active($href) ? 'aria-current="page"' : '' ?>><i class="fa-solid <?= $icon ?>"></i> <?= $label ?></a></li>
    <?php endforeach; ?>
    <li><hr class="dropdown-divider"></li>
    <li class="px-2 pb-1"><a class="nav-link nav-cta text-center" href="/src/login/logout.php"><i class="fa-solid fa-right-from-bracket"></i> Salir</a></li>
  </ul>
<?php };
?>
<header class="site-header">
  <nav class="navbar navbar-expand-lg app-navbar">
    <div class="container-xxl">
      <a class="navbar-brand" href="/">
        <img src="/img/Astronauta-flotador.png" alt="" width="44" height="44">
        <span>adriru</span>
      </a>
      <!-- Escritorio -->
      <div class="d-none d-lg-block">
        <ul class="navbar-nav ms-auto flex-row align-items-center gap-1">
          <?php foreach ($nav_items as [$href, $label, $icon]): ?>
            <li class="nav-item">
              <a class="nav-link <?= $is_active($href) ? 'active' : '' ?>" href="<?= $href ?>"
                 <?= $is_active($href) ? 'aria-current="page"' : '' ?>>
                <i class="fa-solid <?= $icon ?>"></i> <?= $label ?>
              </a>
            </li>
          <?php endforeach; ?>
          <li class="nav-item ms-2">
            <?php if ($logged): ?>
              <div class="dropdown">
                <button class="nav-link dropdown-toggle <?= $account_active ? 'active' : '' ?>" type="button" id="accountBtnDesktop" data-bs-toggle="dropdown" aria-expanded="false">
                  <i class="fa-solid fa-user-gear"></i> Mi cuenta
                </button>
                <?php $account_menu('accountBtnDesktop'); ?>
              </div>
            <?php else: ?>
              <a class="nav-link nav-cta" href="/src/login/"><i class="fa-solid fa-right-to-bracket"></i> Entrar</a>
            <?php endif; ?>
          </li>
        </ul>
      </div>
    </div>
  </nav>
</header>

<!-- Móvil: barra inferior -->
<nav class="bottom-nav d-lg-none" aria-label="Menú principal">
  <?php foreach ($nav_items as [$href, $label, $icon]): ?>
    <a class="bottom-nav-item <?= $is_active($href) ? 'active' : '' ?>" href="<?= $href ?>" <?= $is_active($href) ? 'aria-current="page"' : '' ?>>
      <i class="fa-solid <?= $icon ?>"></i><span><?= $label ?></span>
    </a>
  <?php endforeach; ?>
  <?php if ($logged): ?>
    <div class="dropup bottom-nav-drop">
      <button class="bottom-nav-item <?= $account_active ? 'active' : '' ?>" type="button" id="accountBtnMobile" data-bs-toggle="dropdown" data-bs-offset="0,10" aria-expanded="false">
        <i class="fa-solid fa-user-gear"></i><span>Mi cuenta</span>
      </button>
      <?php $account_menu('accountBtnMobile'); ?>
    </div>
  <?php else: ?>
    <a class="bottom-nav-item bottom-nav-cta" href="/src/login/"><i class="fa-solid fa-right-to-bracket"></i><span>Entrar</span></a>
  <?php endif; ?>
</nav>

<?php
// Autenticación y permisos centralizados: todas las páginas usan estas funciones.
require_once __DIR__ . '/apps.php';

function is_logged_in(): bool {
  return !empty($_SESSION['user_id']);
}

// Acepta solo rutas internas ("/apps/wiki/"), nunca URLs externas (evita open redirect)
function safe_next(?string $next): string {
  $next = trim((string) $next);
  if ($next === '' || $next[0] !== '/' || str_starts_with($next, '//') || str_contains($next, '\\')
      || preg_match('/[\x00-\x1f]/', $next) || str_starts_with($next, '/src/login')) {
    return '/';
  }
  return $next;
}

function login_url(?string $next = null): string {
  $next = safe_next($next);
  return '/src/login/' . ($next !== '/' ? '?next=' . urlencode($next) : '');
}

// ---------------------------------------------------------------- Estado de permisos

// Carga (una vez por petición) el rol, si la cuenta está activa y sus permisos desde la BD.
// 'ready' = false significa que aún no se ha ejecutado el SQL de roles: en ese caso todo
// sigue funcionando como antes (cualquier usuario con sesión puede entrar a todo).
function acl_state(): array {
  static $st = null;
  if ($st !== null) return $st;
  $st = ['ready' => false, 'role' => (string) ($_SESSION['permission'] ?? 'user'), 'active' => true, 'perms' => []];
  if (!is_logged_in()) return $st;

  global $conn;
  if (!isset($conn)) require_once __DIR__ . '/db.php';

  $uid = (string) $_SESSION['user_id'];
  $u = db_query('SELECT permission, active FROM login_user WHERE id = ?', 's', [$uid]);
  if ($u === false) return $st;                       // faltan columnas: modo anterior
  $p = db_query('SELECT perm FROM user_permissions WHERE user_id = ?', 's', [$uid]);
  if ($p === false) return $st;                       // falta la tabla: modo anterior

  $row = $u->fetch_assoc();
  $st['ready']  = true;
  $st['active'] = $row && (int) $row['active'] === 1;
  $st['role']   = $row ? (string) $row['permission'] : 'user';
  $st['perms']  = array_column(db_rows($p), 'perm');
  $_SESSION['permission'] = $st['role'];
  return $st;
}

function current_role(): string { return acl_state()['role']; }
function is_admin(): bool       { return current_role() === 'admin'; }

// ¿Puede hacer esto? Admin: todo. Sin SQL de roles ejecutado: todo (modo anterior).
// Un permiso "sección.sub" exige además tener la sección.
function has_perm(string $key): bool {
  $st = acl_state();
  if (!$st['ready'] || $st['role'] === 'admin') return true;
  if (!in_array($key, $st['perms'], true)) return false;
  return !str_contains($key, '.') || in_array(explode('.', $key)[0], $st['perms'], true);
}
function can_access_app(string $app): bool { return has_perm($app); }

// Secciones que puede ver el usuario actual (para el menú y el inicio)
function allowed_apps(): array {
  return array_filter(app_registry(), fn($_, $key) => can_access_app($key), ARRAY_FILTER_USE_BOTH);
}

// ---------------------------------------------------------------- Guardas de página

// Exige sesión (y cuenta activa). Si no hay sesión, lleva al login y vuelve aquí al entrar.
function require_login(): void {
  if (!is_logged_in()) {
    header('Location: ' . login_url($_SERVER['REQUEST_URI'] ?? '/'));
    exit();
  }
  $st = acl_state();
  if ($st['ready'] && !$st['active']) {               // cuenta desactivada por el administrador
    $_SESSION = [];
    session_destroy();
    header('Location: /src/login/?msg=disabled');
    exit();
  }
}

// Página de "sin acceso" (403)
function deny_page(string $what = 'esta sección'): void {
  http_response_code(403);
  $page_title = 'Sin acceso';
  $src = $GLOBALS['src'] ?? './';
  ?>
<!DOCTYPE html>
<html lang="es" data-bs-theme="dark">
<?php include __DIR__ . '/ini.php'; ?>
<body>
  <?php include __DIR__ . '/../../frontend/menu.php'; ?>
  <main class="auth-wrap">
    <div class="card shadow auth-card text-center">
      <img class="auth-logo" src="/img/Astronauta-flotador.png" alt="">
      <h1 class="h4">Sin acceso</h1>
      <p class="text-muted">No tienes permiso para entrar en <?= htmlspecialchars($what) ?>.<br>Si lo necesitas, pídeselo al administrador.</p>
      <a href="/" class="btn btn-primary">Volver al inicio</a>
    </div>
  </main>
  <?php include __DIR__ . '/../../frontend/footer.php'; ?>
</body>
</html>
<?php
  exit();
}

// Página de una sección: exige sesión y acceso a la sección
function require_app(string $app): void {
  require_login();
  if (!can_access_app($app)) deny_page('la sección «' . (app_registry()[$app]['label'] ?? $app) . '»');
}

// Admin: exige ser administrador
function require_admin(): void {
  require_login();
  if (!is_admin()) deny_page('la administración de usuarios');
}

// Variante para APIs: responde con JSON/texto y código HTTP en vez de una página
function require_app_api(string $app): void {
  if (!is_logged_in()) { http_response_code(401); exit(json_encode(['ok' => false, 'error' => 'no_session'])); }
  $st = acl_state();
  if ($st['ready'] && !$st['active']) { http_response_code(401); exit(json_encode(['ok' => false, 'error' => 'disabled'])); }
  if (!can_access_app($app)) { http_response_code(403); exit(json_encode(['ok' => false, 'error' => 'forbidden'])); }
}

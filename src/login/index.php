<?php
require_once __DIR__ . '/../../backend/config/bootstrap.php';
require_once __DIR__ . '/../../backend/config/db.php';
global $conn;

// A dónde volver tras entrar (solo rutas internas)
$next = safe_next($_POST['next'] ?? $_GET['next'] ?? '');

// Si ya hay sesión, directo a su destino
if (is_logged_in()) {
  header('Location: ' . $next);
  exit();
}

$error = '';
$info  = ($_GET['msg'] ?? '') === 'logout' ? 'Has cerrado la sesión.' : '';
if (($_GET['msg'] ?? '') === 'disabled') $error = 'Tu cuenta está desactivada. Habla con el administrador.';

// Límite de intentos fallidos por IP y usuario (5 en 15 minutos)
$max_fails = 5;
$window = 15 * 60;
$lock_file = sys_get_temp_dir() . '/login_fails_' . md5(($_SERVER['REMOTE_ADDR'] ?? '') . '|' . strtolower(trim($_POST['user'] ?? ''))) . '.json';
function login_fails($file, $window) {
  $data = is_file($file) ? json_decode((string) @file_get_contents($file), true) : [];
  $now = time();
  return array_values(array_filter(is_array($data) ? $data : [], fn($t) => is_int($t) && $t > $now - $window));
}
function login_fail_register($file, $window) {
  $f = login_fails($file, $window);
  $f[] = time();
  @file_put_contents($file, json_encode($f), LOCK_EX);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $user = trim($_POST['user'] ?? '');
  $pass = $_POST['pass'] ?? '';

  if (count(login_fails($lock_file, $window)) >= $max_fails) {
    $error = 'Demasiados intentos fallidos. Espera unos minutos.';
  } elseif ($user === '' || $pass === '') {
    $error = 'Por favor, completa todos los campos.';
  } else {
    // Con el SQL de roles ejecutado existe la columna "active"; sin él se usa la consulta anterior
    $result = db_query('SELECT id, user, pass, permission, active FROM login_user WHERE user = ?', 's', [$user])
           ?: db_query('SELECT id, user, pass, permission FROM login_user WHERE user = ?', 's', [$user]);
    $row = ($result && $result->num_rows === 1) ? $result->fetch_assoc() : null;

    $ok = false;
    if ($row) {
      $ok = password_verify($pass, $row['pass']);
      // Migración: cuentas antiguas con la contraseña sin hash. Se aceptan una última
      // vez (solo si el valor guardado no es un hash) y se guardan ya cifradas.
      if (!$ok && password_get_info($row['pass'])['algo'] === null && hash_equals($row['pass'], $pass)) {
        $ok = true;
        $row['pass'] = ''; // fuerza el rehash de abajo
      }
    }

    if ($ok && isset($row['active']) && (int) $row['active'] !== 1) {
      $ok = false;
      $error = 'Tu cuenta está desactivada. Habla con el administrador.';
    }

    if ($ok) {
      if (password_needs_rehash($row['pass'], PASSWORD_DEFAULT)) {
        $new_hash = password_hash($pass, PASSWORD_DEFAULT);
        $up = $conn->prepare('UPDATE login_user SET pass = ? WHERE id = ?');
        $up->bind_param('ss', $new_hash, $row['id']);
        $up->execute();
        $up->close();
      }
      @unlink($lock_file);
      db_exec('UPDATE login_user SET last_login = ? WHERE id = ?', 'ss', [date('Y-m-d H:i:s'), $row['id']]); // si la columna aún no existe, no pasa nada
      session_regenerate_id(true); // nuevo id para evitar session fixation
      $_SESSION['user_id'] = $row['id'];
      $_SESSION['username'] = $row['user'];
      $_SESSION['permission'] = $row['permission'];
      header('Location: ' . $next);
      exit();
    }

    login_fail_register($lock_file, $window);
    if ($error === '') $error = 'Usuario o contraseña incorrectos.';
  }
}

$page_title = 'Entrar';
?>
<!DOCTYPE html>
<html lang="es" data-bs-theme="dark">
<?php include __DIR__ . '/../../backend/config/ini.php'; ?>
<body>
  <?php include __DIR__ . '/../../frontend/menu.php'; ?>

  <main class="auth-wrap">
    <div class="card shadow auth-card">
      <img class="auth-logo" src="/img/Astronauta-flotador.png" alt="">
      <h1>Iniciar sesión</h1>

      <?php if ($error !== ''): ?>
        <div class="alert alert-danger" role="alert"><?= htmlspecialchars($error) ?></div>
      <?php elseif ($info !== ''): ?>
        <div class="alert alert-info" role="status"><?= htmlspecialchars($info) ?></div>
      <?php endif; ?>

      <form action="/src/login/" method="POST" autocomplete="on">
        <input type="hidden" name="next" value="<?= htmlspecialchars($next) ?>">
        <div class="mb-3">
          <label for="user" class="form-label">Usuario</label>
          <input type="text" name="user" id="user" class="form-control" required autofocus
                 autocomplete="username" value="<?= htmlspecialchars($_POST['user'] ?? '') ?>">
        </div>
        <div class="mb-4">
          <label for="pass" class="form-label">Contraseña</label>
          <input type="password" name="pass" id="pass" class="form-control" required autocomplete="current-password">
        </div>
        <div class="d-grid">
          <button type="submit" class="btn btn-accent btn-lg">Entrar</button>
        </div>
      </form>
    </div>
  </main>

  <?php include __DIR__ . '/../../frontend/footer.php'; ?>
</body>
</html>

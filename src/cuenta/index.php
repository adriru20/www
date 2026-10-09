<?php
// Mi cuenta: cualquier usuario con sesión puede cambiar su propia contraseña.
require_once __DIR__ . '/../../backend/config/bootstrap.php';
require_login();
require_once __DIR__ . '/../../backend/config/db.php';
global $conn;

const PASS_MIN = 8;
$uid = (string) $_SESSION['user_id'];

// Límite de intentos con la contraseña actual equivocada (5 en 15 minutos por usuario e IP)
$max_fails = 5;
$window = 15 * 60;
$lock_file = sys_get_temp_dir() . '/pass_fails_' . md5($uid . '|' . ($_SERVER['REMOTE_ADDR'] ?? '')) . '.json';
$fails = function () use ($lock_file, $window): array {
  $d = is_file($lock_file) ? json_decode((string) @file_get_contents($lock_file), true) : [];
  return array_values(array_filter(is_array($d) ? $d : [], fn($t) => is_int($t) && $t > time() - $window));
};

$row = ($r = db_query('SELECT id, user, pass, permission FROM login_user WHERE id = ?', 's', [$uid])) ? $r->fetch_assoc() : null;
if (!$row) {                       // la cuenta ya no existe
  header('Location: /src/login/logout.php');
  exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  csrf_verify_post();
  $cur  = (string) ($_POST['current'] ?? '');
  $new  = (string) ($_POST['new'] ?? '');
  $new2 = (string) ($_POST['new2'] ?? '');

  if (count($fails()) >= $max_fails) {
    flash_add('Demasiados intentos con la contraseña actual. Espera unos minutos.', 'danger');
  } elseif ($cur === '' || $new === '' || $new2 === '') {
    flash_add('Completa los tres campos.', 'danger');
  } elseif (!password_verify($cur, $row['pass'])) {
    $f = $fails(); $f[] = time();
    @file_put_contents($lock_file, json_encode($f), LOCK_EX);
    flash_add('La contraseña actual no es correcta.', 'danger');
  } elseif (mb_strlen($new) < PASS_MIN) {
    flash_add('La contraseña nueva debe tener al menos ' . PASS_MIN . ' caracteres.', 'danger');
  } elseif ($new !== $new2) {
    flash_add('La contraseña nueva y su repetición no coinciden.', 'danger');
  } elseif ($new === $cur) {
    flash_add('La contraseña nueva debe ser distinta de la actual.', 'warning');
  } else {
    $ok = db_exec('UPDATE login_user SET pass = ? WHERE id = ?', 'ss', [password_hash($new, PASSWORD_DEFAULT), $uid]);
    if ($ok['ok']) {
      @unlink($lock_file);
      session_regenerate_id(true);
      flash_add('Contraseña cambiada correctamente.');
    } else {
      flash_add('No se pudo guardar la contraseña. Inténtalo de nuevo.', 'danger');
    }
  }
  header('Location: /src/cuenta/');
  exit();
}

$page_title   = 'Mi cuenta';
$page_scripts = ['/js/password-eye.js'];
?>
<!DOCTYPE html>
<html lang="es" data-bs-theme="dark">
<?php include __DIR__ . '/../../backend/config/ini.php'; ?>
<body>
  <?php include __DIR__ . '/../../frontend/menu.php'; ?>
  <main class="page-container">
    <?php flash_render(); ?>
    <div class="page-header">
      <h1 class="h3"><i class="fa-solid fa-user-gear"></i> Mi cuenta</h1>
    </div>

    <div class="card" style="max-width: 520px;">
      <div class="card-body">
        <p class="text-muted mb-3">
          Sesión iniciada como <strong><?= e($row['user']) ?></strong>
          <span class="badge bg-secondary ms-1"><?= e(ROLES[$row['permission']] ?? $row['permission']) ?></span>
        </p>
        <h2 class="h5 mb-3">Cambiar contraseña</h2>
        <form method="POST" action="/src/cuenta/" autocomplete="off">
          <?= csrf_input() ?>
          <!-- Ayuda a los gestores de contraseñas a saber de qué cuenta se trata -->
          <input type="text" name="username" value="<?= e($row['user']) ?>" autocomplete="username" class="visually-hidden" tabindex="-1" aria-hidden="true" readonly>
          <div class="mb-3">
            <label class="form-label" for="current">Contraseña actual</label>
            <input type="password" id="current" name="current" class="form-control" required autocomplete="current-password" data-eye>
          </div>
          <div class="mb-3">
            <label class="form-label" for="new">Contraseña nueva <small class="text-muted">(mínimo <?= PASS_MIN ?> caracteres)</small></label>
            <input type="password" id="new" name="new" class="form-control" required minlength="<?= PASS_MIN ?>" autocomplete="new-password" data-eye>
          </div>
          <div class="mb-4">
            <label class="form-label" for="new2">Repite la contraseña nueva</label>
            <input type="password" id="new2" name="new2" class="form-control" required minlength="<?= PASS_MIN ?>" autocomplete="new-password" data-eye>
            <div class="form-text">Pulsa el ojo de cada campo para ver lo que has escrito y comprobarlo.</div>
          </div>
          <button type="submit" class="btn btn-accent">Guardar contraseña</button>
        </form>
      </div>
    </div>
  </main>
  <?php include __DIR__ . '/../../frontend/footer.php'; ?>
</body>
</html>

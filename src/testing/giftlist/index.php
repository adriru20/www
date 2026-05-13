<?php
// ──────────────────────────────────────────────
//  GiftList · Login
// ──────────────────────────────────────────────
include "../../backend/config/ini.php";
gl_session();

// Redirigir si ya autenticado
if (!empty($_SESSION['gl_id'])) {
    header('Location: ' . (gl_is_admin() ? 'admin.php' : 'dashboard.php'));
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    gl_csrf_verify();
    $email = trim($_POST['email'] ?? '');
    $pass  = $_POST['pass'] ?? '';

    if (!$email || !$pass) {
        $error = 'Completa todos los campos.';
    } else {
        $user = gl_authenticate($email, $pass);
        if ($user) {
            gl_login($user);
            header('Location: ' . ($user['role'] === 'admin' ? 'admin.php' : 'dashboard.php'));
            exit;
        }
        $error = 'Correo o contraseña incorrectos.';
    }
}
?>
<!DOCTYPE html>
<html lang="es" data-bs-theme="dark">
<body>
  <?php include "{$src}frontend/menu.php"; ?>

  <style>
    .gl-login-wrap { min-height: 80vh; display: flex; align-items: center; justify-content: center; }
    .gl-card { width: 100%; max-width: 420px; }
    .gl-brand { font-size: 2rem; }
  </style>

  <div class="gl-login-wrap px-3">
    <div class="gl-card">
      <div class="text-center mb-4">
        <div class="gl-brand">🎁</div>
        <h2 class="fw-bold">GiftList</h2>
        <p class="text-secondary small">Gestiona tus listas de deseos</p>
      </div>

      <div class="card border-secondary">
        <div class="card-body p-4">

          <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show py-2" role="alert">
              <i class="fa fa-triangle-exclamation me-1"></i> <?= gl_h($error) ?>
              <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
          <?php endif; ?>

          <form method="post" autocomplete="on">
            <?= gl_csrf_input() ?>

            <div class="mb-3">
              <label for="email" class="form-label small text-uppercase fw-bold text-secondary">Correo electrónico</label>
              <input type="email" id="email" name="email" class="form-control"
                     value="<?= gl_h($_POST['email'] ?? '') ?>"
                     placeholder="tu@correo.com" required autofocus>
            </div>

            <div class="mb-4">
              <label for="pass" class="form-label small text-uppercase fw-bold text-secondary">Contraseña</label>
              <input type="password" id="pass" name="pass" class="form-control"
                     placeholder="••••••••" required>
            </div>

            <button type="submit" class="btn btn-primary w-100">
              <i class="fa fa-right-to-bracket me-1"></i> Entrar
            </button>
          </form>
        </div>
      </div>

      <p class="text-center text-secondary small mt-3">
        <a href="https://<?= gl_h($url ?? 'www.adriru.es') ?>/" class="text-secondary">
          ← Volver a adriru.es
        </a>
      </p>
    </div>
  </div>

  <?php include "{$src}frontend/footer.php"; ?>
</body>
</html>

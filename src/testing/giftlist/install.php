<?php
// ──────────────────────────────────────────────────────────────
//  GiftList · Instalador de tablas
//  Crea gl_users y gl_gifts en la BD "login_user" existente.
//  ⚠ ELIMINA ESTE ARCHIVO del servidor tras instalarlo.
// ──────────────────────────────────────────────────────────────

include "../../backend/config/ini.php";
$error = $success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['install'])) {
    $admin_name  = trim($_POST['admin_name']  ?? '');
    $admin_email = trim($_POST['admin_email'] ?? '');
    $admin_pass  = $_POST['admin_pass'] ?? '';

    if (!$admin_name || !$admin_email || strlen($admin_pass) < 8) {
        $error = 'Completa todos los campos. La contraseña debe tener mínimo 8 caracteres.';
    } else {
        // Charset UTF-8
        $conn->set_charset('utf8mb4');

        // Tabla gl_users
        $conn->query("
            CREATE TABLE IF NOT EXISTS gl_users (
                id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                name       VARCHAR(100)  NOT NULL,
                email      VARCHAR(150)  NOT NULL UNIQUE,
                password   VARCHAR(255)  NOT NULL,
                role       ENUM('admin','user') NOT NULL DEFAULT 'user',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // Tabla gl_gifts
        $conn->query("
            CREATE TABLE IF NOT EXISTS gl_gifts (
                id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                user_id        INT UNSIGNED NOT NULL,
                name           VARCHAR(200) NOT NULL,
                description    TEXT,
                url            VARCHAR(1000),
                price          DECIMAL(10,2) UNSIGNED,
                priority       ENUM('high','medium','low') NOT NULL DEFAULT 'medium',
                priority_order TINYINT NOT NULL DEFAULT 2,
                reserved_by    INT UNSIGNED,
                created_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id)     REFERENCES gl_users(id) ON DELETE CASCADE,
                FOREIGN KEY (reserved_by) REFERENCES gl_users(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        if ($conn->error) {
            $error = 'Error al crear tablas: ' . htmlspecialchars($conn->error);
        } else {
            // Admin inicial (INSERT IGNORE por si ya existe)
            $hash = password_hash($admin_pass, PASSWORD_BCRYPT, ['cost' => 12]);
            $st = $conn->prepare(
                'INSERT IGNORE INTO gl_users (name, email, password, role) VALUES (?, ?, ?, "admin")'
            );
            $st->bind_param('sss', $admin_name, $admin_email, $hash);
            $st->execute();
            $st->close();

            if ($conn->error) {
                $error = 'Error al crear el administrador: ' . htmlspecialchars($conn->error);
            } else {
                $success = 'Instalación completada correctamente.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es" data-bs-theme="dark">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Instalador · GiftList · adriru.es</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body { display: flex; align-items: center; justify-content: center; min-height: 100vh; background: #0d1117; }
    .install-card { width: 100%; max-width: 460px; }
    .badge-path { font-family: monospace; font-size: .75rem; opacity: .6; }
  </style>
</head>
<body>
<div class="install-card p-2">
  <div class="card border-secondary">
    <div class="card-body p-4">
      <h4 class="card-title mb-1">🎁 GiftList · Instalador</h4>
      <p class="text-secondary small mb-4">Crea las tablas <code>gl_users</code> y <code>gl_gifts</code> en la base de datos <strong>login_user</strong>.</p>

      <?php if ($error): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>

      <?php if ($success): ?>
        <div class="alert alert-success">
          <strong>✓ <?= $success ?></strong><br><br>
          ⚠️ <strong>Elimina este archivo</strong> del servidor por seguridad:<br>
          <code class="badge-path">apps/giftlist/install.php</code><br><br>
          <a href="index.php" class="btn btn-success btn-sm mt-2">→ Ir al login de GiftList</a>
        </div>
      <?php else: ?>
      <form method="post">
        <div class="mb-3">
          <label class="form-label small text-secondary text-uppercase fw-bold">Nombre del administrador</label>
          <input type="text" name="admin_name" class="form-control" required
                 value="<?= htmlspecialchars($_POST['admin_name'] ?? '') ?>">
        </div>
        <div class="mb-3">
          <label class="form-label small text-secondary text-uppercase fw-bold">Email del administrador</label>
          <input type="email" name="admin_email" class="form-control" required
                 value="<?= htmlspecialchars($_POST['admin_email'] ?? '') ?>">
        </div>
        <div class="mb-4">
          <label class="form-label small text-secondary text-uppercase fw-bold">Contraseña (mín. 8 caracteres)</label>
          <input type="password" name="admin_pass" class="form-control" required minlength="8">
        </div>
        <button type="submit" name="install" class="btn btn-primary w-100">Instalar GiftList</button>
      </form>
      <?php endif; ?>
    </div>
  </div>
  <p class="text-center text-secondary small mt-3">adriru.es · apps/giftlist</p>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

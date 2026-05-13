<?php
header("Location: ../");

session_start();
$src = '../../';

// // MODO DEBUG
// ini_set('display_errors', 1);
// ini_set('display_startup_errors', 1);
// error_reporting(E_ALL);

// Requerimos la conexión
require_once $src . 'backend/config/db.php';
global $conn;

// 2. Verificar si la conexión existe
if (!$conn) {
  die("Fallo crítico: No existe la conexión en \$conn. Revisa backend/config/db.php");
}

$mensaje = '';
$tipo_mensaje = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
  $user = trim($_POST['user'] ?? '');
  $pass = $_POST['pass'] ?? '';
  $pass_confirm = $_POST['pass_confirm'] ?? '';

  if (empty($user) || empty($pass) || empty($pass_confirm)) {
    $mensaje = "Todos los campos son obligatorios.";
    $tipo_mensaje = "danger";
  } elseif ($pass !== $pass_confirm) {
    $mensaje = "Las contraseñas no coinciden.";
    $tipo_mensaje = "danger";
  } else {
    // Comprobar si el usuario existe
    $stmt = $conn->prepare("SELECT id FROM login_user WHERE user = ?");
    $stmt->bind_param("s", $user);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
      $mensaje = "El nombre de usuario ya está en uso.";
      $tipo_mensaje = "warning";
    } else {
      $new_id = uniqid('usr_', true);
      $hashed_pass = password_hash($pass, PASSWORD_DEFAULT);
      $permission = 'user';

      // 3. Intento de inserción con captura de error
      $insert_stmt = $conn->prepare("INSERT INTO login_user (id, user, pass, permission) VALUES (?, ?, ?, ?)");
      $insert_stmt->bind_param("ssss", $new_id, $user, $hashed_pass, $permission);

      if ($insert_stmt->execute()) {
        $mensaje = "¡Cuenta creada! <a href='index.php' class='alert-link'>Loguéate aquí</a>.";
        $tipo_mensaje = "success";
      } else {
        // ESTO TE DIRÁ EL ERROR REAL:
        $mensaje = "Error de MySQL: " . $insert_stmt->error;
        $tipo_mensaje = "danger";
      }
      $insert_stmt->close();
    }
    $stmt->close();
  }
}
?>
<!DOCTYPE html>
<html lang="es" data-bs-theme="dark">
<?php require_once $src . 'backend/config/ini.php'; ?>
<body>
  <?php include "{$src}frontend/menu.php"; ?>
  <main class="my-6">
    <div class="container justify-content-center">
      <div class="col-12 col-md-6 col-lg-4">
        <h1 class="text-center mb-4">Registro</h1>
        <?php if ($mensaje != ''): ?>
          <div class="alert alert-<?php echo $tipo_mensaje; ?>" role="alert">
            <?php echo $mensaje; ?>
          </div>
        <?php endif; ?>
        <div class="card shadow border-secondary">
          <div class="card-body p-4">
            <form action="signup.php" method="POST">
              <div class="mb-3">
                <label class="form-label">Usuario</label>
                <input type="text" name="user" class="form-control" required value="<?php echo htmlspecialchars($user ?? ''); ?>">
              </div>
              <div class="mb-3">
                <label class="form-label">Contraseña</label>
                <input type="password" name="pass" class="form-control" required>
              </div>
              <div class="mb-4">
                <label class="form-label">Confirmar contraseña</label>
                <input type="password" name="pass_confirm" class="form-control" required>
              </div>
              <div class="d-grid gap-2">
                <button type="submit" class="btn btn-primary btn-lg">Registrarse</button>
              </div>
            </form>
          </div>
        </div>
      </div>
    </div>
  </main>
  <?php include "{$src}frontend/footer.php"; ?>
</body>

</html>
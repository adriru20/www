<?php
session_start();

// Definimos la ruta relativa para los includes y las redirecciones
$src = '../../';

// // MODO DEBUG
// ini_set('display_errors', 1);
// ini_set('display_startup_errors', 1);
// error_reporting(E_ALL);

// Redirigir si ya está logueado a la página principal
if (isset($_SESSION['user_id'])) {
  header("Location: {$src}");
  exit();
}

require_once $src . 'backend/config/db.php';
global $conn;

$error = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
  $user = trim($_POST['user'] ?? '');
  $pass = $_POST['pass'] ?? '';

  if (!empty($user) && !empty($pass)) {
    // Preparamos la consulta
    $stmt = $conn->prepare("SELECT id, user, pass, permission FROM login_user WHERE user = ?");
    $stmt->bind_param("s", $user);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
      $row = $result->fetch_assoc();

      // Validamos la contraseña
      if (password_verify($pass, $row['pass']) || $pass === $row['pass']) {
        // Iniciar sesión
        $_SESSION['user_id'] = $row['id'];
        $_SESSION['username'] = $row['user'];
        $_SESSION['permission'] = $row['permission'];

        header("Location: {$src}");
        exit();
      } else {
        header("Location: logout.php");
        exit();
      }
    } else {
      header("Location: logout.php");
      exit();
    }
    $stmt->close();
  } else {
    $error = "Por favor, completa todos los campos.";
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

        <h1 class="text-center mb-4">Iniciar Sesión</h1>

        <?php if ($error != ''): ?>
          <div class="alert alert-danger" role="alert">
            <?php echo htmlspecialchars($error); ?>
          </div>
        <?php endif; ?>

        <div class="card shadow border-secondary">
          <div class="card-body p-4">
            <form action="index.php" method="POST">
              <div class="mb-3">
                <label for="user" class="form-label">Usuario</label>
                <input type="text" name="user" id="user" class="form-control" required autofocus>
              </div>

              <div class="mb-4">
                <label for="pass" class="form-label">Contraseña</label>
                <input type="password" name="pass" id="pass" class="form-control" required>
              </div>

              <div class="d-grid gap-2">
                <button type="submit" class="btn btn-primary btn-lg">Entrar</button>
              </div>
            </form>
          </div>
        </div>

        <div class="text-center mt-4">
          <p>¿No tienes cuenta? <a href="signup.php" class="text-info text-decoration-none">Regístrate aquí</a></p>
        </div>

      </div>
    </div>
  </main>
  <?php include "{$src}frontend/footer.php"; ?>
</body>

</html>
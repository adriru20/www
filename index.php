<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: /src/login/");
    exit();
}
?>

<!DOCTYPE html>
<html lang="es" data-bs-theme="dark">
<?php include "{$src}backend/config/ini.php"; save_ip(); ?>
<body>
  <?php include "{$src}frontend/menu.php"; ?>
  <nav class="container">

    <h1><?php include "{$src}frontend/menu.php"; ?></h1>

  </nav>
  <?php include "{$src}frontend/footer.php"; ?>
</body>
</html>
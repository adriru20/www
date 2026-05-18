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
    <li><a href="https://<?=$url?>/" class="btn btn-primary"><b>INICIO</b></a></li>
    <li><a href="https://<?=$url?>/apps/wiki/" class="btn btn-primary"><b>WIKI</b></a></li>
    <li><a href="https://<?=$url?>/apps/parking/" class="btn btn-primary"><b>PARKING</b></a></li>
    <li><a href="https://<?=$url?>/apps/giftlist/" class="btn btn-primary"><b>GIFT LIST</b></a></li>
    <li><a href="https://<?=$url?>/apps/inventario/" class="btn btn-primary"><b>INVENTARIO</b></a></li>
  </nav>
  <?php include "{$src}frontend/footer.php"; ?>
</body>
</html>
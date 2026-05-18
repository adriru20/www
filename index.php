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

    <li><a href="https://<?=$url?>/"><b>INICIO</b></a></li>
    <li><a href="https://<?=$url?>/apps/wiki/"><b>WIKI</b></a></li>
    <li><a href="https://<?=$url?>/apps/parking/"><b>PARKING</b></a></li>
    <li><a href="https://<?=$url?>/apps/giftlist/"><b>GIFT LIST</b></a></li>
    <li><a href="https://<?=$url?>/apps/inventario/"><b>INVENTARIO</b></a></li>

  </nav>
  <?php include "{$src}frontend/footer.php"; ?>
</body>
</html>
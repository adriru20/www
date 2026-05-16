<?php
  if ($_SERVER["SERVER_NAME"] == 'adriru.es') {
    $url = "www.{$_SERVER["SERVER_NAME"]}";
    $entorno = substr(string: $url, offset: 0, length: -10);
  } else {
    $url = $_SERVER["SERVER_NAME"];
    $entorno = substr(string: $url, offset: 0, length: -10);
  }
// Nos aseguramos de que la sesión esté iniciada para poder consultar $_SESSION
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>

<header>
  <div class="navbar">
    <div class="logo"><a href="https://<?=$url?>/"><img src="<?=$src?>/img/Astronauta-flotador.png" alt="logo"/></a></div>

    <ul class="links">
      <li><a href="https://<?=$url?>/"><b>INICIO</b></a></li>
      <li><a href="https://<?=$url?>/apps/wiki/"><b>WIKI</b></a></li>
      <li><a href="https://<?=$url?>/apps/parking/"><b>PARKING</b></a></li>
      <li><a href="https://<?=$url?>/apps/giftlist/"><b>GIFT LIST</b></a></li>
      <li><a href="https://<?=$url?>/apps/inventario/"><b>INVENTARIO</b></a></li>
      <?php if (isset($_SESSION['user_id'])): ?>
        <li><a href="https://<?=$url?>/src/login/logout.php"><b>LOGOUT</b></a></li>
      <?php else: ?>
        <li><a href="https://<?=$url?>/src/login/"><b>LOGIN</b></a></li>
      <?php endif; ?>
    </ul>

    <div class="toggle_btn">
      <i class="fa-solid fa-bars"></i>
    </div>
  </div>

  <div class="dropdown_menu">
    <li><a href="https://<?=$url?>/"><b>INICIO</b></a></li>
    <li><a href="https://<?=$url?>/apps/wiki/"><b>WIKI</b></a></li>
      <li><a href="https://<?=$url?>/apps/parking/"><b>PARKING</b></a></li>
    <li><a href="https://<?=$url?>/apps/giftlist/"><b>GIFT LIST</b></a></li>
    <li><a href="https://<?=$url?>/apps/inventario/"><b>INVENTARIO</b></a></li>
    <?php if (isset($_SESSION['user_id'])): ?>
      <li><a href="https://<?=$url?>/src/login/logout.php"><b>LOGOUT</b></a></li>
    <?php else: ?>
      <li><a href="https://<?=$url?>/src/login/"><b>LOGIN</b></a></li>
    <?php endif; ?>
  </div>
</header>
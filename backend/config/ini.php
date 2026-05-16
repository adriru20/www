<?php
// Asegurarnos de que la sesión esté iniciada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Rutas donde NO queremos exigir login para no hacer un bucle de redirecciones
$current_page = basename($_SERVER['PHP_SELF']);
$allowed_pages = ['signup.php', 'login.php', 'logout.php', 'index.php',]; // Ajusta según tu estructura

// Comprobar si NO hay una sesión activa de usuario
if (!isset($_SESSION['user_id'])) {

    // Aquí defines la ruta raíz o índice público
    $redirect_url = '../../index.php'; // Cambiar por la ruta correcta si estás en subcarpetas

    // Previene bucle infinito si ya estás en el index.php o ficheros permitidos
    if (!in_array($current_page, $allowed_pages)) {
        header("Location: " . $redirect_url);
        exit();
    }
}

// Detectar el entorno
unset($url); unset($entorno);
if ($_SERVER["SERVER_NAME"] == 'adriru.es') {
  $url = "www.{$_SERVER["SERVER_NAME"]}";
  $entorno = substr(string: $url, offset: 0, length: -10);
} else {
  $url = $_SERVER["SERVER_NAME"];
  $entorno = substr(string: $url, offset: 0, length: -10);
}
// Detectar la ruta relativa
unset($src);
if (getcwd() === "/homepages/28/d1007113302/htdocs/$entorno") {
  $src = "./";
} else {
  $src = "../../";
}
include "{$src}backend/config/environment.php";
include "{$src}frontend/head.php";
// Incluye todos los ficheros de funciones
unset($directory);
$directory = "{$src}backend/functions/";
$directorio=opendir("$directory");
while ($archivo = readdir($directorio)) {
  if (($archivo!=".")and($archivo!="..")) {
    include "{$directory}$archivo";
  } else {
    $archivo=null;
  }
}

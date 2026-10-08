<?php
// Arranque común de las páginas: sesión, rutas, cabecera HTML y funciones.
require_once __DIR__ . '/session.php';

// Raíz del proyecto en disco y ruta relativa desde la página actual hasta ella ($src).
// Se calcula por la profundidad del script, sin depender del dominio ni de rutas del servidor.
$project_root = realpath(__DIR__ . '/../..');
$script_dir   = dirname(realpath($_SERVER['SCRIPT_FILENAME']));
$depth = 0;
if (strpos($script_dir, $project_root) === 0) {
  $rel = trim(substr($script_dir, strlen($project_root)), DIRECTORY_SEPARATOR);
  $depth = ($rel === '') ? 0 : count(explode(DIRECTORY_SEPARATOR, $rel));
}
$src = $depth === 0 ? './' : str_repeat('../', $depth);

// Host para los enlaces absolutos del menú (adriru.es -> www.adriru.es)
$url = $_SERVER["SERVER_NAME"];
if ($url === 'adriru.es') {
  $url = "www.$url";
}

// Sin sesión solo se permite el área de login (las páginas públicas no incluyen este fichero)
if (!isset($_SESSION['user_id'])) {
  $in_login_area = strpos(str_replace('\\', '/', $script_dir), '/src/login') !== false;
  if (!$in_login_area) {
    header("Location: {$src}src/login/");
    exit();
  }
}

include "{$src}backend/config/environment.php";
include "{$src}frontend/head.php";

// Carga todas las funciones de backend/functions/ (solo ficheros .php)
foreach (glob(__DIR__ . '/../functions/*.php') as $archivo) {
  include_once $archivo;
}

<?php
// Arranque común (sin salida HTML): sesión, rutas y autenticación.
// Cada página lo incluye lo primero; después llama a require_login() si es privada.
require_once __DIR__ . '/session.php';
require_once __DIR__ . '/auth.php';

// $src: ruta relativa desde la página actual hasta la raíz del proyecto (para include/require)
if (!isset($src)) {
  $project_root = realpath(__DIR__ . '/../..');
  $script_dir   = dirname(realpath($_SERVER['SCRIPT_FILENAME']));
  $depth = 0;
  if (strpos($script_dir, $project_root) === 0) {
    $rel = trim(substr($script_dir, strlen($project_root)), DIRECTORY_SEPARATOR);
    $depth = ($rel === '') ? 0 : count(explode(DIRECTORY_SEPARATOR, $rel));
  }
  $src = $depth === 0 ? './' : str_repeat('../', $depth);
}

// Carga las funciones de backend/functions/ (csrf, log de accesos...)
foreach (glob(__DIR__ . '/../functions/*.php') as $archivo) {
  include_once $archivo;
}

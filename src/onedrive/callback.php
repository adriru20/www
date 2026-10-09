<?php
// Vuelta desde Microsoft tras iniciar sesión: intercambia el código por el permiso de larga duración.
require_once __DIR__ . '/../../backend/config/bootstrap.php';
require_admin();
require_once __DIR__ . '/../../backend/lib/onedrive.php';

if (isset($_GET['error'])) {
  unset($_SESSION['od_oauth']);
  flash_add('Microsoft no concedió el permiso: ' . substr((string) ($_GET['error_description'] ?? $_GET['error']), 0, 200), 'warning');
} elseif (!od_config()) {
  flash_add('Falta la configuración de OneDrive.', 'warning');
} else {
  $r = od_finish_connect((string) ($_GET['code'] ?? ''), (string) ($_GET['state'] ?? ''));
  $r['ok'] ? flash_add('OneDrive conectado. Ya puedes sincronizar la wiki.') : flash_add($r['error'], 'danger');
}
header('Location: /src/onedrive/');
exit();

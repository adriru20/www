<?php
// api/sync.php: sincroniza la wiki con OneDrive (la llama wiki.js al abrir la página o con el botón de actualizar).
// POST csrf + mode = auto (solo si toca) | force (siempre, con un mínimo de 15 s entre ejecuciones) | continue (sigue descargando)
// GET → solo estado, sin sincronizar.
require_once __DIR__ . '/../../../backend/config/bootstrap.php';
require_once __DIR__ . '/../../../backend/lib/onedrive.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
require_app_api('wiki');

$reply = function (array $r) {
  $s = od_status();
  echo json_encode($r + ['configured' => $s['configured'], 'connected' => $s['connected'], 'notes' => $s['notes'],
                         'ago' => $s['last_sync'] ? time() - $s['last_sync'] : null, 'last_error' => $s['last_error']], JSON_UNESCAPED_UNICODE);
  exit();
};

if (!od_config() || !od_is_connected()) $reply(['state' => 'not_configured', 'changed' => 0, 'pending' => 0, 'message' => '']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') $reply(['state' => 'status', 'changed' => 0, 'pending' => od_status()['pending'], 'message' => '']);

if (!csrf_valid($_POST['csrf'] ?? null)) { http_response_code(403); echo json_encode(['state' => 'error', 'message' => 'Token no válido: recarga la página.']); exit(); }
session_write_close();                       // no bloquear el resto de peticiones del usuario mientras se sincroniza

$mode = (string) ($_POST['mode'] ?? 'auto');
$s = od_status();
if ($mode === 'auto' && !od_is_stale()) $reply(['state' => 'fresh', 'changed' => 0, 'pending' => 0, 'message' => '']);
if ($mode === 'force' && $s['pending'] === 0 && time() - $s['last_run'] < 15) $reply(['state' => 'fresh', 'changed' => 0, 'pending' => 0, 'message' => 'Se acaba de comprobar.']);

$reply(od_sync(12));

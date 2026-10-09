<?php
// API del Parking: guarda las ubicaciones en el servidor (tabla parking_spots, se crea sola).
// Siempre responde JSON. Requiere sesión; las acciones que escriben exigen token CSRF.
require_once __DIR__ . '/../../backend/config/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function out(array $data, int $code = 200): void {
  http_response_code($code);
  echo json_encode($data, JSON_UNESCAPED_UNICODE);
  exit;
}

if (!is_logged_in()) out(['ok' => false, 'error' => 'no_session'], 401);

require_once __DIR__ . '/../../backend/config/db.php';
global $conn;
$uid = (string) $_SESSION['user_id'];

const PARK_HISTORY_KEEP = 30;
const PARK_PHOTO_MAX = 10 * 1024 * 1024;
define('PARK_UPLOADS', __DIR__ . '/uploads/');

// La tabla se crea la primera vez que falta (no hace falta ejecutar SQL a mano)
function park_ensure_table(): bool {
  $r = db_exec("CREATE TABLE IF NOT EXISTS parking_spots (
      id INT AUTO_INCREMENT PRIMARY KEY,
      user_id VARCHAR(50) NOT NULL,
      label VARCHAR(40) NOT NULL DEFAULT 'Coche',
      lat DOUBLE NOT NULL,
      lon DOUBLE NOT NULL,
      accuracy DOUBLE NULL,
      note VARCHAR(255) NOT NULL DEFAULT '',
      photo VARCHAR(80) NULL,
      parked_at INT NOT NULL,
      meter_until INT NULL,
      active TINYINT(1) NOT NULL DEFAULT 1,
      archived_at INT NULL,
      KEY idx_user_active (user_id, active)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
  return $r['ok'];
}

function park_spot_out(array $s): array {
  return [
    'id' => (int) $s['id'], 'label' => $s['label'], 'lat' => (float) $s['lat'], 'lon' => (float) $s['lon'],
    'accuracy' => $s['accuracy'] !== null ? (float) $s['accuracy'] : null, 'note' => $s['note'],
    'has_photo' => !empty($s['photo']), 'parked_at' => (int) $s['parked_at'],
    'meter_until' => $s['meter_until'] !== null ? (int) $s['meter_until'] : null,
    'active' => (int) $s['active'] === 1, 'archived_at' => $s['archived_at'] !== null ? (int) $s['archived_at'] : null,
  ];
}

function park_get(int $id, string $uid): ?array {
  $r = db_query('SELECT * FROM parking_spots WHERE id = ? AND user_id = ?', 'is', [$id, $uid]);
  return $r ? ($r->fetch_assoc() ?: null) : null;
}

function park_delete_photo(?string $photo): void {
  if ($photo && preg_match('/^[a-f0-9_]+\.jpg$/', $photo)) @unlink(PARK_UPLOADS . $photo);
}

// Guarda la foto subida (JPEG/PNG/WebP): se re-codifica para quitar metadatos (GPS, etc.)
function park_save_photo(array $f, string $uid): ?string {
  if (empty($f['tmp_name']) || ($f['error'] ?? 1) !== UPLOAD_ERR_OK || $f['size'] > PARK_PHOTO_MAX) return null;
  $info = @getimagesize($f['tmp_name']);
  if (!$info || !in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true)) return null;
  if (!is_dir(PARK_UPLOADS)) @mkdir(PARK_UPLOADS, 0750, true);
  $name = substr(md5($uid), 0, 8) . '_' . bin2hex(random_bytes(8)) . '.jpg';
  $dest = PARK_UPLOADS . $name;
  if (extension_loaded('gd')) {
    $img = match ($info[2]) { IMAGETYPE_JPEG => @imagecreatefromjpeg($f['tmp_name']), IMAGETYPE_PNG => @imagecreatefrompng($f['tmp_name']),
                              default => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($f['tmp_name']) : false };
    if ($img) {
      $w = imagesx($img); $h = imagesy($img); $max = 1280;
      if (max($w, $h) > $max) {
        $k = $max / max($w, $h); $n = imagecreatetruecolor((int) round($w * $k), (int) round($h * $k));
        imagecopyresampled($n, $img, 0, 0, 0, 0, imagesx($n), imagesy($n), $w, $h); $img = $n;
      }
      $ok = imagejpeg($img, $dest, 80);
      return $ok ? $name : null;
    }
  }
  return move_uploaded_file($f['tmp_name'], $dest) ? $name : null;
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';
$isWrite = in_array($action, ['save', 'update', 'archive', 'delete'], true);

if ($isWrite) {
  if ($_SERVER['REQUEST_METHOD'] !== 'POST') out(['ok' => false, 'error' => 'method'], 405);
  if (!csrf_valid($_POST['csrf'] ?? null)) out(['ok' => false, 'error' => 'csrf'], 403);
}

// ¿Existe la tabla? (si falta, se crea; si no se puede, la app usa el almacén local del móvil)
if (db_query('SELECT 1 FROM parking_spots LIMIT 1') === false) {
  if (!park_ensure_table() || db_query('SELECT 1 FROM parking_spots LIMIT 1') === false) out(['ok' => false, 'error' => 'no_table'], 503);
}

$now = time();

switch ($action) {
  case 'list':
    $active = db_rows(db_query('SELECT * FROM parking_spots WHERE user_id = ? AND active = 1 ORDER BY parked_at DESC', 's', [$uid]));
    $hist = db_rows(db_query('SELECT * FROM parking_spots WHERE user_id = ? AND active = 0 ORDER BY archived_at DESC, id DESC LIMIT 20', 's', [$uid]));
    out(['ok' => true, 'active' => array_map('park_spot_out', $active), 'history' => array_map('park_spot_out', $hist), 'now' => $now]);

  case 'save':
    $lat = (float) ($_POST['lat'] ?? 999); $lon = (float) ($_POST['lon'] ?? 999);
    if (!is_numeric($_POST['lat'] ?? null) || !is_numeric($_POST['lon'] ?? null) || abs($lat) > 90 || abs($lon) > 180) out(['ok' => false, 'error' => 'coords'], 422);
    $label = mb_substr(trim((string) ($_POST['label'] ?? '')), 0, 40) ?: 'Coche';
    $note = mb_substr(trim((string) ($_POST['note'] ?? '')), 0, 255);
    $acc = is_numeric($_POST['accuracy'] ?? null) ? max(0, (float) $_POST['accuracy']) : null;
    $meter = is_numeric($_POST['meter_until'] ?? null) ? (int) $_POST['meter_until'] : null;
    if ($meter !== null && ($meter <= $now || $meter > $now + 86400 * 2)) $meter = null;
    $photo = !empty($_FILES['photo']) ? park_save_photo($_FILES['photo'], $uid) : null;

    // Solo un sitio activo por etiqueta: el anterior pasa al historial
    db_exec('UPDATE parking_spots SET active = 0, archived_at = ? WHERE user_id = ? AND label = ? AND active = 1', 'iss', [$now, $uid, $label]);
    $r = db_exec('INSERT INTO parking_spots (user_id, label, lat, lon, accuracy, note, photo, parked_at, meter_until, active) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1)',
                 'ssdddssii', [$uid, $label, $lat, $lon, $acc, $note, $photo, $now, $meter]);
    if (!$r['ok']) { park_delete_photo($photo); out(['ok' => false, 'error' => 'db'], 500); }

    // El historial se limita a las últimas PARK_HISTORY_KEEP entradas
    $old = db_rows(db_query('SELECT id, photo FROM parking_spots WHERE user_id = ? AND active = 0 ORDER BY archived_at DESC, id DESC LIMIT 1000 OFFSET ' . PARK_HISTORY_KEEP, 's', [$uid]));
    foreach ($old as $o) { park_delete_photo($o['photo']); db_exec('DELETE FROM parking_spots WHERE id = ? AND user_id = ?', 'is', [(int) $o['id'], $uid]); }

    out(['ok' => true, 'spot' => park_spot_out(park_get($r['insert_id'], $uid))]);

  case 'update':
    $s = park_get((int) ($_POST['id'] ?? 0), $uid);
    if (!$s) out(['ok' => false, 'error' => 'not_found'], 404);
    $note = array_key_exists('note', $_POST) ? mb_substr(trim((string) $_POST['note']), 0, 255) : $s['note'];
    $meter = $s['meter_until'];
    if (array_key_exists('meter_until', $_POST)) {
      $m = (int) $_POST['meter_until'];
      $meter = ($m > $now && $m <= $now + 86400 * 2) ? $m : null;
    }
    db_exec('UPDATE parking_spots SET note = ?, meter_until = ? WHERE id = ? AND user_id = ?', 'siis', [$note, $meter, (int) $s['id'], $uid]);
    out(['ok' => true, 'spot' => park_spot_out(park_get((int) $s['id'], $uid))]);

  case 'archive':
    $s = park_get((int) ($_POST['id'] ?? 0), $uid);
    if (!$s) out(['ok' => false, 'error' => 'not_found'], 404);
    db_exec('UPDATE parking_spots SET active = 0, archived_at = ? WHERE id = ? AND user_id = ?', 'iis', [$now, (int) $s['id'], $uid]);
    out(['ok' => true]);

  case 'delete':
    $s = park_get((int) ($_POST['id'] ?? 0), $uid);
    if (!$s) out(['ok' => false, 'error' => 'not_found'], 404);
    park_delete_photo($s['photo']);
    db_exec('DELETE FROM parking_spots WHERE id = ? AND user_id = ?', 'is', [(int) $s['id'], $uid]);
    out(['ok' => true]);

  case 'photo':
    $s = park_get((int) ($_GET['id'] ?? 0), $uid);
    $file = $s && $s['photo'] && preg_match('/^[a-f0-9_]+\.jpg$/', $s['photo']) ? PARK_UPLOADS . $s['photo'] : null;
    if (!$file || !is_file($file)) { http_response_code(404); exit; }
    header('Content-Type: image/jpeg');
    header('Cache-Control: private, max-age=86400');
    header('Content-Length: ' . filesize($file));
    readfile($file);
    exit;

  default:
    out(['ok' => false, 'error' => 'unknown_action'], 400);
}

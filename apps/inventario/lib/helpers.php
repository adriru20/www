<?php
// Utilidades generales de la app de inventario.

function h($v): string {
  return htmlspecialchars((string) ($v ?? ''), ENT_QUOTES, 'UTF-8');
}

// "A, B , C" -> ['A','B','C'] (sin vacíos ni repetidos)
function inv_split_list(?string $s): array {
  $out = [];
  foreach (explode(',', (string) $s) as $p) {
    $p = trim($p);
    if ($p !== '' && !in_array($p, $out, true)) $out[] = $p;
  }
  return $out;
}

// Construye una URL "?..." conservando los parámetros actuales y aplicando cambios (null = quitar)
function inv_url(array $updates = []): string {
  $params = $_GET;
  foreach ($updates as $k => $v) {
    if ($v === null || $v === '') unset($params[$k]); else $params[$k] = $v;
  }
  $q = http_build_query($params);
  return '?' . $q;
}

// URL de una imagen guardada (nombre en img/ o URL http). $thumb=true -> miniatura
function inv_img_url(?string $val, bool $thumb = false): string {
  $val = trim((string) $val);
  if ($val === '') return '';
  if (stripos($val, 'http') === 0) return $val;
  $name = basename($val);
  return $thumb ? 'thumb.php?f=' . rawurlencode($name) : 'img/' . rawurlencode($name);
}

function inv_emoji(?string $cat): string {
  $cat = mb_strtolower(trim((string) $cat));
  if ($cat === '') return '📍';
  $map = [
    '📦' => ['caja'], '🗃️' => ['trastero'], '🛏️' => ['canapé', 'canape'],
    '💿' => ['disquetera', 'cd', 'dvd'], '📚' => ['estanter', 'balda', 'librer'],
    '🗄️' => ['cajón', 'cajon', 'gaveta'], '🚪' => ['mueble', 'armario'],
    '📁' => ['funda', 'carpeta', 'archivador'], '🚗' => ['coche', 'maletero'],
    '🎒' => ['maleta', 'mochila', 'bolsa'],
  ];
  foreach ($map as $emoji => $words) {
    foreach ($words as $w) if (str_contains($cat, $w)) return $emoji;
  }
  return '📍';
}

// --- Avisos (se muestran una vez tras redirigir) ---
function inv_flash(string $msg, string $type = 'success'): void {
  $_SESSION['inv_flash'][] = ['msg' => $msg, 'type' => $type];
}
function inv_take_flash(): array {
  $f = $_SESSION['inv_flash'] ?? [];
  unset($_SESSION['inv_flash']);
  return $f;
}

// Redirige tras una acción (patrón POST-redirect-GET). $return = "?tab=...&p=2" validado.
function inv_redirect(string $return = '', string $fallback = '?tab=objetos'): void {
  $target = preg_match('/^\?[A-Za-z0-9_=&%\[\]\-\.+]*$/', $return) ? $return : $fallback;
  header('Location: index.php' . $target);
  exit();
}

<?php
// api/file.php?name=foto.png: sirve un adjunto de la wiki (imagen, PDF, audio...) buscándolo POR NOMBRE en todo el vault,
// como hace Obsidian. Si OneDrive está conectado lo busca allí (y lo baja la primera vez); si no, en el vault del servidor.
require_once __DIR__ . '/../../../backend/config/bootstrap.php';
require_once __DIR__ . '/../../../backend/lib/onedrive.php';
require_app_api('wiki');
session_write_close();                      // no bloquear otras peticiones mientras se descarga

function wiki_404(string $msg = 'Archivo no encontrado.'): void { http_response_code(404); header('Content-Type: text/plain; charset=utf-8'); echo $msg; exit(); }

$name = basename(str_replace('\\', '/', (string) ($_GET['name'] ?? '')));
if (!od_safe_name($name) || strtolower(pathinfo($name, PATHINFO_EXTENSION)) === 'md') wiki_404();

$path = null;
if (od_config() && od_is_connected() && ($f = od_find_remote_file($name))) {
  $r = od_cached_file($f);
  if ($r['ok']) $path = $r['path']; else if (!od_local_find($name)) wiki_404($r['error']);
}
$path = $path ?? od_local_find($name);
if (!$path || !is_file($path)) wiki_404();

// Tipo de contenido por extensión (nunca se confía en el contenido). Solo se muestran «en la página» los tipos seguros.
$types = ['png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'gif' => 'image/gif', 'webp' => 'image/webp', 'bmp' => 'image/bmp',
          'avif' => 'image/avif', 'svg' => 'image/svg+xml', 'pdf' => 'application/pdf',
          'mp3' => 'audio/mpeg', 'm4a' => 'audio/mp4', 'wav' => 'audio/wav', 'ogg' => 'audio/ogg', 'oga' => 'audio/ogg', 'flac' => 'audio/flac',
          'mp4' => 'video/mp4', 'm4v' => 'video/mp4', 'webm' => 'video/webm', 'mov' => 'video/quicktime', 'ogv' => 'video/ogg'];
$ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
$type = $types[$ext] ?? 'application/octet-stream';
$inline = isset($types[$ext]);

header('X-Content-Type-Options: nosniff');
header('Content-Type: ' . $type);
header('Content-Disposition: ' . ($inline ? 'inline' : 'attachment') . '; filename="' . rawurlencode($name) . '"; filename*=UTF-8\'\'' . rawurlencode($name));
if ($ext === 'svg') header("Content-Security-Policy: sandbox; default-src 'none'; style-src 'unsafe-inline'");   // un SVG abierto directamente no puede ejecutar nada
header('Cache-Control: private, max-age=300');
$size = filesize($path);
$etag = '"' . md5($size . '|' . filemtime($path) . '|' . $name) . '"';
header('ETag: ' . $etag);
header('Accept-Ranges: bytes');
if (($_SERVER['HTTP_IF_NONE_MATCH'] ?? '') === $etag) { http_response_code(304); exit(); }

// Rangos (para poder adelantar audio y vídeo)
$start = 0; $end = $size - 1;
if (isset($_SERVER['HTTP_RANGE']) && preg_match('/^bytes=(\d*)-(\d*)$/', $_SERVER['HTTP_RANGE'], $m) && ($m[1] !== '' || $m[2] !== '')) {
  if ($m[1] === '') { $start = max(0, $size - (int) $m[2]); } else { $start = (int) $m[1]; if ($m[2] !== '') $end = min($end, (int) $m[2]); }
  if ($start > $end || $start >= $size) { http_response_code(416); header('Content-Range: bytes */' . $size); exit(); }
  http_response_code(206);
  header('Content-Range: bytes ' . $start . '-' . $end . '/' . $size);
}
header('Content-Length: ' . ($end - $start + 1));
$h = fopen($path, 'rb');
fseek($h, $start);
$left = $end - $start + 1;
while ($left > 0 && !feof($h)) { $chunk = fread($h, min(8192, $left)); echo $chunk; $left -= strlen($chunk); }
fclose($h);

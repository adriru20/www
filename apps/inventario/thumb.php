<?php
// Miniaturas con caché: thumb.php?f=foto.jpg  (genera img/_thumbs/<ancho>_foto.jpg la primera vez)
require_once __DIR__ . '/lib/config.php';

$name = basename((string) ($_GET['f'] ?? ''));
$ext  = strtolower(pathinfo($name, PATHINFO_EXTENSION));
$src  = INV_IMG_DIR . $name;
if ($name === '' || !in_array($ext, INV_IMG_EXT, true) || !is_file($src)) {
  http_response_code(404);
  exit;
}

$types = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'gif' => 'image/gif', 'webp' => 'image/webp'];
header('Cache-Control: public, max-age=86400');

$cacheDir = INV_IMG_DIR . '_thumbs/';
$cache = $cacheDir . INV_THUMB_W . '_' . $name;

function serve(string $file, string $mime): void {
  header('Content-Type: ' . $mime);
  header('Content-Length: ' . filesize($file));
  readfile($file);
  exit;
}

if (is_file($cache) && filemtime($cache) >= filemtime($src)) serve($cache, $types[$ext]);

// Sin GD o formato no soportado: se sirve el original
if (!extension_loaded('gd') || $ext === 'gif') serve($src, $types[$ext]);

$img = match ($ext) {
  'jpg', 'jpeg' => @imagecreatefromjpeg($src),
  'png'         => @imagecreatefrompng($src),
  'webp'        => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($src) : false,
  default       => false,
};
if (!$img) serve($src, $types[$ext]);

// Respeta la orientación EXIF de las fotos del móvil
if (in_array($ext, ['jpg', 'jpeg'], true) && function_exists('exif_read_data')) {
  $exif = @exif_read_data($src);
  $rot = [3 => 180, 6 => -90, 8 => 90][$exif['Orientation'] ?? 1] ?? 0;
  if ($rot) { $img = imagerotate($img, $rot, 0); }
}

$w = imagesx($img); $h = imagesy($img);
if ($w <= INV_THUMB_W) serve($src, $types[$ext]);
$nw = INV_THUMB_W; $nh = (int) round($h * $nw / $w);
$thumb = imagecreatetruecolor($nw, $nh);
if ($ext === 'png' || $ext === 'webp') { imagealphablending($thumb, false); imagesavealpha($thumb, true); }
imagecopyresampled($thumb, $img, 0, 0, 0, 0, $nw, $nh, $w, $h);

if (!is_dir($cacheDir)) @mkdir($cacheDir, 0755, true);
match ($ext) {
  'jpg', 'jpeg' => imagejpeg($thumb, $cache, 80),
  'png'         => imagepng($thumb, $cache, 7),
  'webp'        => imagewebp($thumb, $cache, 80),
};
imagedestroy($img); imagedestroy($thumb);
is_file($cache) ? serve($cache, $types[$ext]) : serve($src, $types[$ext]);

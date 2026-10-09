<?php
// Gestión de imágenes: listado, subida validada, borrado, renombrado y uso en la BD.

function inv_list_images(): array {
  $out = [];
  if (!is_dir(INV_IMG_DIR)) @mkdir(INV_IMG_DIR, 0755, true);
  foreach (scandir(INV_IMG_DIR) as $f) {
    if ($f[0] === '.' || !is_file(INV_IMG_DIR . $f)) continue;
    if (in_array(strtolower(pathinfo($f, PATHINFO_EXTENSION)), INV_IMG_EXT, true)) $out[] = $f;
  }
  natcasesort($out);
  return array_values($out);
}

function inv_clean_name(string $s): string {
  return preg_replace('/[^a-zA-Z0-9\-_]/', '', $s);
}

// Nombre libre en img/: si ya existe, añade _2, _3...
function inv_unique_name(string $base, string $ext): string {
  $name = $base . '.' . $ext; $i = 2;
  while (file_exists(INV_IMG_DIR . $name)) $name = $base . '_' . $i++ . '.' . $ext;
  return $name;
}

// Sube UN fichero de $_FILES (formato de un solo archivo). Devuelve el nombre guardado o null.
function inv_upload_image(array $f, string $customName = '', ?string &$error = null): ?string {
  if (empty($f['name']) || ($f['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) return null;
  if ($f['error'] !== UPLOAD_ERR_OK) { $error = 'Error al subir «' . $f['name'] . '».'; return null; }

  $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
  if (!in_array($ext, INV_IMG_EXT, true)) { $error = '«' . $f['name'] . '»: solo se admiten ' . implode(', ', INV_IMG_EXT) . '.'; return null; }
  if ($f['size'] > INV_MAX_UPLOAD) { $error = '«' . $f['name'] . '» pesa más de 15 MB.'; return null; }
  $info = @getimagesize($f['tmp_name']);
  if ($info === false || !in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_GIF, IMAGETYPE_WEBP], true)) {
    $error = '«' . $f['name'] . '» no es una imagen válida.'; return null;
  }

  $base = $customName !== '' ? inv_clean_name($customName) : inv_clean_name(pathinfo($f['name'], PATHINFO_FILENAME));
  if ($base === '') $base = 'img_' . date('Ymd_His');
  if (!is_dir(INV_IMG_DIR)) @mkdir(INV_IMG_DIR, 0755, true);
  $name = inv_unique_name($base, $ext);
  return move_uploaded_file($f['tmp_name'], INV_IMG_DIR . $name) ? $name : null;
}

// Foto de portada de un formulario: prueba la cámara y luego el archivo elegido
function inv_upload_dual(array $cam, array $folder, string $customName, ?string &$error = null): ?string {
  return inv_upload_image($cam, $customName, $error) ?? inv_upload_image($folder, $customName, $error);
}

// Normaliza $_FILES['x'] con multiple (name[0], name[1]...) a una lista de ficheros sueltos
function inv_files_list(array $f): array {
  if (!isset($f['name'])) return [];
  if (!is_array($f['name'])) return [$f];
  $out = [];
  foreach ($f['name'] as $i => $_) {
    $out[] = ['name' => $f['name'][$i], 'type' => $f['type'][$i], 'tmp_name' => $f['tmp_name'][$i], 'error' => $f['error'][$i], 'size' => $f['size'][$i]];
  }
  return $out;
}

function inv_delete_image(string $name): bool {
  $name = basename($name);
  $path = INV_IMG_DIR . $name;
  if (!is_file($path)) return false;
  foreach (glob(INV_IMG_DIR . '_thumbs/*_' . $name) ?: [] as $t) @unlink($t);
  return @unlink($path);
}

// Renombra la imagen y actualiza las referencias en la BD (antes quedaban rotas)
function inv_rename_image(string $old, string $newBase): ?string {
  $old = basename($old);
  $newBase = inv_clean_name($newBase);
  $ext = strtolower(pathinfo($old, PATHINFO_EXTENSION));
  if ($newBase === '' || !is_file(INV_IMG_DIR . $old)) return null;
  $new = $newBase . '.' . $ext;
  if ($new === $old) return $old;
  if (file_exists(INV_IMG_DIR . $new)) return null;
  if (!@rename(INV_IMG_DIR . $old, INV_IMG_DIR . $new)) return null;
  foreach (glob(INV_IMG_DIR . '_thumbs/*_' . $old) ?: [] as $t) @unlink($t);
  inv_exec('UPDATE inv_objetos SET portada_http = ? WHERE portada_http = ?', 'ss', [$new, $old]);
  inv_exec('UPDATE inv_localizaciones SET foto_http = ? WHERE foto_http = ?', 'ss', [$new, $old]);
  return $new;
}

// Cuántos objetos/localizaciones usan cada imagen: ['foto.jpg' => 2, ...]
// Devuelve null si falla la base de datos (así nunca se confunde "no se pudo comprobar" con "sin usar").
function inv_image_usage(): ?array {
  $use = [];
  foreach (['SELECT portada_http AS f FROM inv_objetos', 'SELECT foto_http AS f FROM inv_localizaciones'] as $sql) {
    $r = inv_query($sql);
    if (!$r) return null;
    while ($row = $r->fetch_assoc()) {
      $f = trim((string) $row['f']);
      if ($f !== '' && stripos($f, 'http') !== 0) { $f = basename($f); $use[$f] = ($use[$f] ?? 0) + 1; }
    }
  }
  return $use;
}

// Imágenes de img/ que ningún objeto ni localización usa (sin distinguir mayúsculas, por seguridad).
// null si no se pudo comprobar el uso.
function inv_unused_images(?array $images = null, ?array $usage = null): ?array {
  $usage ??= inv_image_usage();
  if ($usage === null) return null;
  $used = array_change_key_case($usage, CASE_LOWER);
  return array_values(array_filter($images ?? inv_list_images(), fn($n) => !isset($used[strtolower($n)])));
}

// Espacio que ocupan estas imágenes (bytes)
function inv_images_size(array $names): int {
  $sum = 0;
  foreach ($names as $n) $sum += (int) @filesize(INV_IMG_DIR . basename($n));
  return $sum;
}

function inv_format_bytes(int $b): string {
  if ($b >= 1048576) return number_format($b / 1048576, 1, ',', '.') . ' MB';
  if ($b >= 1024) return number_format($b / 1024, 0, ',', '.') . ' KB';
  return $b . ' B';
}

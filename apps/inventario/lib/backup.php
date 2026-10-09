<?php
// Exportación a CSV y copias de seguridad en ZIP (usado por index.php y csv.php).

function inv_csv_localizaciones($out): void {
  fputcsv($out, ['portada_http', 'nombre', 'descripcion_del_contenido', 'categoria']);
  foreach (inv_rows(inv_query('SELECT foto_http, nombre, descripcion_del_contenido, categoria FROM inv_localizaciones ORDER BY id ASC')) as $r) {
    fputcsv($out, [basename($r['foto_http'] ?? ''), $r['nombre'] ?? '', $r['descripcion_del_contenido'] ?? '', $r['categoria'] ?? '']);
  }
}

function inv_csv_objetos($out): void {
  fputcsv($out, ['portada_http', 'objeto', 'objeto_v2', 'objeto_v3', 'localizacion', 'descripcion', 'tipo', 'tipo_de_objeto', 'cantidad',
                 'generos', 'plataformas', 'anio_de_estreno', 'formato', 'precio_de_venta', 'duracion', 'formato_de_archivo', 'en_la_caja']);
  $locsOf = inv_locations_of();
  foreach (inv_rows(inv_query('SELECT * FROM inv_objetos ORDER BY id ASC')) as $r) {
    fputcsv($out, [basename($r['portada_http'] ?? ''), $r['objeto'] ?? '', '', '', implode(', ', $locsOf[(int) $r['id']] ?? []), $r['descripcion'] ?? '',
                   $r['tipo'] ?? '', $r['tipo_de_objeto'] ?? '', $r['cantidad'] ?? 1, $r['generos'] ?? '', $r['plataformas'] ?? '',
                   $r['anio_de_estreno'] ?? '', $r['formato'] ?? '', $r['precio_de_venta'] ?? '0.00', $r['duracion'] ?? '',
                   $r['formato_de_archivo'] ?? '', $r['en_la_caja'] ?? 0]);
  }
}

function inv_backup_dir(): string {
  if (!is_dir(INV_BACKUP_DIR)) {
    @mkdir(INV_BACKUP_DIR, 0750, true);
  }
  // Los ZIP no se sirven por URL: se descargan desde csv.php (con sesión)
  if (!is_file(INV_BACKUP_DIR . '.htaccess')) @file_put_contents(INV_BACKUP_DIR . '.htaccess', "Require all denied\n");
  return INV_BACKUP_DIR;
}

function inv_backup_zip(string $dest): bool {
  $a = tmpfile(); $b = tmpfile();
  inv_csv_localizaciones($a); inv_csv_objetos($b);
  $zip = new ZipArchive();
  $ok = false;
  if ($zip->open($dest, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true) {
    $zip->addFile(stream_get_meta_data($a)['uri'], 'localizaciones.csv');
    $zip->addFile(stream_get_meta_data($b)['uri'], 'objetos.csv');
    $ok = $zip->close();
  }
  fclose($a); fclose($b);
  return $ok;
}

// Copia semanal automática: se crea al abrir el inventario si la última tiene más de INV_BACKUP_DAYS días
function inv_auto_backup(): ?string {
  if (!class_exists('ZipArchive')) return null;
  $dir = inv_backup_dir();
  if (!is_dir($dir) || !is_writable($dir)) return null;
  $zips = glob($dir . '*.zip') ?: [];
  $last = $zips ? max(array_map('filemtime', $zips)) : 0;
  if ($last > time() - INV_BACKUP_DAYS * 86400) return null;
  $name = date('Y-m-d_H-i-s') . '_auto_inventario.zip';
  if (!inv_backup_zip($dir . $name)) return null;
  // Conserva solo las últimas copias automáticas
  $auto = glob($dir . '*_auto_inventario.zip') ?: [];
  sort($auto);
  foreach (array_slice($auto, 0, max(0, count($auto) - INV_BACKUP_KEEP)) as $old) @unlink($old);
  return $name;
}

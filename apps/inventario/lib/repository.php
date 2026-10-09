<?php
// Acceso a datos del inventario: toda la SQL está aquí, siempre con consultas preparadas.

// SELECT (o cualquier consulta con resultado). Devuelve mysqli_result|false
function inv_query(string $sql, string $types = '', array $params = []) {
  global $conn;
  $stmt = $conn->prepare($sql);
  if (!$stmt) return false;
  if ($types !== '') $stmt->bind_param($types, ...$params);
  if (!$stmt->execute()) return false;
  return $stmt->get_result();
}

// INSERT/UPDATE/DELETE. Devuelve ['ok','affected','insert_id','error']
function inv_exec(string $sql, string $types = '', array $params = []): array {
  global $conn;
  $stmt = $conn->prepare($sql);
  if (!$stmt) return ['ok' => false, 'affected' => 0, 'insert_id' => 0, 'error' => (string) $conn->error];
  if ($types !== '') $stmt->bind_param($types, ...$params);
  $ok = $stmt->execute();
  $res = ['ok' => (bool) $ok, 'affected' => (int) $stmt->affected_rows, 'insert_id' => (int) $stmt->insert_id, 'error' => (string) $stmt->error];
  $stmt->close();
  return $res;
}

function inv_rows($result): array {
  $rows = [];
  while ($result && ($r = $result->fetch_assoc())) $rows[] = $r;
  return $rows;
}

// ---------------------------------------------------------------- OBJETOS

// Filtros de la lista de objetos a partir de $_GET
function inv_obj_filters(): array {
  $f_loc = $_GET['f_loc'] ?? [];
  $f_loc = array_values(array_filter(is_array($f_loc) ? $f_loc : [$f_loc], fn($v) => is_string($v) && $v !== ''));
  return [
    'q'      => trim((string) ($_GET['q'] ?? '')),
    'f_loc'  => $f_loc,
    'f_tipo' => (string) ($_GET['f_tipo'] ?? ''),
    'f_cat'  => (string) ($_GET['f_t_obj'] ?? ''),
    'ver'    => in_array($_GET['ver'] ?? '', ['venta', 'sin_foto', 'sin_loc'], true) ? $_GET['ver'] : '',
    'sort'   => (string) ($_GET['sort'] ?? 'newest'),
  ];
}

function inv_obj_list(array $f, int $page, int $size = INV_PAGE_SIZE): array {
  $where = []; $types = ''; $params = [];
  if ($f['q'] !== '') {
    $like = '%' . $f['q'] . '%';
    $where[] = '(objeto LIKE ? OR descripcion LIKE ? OR plataformas LIKE ? OR tipo_de_objeto LIKE ?
                 OR EXISTS (SELECT 1 FROM inv_objeto_localizacion j JOIN inv_localizaciones l ON l.id = j.localizacion_id
                            WHERE j.objeto_id = inv_objetos.id AND l.nombre LIKE ?))';
    $types .= 'sssss'; array_push($params, $like, $like, $like, $like, $like);
  }
  if ($f['f_loc']) {
    $in = implode(',', array_fill(0, count($f['f_loc']), '?'));
    $where[] = "EXISTS (SELECT 1 FROM inv_objeto_localizacion j JOIN inv_localizaciones l ON l.id = j.localizacion_id
                        WHERE j.objeto_id = inv_objetos.id AND l.nombre IN ($in))";
    $types .= str_repeat('s', count($f['f_loc'])); array_push($params, ...$f['f_loc']);
  }
  if ($f['f_tipo'] !== '') { $where[] = 'tipo = ?'; $types .= 's'; $params[] = $f['f_tipo']; }
  if ($f['f_cat'] !== '')  { $where[] = 'tipo_de_objeto = ?'; $types .= 's'; $params[] = $f['f_cat']; }
  if ($f['ver'] === 'venta')     $where[] = 'CAST(precio_de_venta AS DECIMAL(10,2)) > 0';
  if ($f['ver'] === 'sin_foto')  $where[] = "(portada_http IS NULL OR portada_http = '')";
  if ($f['ver'] === 'sin_loc')   $where[] = 'NOT EXISTS (SELECT 1 FROM inv_objeto_localizacion j WHERE j.objeto_id = inv_objetos.id)';
  $w = $where ? 'WHERE ' . implode(' AND ', $where) : '';

  $order = match ($f['sort']) {
    'nombre_asc'  => 'ORDER BY objeto ASC',
    'tipo_nombre' => 'ORDER BY tipo ASC, objeto ASC',
    'precio_desc' => 'ORDER BY CAST(precio_de_venta AS DECIMAL(10,2)) DESC, objeto ASC',
    default       => 'ORDER BY id DESC',
  };

  $total = (int) (inv_query("SELECT COUNT(*) AS total FROM inv_objetos $w", $types, $params)->fetch_assoc()['total'] ?? 0);
  $pages = max(1, (int) ceil($total / $size));
  $page = min(max(1, $page), $pages);
  $offset = ($page - 1) * $size;
  $rows = inv_rows(inv_query("SELECT * FROM inv_objetos $w $order LIMIT $offset, $size", $types, $params));
  inv_attach_locations($rows);
  return ['rows' => $rows, 'total' => $total, 'pages' => $pages, 'page' => $page];
}

function inv_obj_get(int $id): ?array {
  $o = inv_query('SELECT * FROM inv_objetos WHERE id = ?', 'i', [$id])->fetch_assoc();
  if (!$o) return null;
  $rows = [$o]; inv_attach_locations($rows);
  return $rows[0];
}

// ---- Localizaciones de cada objeto (tabla de relación inv_objeto_localizacion)

// Pone en cada fila el campo 'localizacion' = "Caja 1, Estantería" leído de la relación
function inv_attach_locations(array &$rows): void {
  if (!$rows) return;
  $ids = array_map(fn($r) => (int) $r['id'], $rows);
  $map = inv_locations_of($ids);
  foreach ($rows as &$r) $r['localizacion'] = implode(', ', $map[(int) $r['id']] ?? []);
}

// [objeto_id => ['Caja 1', 'Estantería']] (todos los objetos si $ids es null)
function inv_locations_of(?array $ids = null): array {
  $sql = 'SELECT j.objeto_id, l.nombre FROM inv_objeto_localizacion j JOIN inv_localizaciones l ON l.id = j.localizacion_id';
  $types = ''; $params = [];
  if ($ids !== null) {
    if (!$ids) return [];
    $sql .= ' WHERE j.objeto_id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')';
    $types = str_repeat('i', count($ids)); $params = array_values($ids);
  }
  $map = [];
  foreach (inv_rows(inv_query($sql . ' ORDER BY l.nombre', $types, $params)) as $r) $map[(int) $r['objeto_id']][] = $r['nombre'];
  return $map;
}

// id de una localización por nombre; si no existe se crea (así nunca quedan nombres sueltos)
function inv_location_id(string $nombre, bool $create = true): ?int {
  $nombre = trim($nombre);
  if ($nombre === '') return null;
  $r = inv_query('SELECT id FROM inv_localizaciones WHERE LOWER(nombre) = LOWER(?)', 's', [$nombre]);
  if ($r && ($row = $r->fetch_assoc())) return (int) $row['id'];
  if (!$create) return null;
  $x = inv_exec('INSERT INTO inv_localizaciones (nombre) VALUES (?)', 's', [$nombre]);
  return $x['ok'] ? $x['insert_id'] : null;
}

// Sustituye las localizaciones de un objeto por la lista de nombres dada. Devuelve cuántas se han creado nuevas.
function inv_obj_set_locations(int $objId, array $names): int {
  $before = (int) (inv_query('SELECT COUNT(*) AS n FROM inv_localizaciones')?->fetch_assoc()['n'] ?? 0);
  inv_exec('DELETE FROM inv_objeto_localizacion WHERE objeto_id = ?', 'i', [$objId]);
  $done = [];
  foreach ($names as $n) {
    $lid = inv_location_id($n);
    if ($lid && !isset($done[$lid])) { $done[$lid] = true; inv_exec('INSERT INTO inv_objeto_localizacion (objeto_id, localizacion_id) VALUES (?, ?)', 'ii', [$objId, $lid]); }
  }
  inv_sync_location_text([$objId]);
  return (int) (inv_query('SELECT COUNT(*) AS n FROM inv_localizaciones')?->fetch_assoc()['n'] ?? 0) - $before;
}

// Copia de seguridad de la relación en el texto antiguo (columna inv_objetos.localizacion):
// la lectura usa solo la relación; el texto se mantiene por si hubiera que volver atrás.
function inv_sync_location_text(array $objIds): void {
  $map = inv_locations_of($objIds ?: null);
  foreach ($objIds as $id) inv_exec('UPDATE inv_objetos SET localizacion = ? WHERE id = ?', 'si', [implode(', ', $map[(int) $id] ?? []), (int) $id]);
}

function inv_objects_in_location(int $locId): array {
  return array_map('intval', array_column(inv_rows(inv_query('SELECT objeto_id FROM inv_objeto_localizacion WHERE localizacion_id = ?', 'i', [$locId])), 'objeto_id'));
}

// Objetos (filas completas, con su texto de localizaciones) que están en una localización
function inv_objects_in_location_rows(int $locId): array {
  $r = inv_query('SELECT o.* FROM inv_objetos o JOIN inv_objeto_localizacion j ON j.objeto_id = o.id WHERE j.localizacion_id = ? ORDER BY o.objeto ASC, o.id ASC', 'i', [$locId]);
  $rows = $r ? inv_rows($r) : [];
  inv_attach_locations($rows);
  return $rows;
}

// Valores únicos para desplegables y sugerencias
function inv_options(): array {
  $col = fn(string $sql, string $c) => array_values(array_filter(array_column(inv_rows(inv_query($sql)), $c), fn($v) => $v !== null && $v !== ''));
  $tags = function (string $c): array {
    $set = [];
    foreach (inv_rows(inv_query("SELECT DISTINCT $c FROM inv_objetos WHERE $c IS NOT NULL AND $c != ''")) as $r)
      foreach (inv_split_list($r[$c]) as $t) $set[$t] = true;
    $out = array_keys($set); sort($out, SORT_NATURAL | SORT_FLAG_CASE);
    return $out;
  };
  return [
    'locs'  => $col('SELECT nombre FROM inv_localizaciones ORDER BY nombre ASC', 'nombre'),
    'tipos' => $col("SELECT DISTINCT tipo FROM inv_objetos WHERE tipo IS NOT NULL AND tipo != '' ORDER BY tipo", 'tipo'),
    'cats'  => $col("SELECT DISTINCT tipo_de_objeto FROM inv_objetos WHERE tipo_de_objeto IS NOT NULL AND tipo_de_objeto != '' ORDER BY tipo_de_objeto", 'tipo_de_objeto'),
    'plats' => $tags('plataformas'),
    'gens'  => $tags('generos'),
    'fa'    => $col("SELECT DISTINCT formato_de_archivo FROM inv_objetos WHERE formato_de_archivo IS NOT NULL AND formato_de_archivo != '' ORDER BY formato_de_archivo", 'formato_de_archivo'),
    'loc_cats' => $col("SELECT DISTINCT categoria FROM inv_localizaciones WHERE categoria IS NOT NULL AND categoria != '' ORDER BY categoria", 'categoria'),
  ];
}

// Valida y guarda un objeto. Devuelve [true, id] o [false, mensaje de error]
function inv_obj_save(array $in, string $portada, ?int $id): array {
  $titulo = trim((string) ($in['titulo'] ?? ''));
  if ($titulo === '') return [false, 'El título es obligatorio.'];
  $tipo = in_array($in['tipo'] ?? '', INV_TIPOS, true) ? $in['tipo'] : 'Objetos';
  $formato = in_array($in['formato'] ?? '', INV_FORMATOS, true) ? $in['formato'] : 'Físico';
  $cantidad = max(1, (int) ($in['cantidad'] ?? 1));
  $caja = !empty($in['en_la_caja']) ? 1 : 0;
  $precio = max(0, (float) str_replace(',', '.', (string) ($in['precio_de_venta'] ?? 0)));
  $locNames = inv_split_list($in['localizacion'] ?? '');
  $plat = implode(', ', inv_split_list($in['plataformas'] ?? ''));
  $gen  = implode(', ', inv_split_list($in['generos'] ?? ''));
  $desc = trim((string) ($in['descripcion'] ?? ''));
  $cat  = trim((string) ($in['tipo_de_objeto'] ?? ''));
  $fa   = trim((string) ($in['formato_de_archivo'] ?? ''));

  $vals = [$titulo, $desc, $tipo, $cat, $plat, $portada, $cantidad, $gen, $formato, $fa, $caja, $precio];
  if ($id) {
    $r = inv_exec('UPDATE inv_objetos SET objeto=?, descripcion=?, tipo=?, tipo_de_objeto=?, plataformas=?, portada_http=?, cantidad=?, generos=?, formato=?, formato_de_archivo=?, en_la_caja=?, precio_de_venta=? WHERE id=?',
      'ssssssisssidi', array_merge($vals, [$id]));
  } else {
    $r = inv_exec('INSERT INTO inv_objetos (objeto, descripcion, tipo, tipo_de_objeto, plataformas, portada_http, cantidad, generos, formato, formato_de_archivo, en_la_caja, precio_de_venta) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
      'ssssssisssid', $vals);
  }
  if (!$r['ok']) return [false, 'No se pudo guardar el objeto.'];
  $objId = $id ?: $r['insert_id'];
  $created = inv_obj_set_locations((int) $objId, $locNames);
  return [true, $objId, $created];
}

function inv_obj_delete(int $id): ?string {
  $o = inv_obj_get($id);
  if (!$o) return null;
  inv_exec('DELETE FROM inv_objetos WHERE id = ?', 'i', [$id]);
  return (string) ($o['objeto'] ?? '');
}

function inv_title_exists(string $titulo, ?int $exceptId = null): bool {
  $r = inv_query('SELECT id FROM inv_objetos WHERE LOWER(objeto) = LOWER(?) AND id != ? LIMIT 1', 'si', [$titulo, (int) $exceptId]);
  return (bool) ($r && $r->fetch_assoc());
}

// ---------------------------------------------------------------- LOCALIZACIONES

function inv_loc_filters(): array {
  return [
    'q'    => trim((string) ($_GET['q_loc'] ?? '')),
    'cat'  => (string) ($_GET['f_cat_loc'] ?? ''),
    'sort' => (string) ($_GET['sort_loc'] ?? 'cat_nombre'),
  ];
}

function inv_loc_list(array $f, int $page, int $size = INV_PAGE_SIZE): array {
  $where = []; $types = ''; $params = [];
  if ($f['q'] !== '') {
    $like = '%' . $f['q'] . '%';
    $where[] = '(nombre LIKE ? OR descripcion_del_contenido LIKE ? OR categoria LIKE ?)';
    $types .= 'sss'; array_push($params, $like, $like, $like);
  }
  if ($f['cat'] !== '') { $where[] = 'categoria = ?'; $types .= 's'; $params[] = $f['cat']; }
  $w = $where ? 'WHERE ' . implode(' AND ', $where) : '';
  $order = match ($f['sort']) {
    'nombre_asc'  => 'ORDER BY nombre ASC',
    'nombre_desc' => 'ORDER BY nombre DESC',
    'newest'      => 'ORDER BY id DESC',
    default       => 'ORDER BY categoria ASC, nombre ASC',
  };
  $total = (int) (inv_query("SELECT COUNT(*) AS total FROM inv_localizaciones $w", $types, $params)->fetch_assoc()['total'] ?? 0);
  $pages = max(1, (int) ceil($total / $size));
  $page = min(max(1, $page), $pages);
  $offset = ($page - 1) * $size;
  $rows = inv_rows(inv_query("SELECT * FROM inv_localizaciones $w $order LIMIT $offset, $size", $types, $params));
  return ['rows' => $rows, 'total' => $total, 'pages' => $pages, 'page' => $page];
}

// Cuántos objetos hay en cada localización: ['Caja 1' => 5, ...]
function inv_location_usage(): array {
  $use = [];
  foreach (inv_rows(inv_query('SELECT l.nombre, COUNT(*) AS n FROM inv_objeto_localizacion j JOIN inv_localizaciones l ON l.id = j.localizacion_id GROUP BY l.id, l.nombre')) as $r)
    $use[$r['nombre']] = (int) $r['n'];
  return $use;
}

function inv_loc_get(int $id): ?array {
  return inv_query('SELECT * FROM inv_localizaciones WHERE id = ?', 'i', [$id])->fetch_assoc() ?: null;
}

// Devuelve [true, id, nº de objetos actualizados] o [false, error]
function inv_loc_save(array $in, string $foto, ?int $id): array {
  $nombre = trim((string) ($in['nombre'] ?? ''));
  if ($nombre === '') return [false, 'El nombre es obligatorio.'];
  if (str_contains($nombre, ',')) return [false, 'El nombre no puede llevar comas (se usan para separar localizaciones).'];
  $dup = inv_query('SELECT id FROM inv_localizaciones WHERE LOWER(nombre) = LOWER(?) AND id != ? LIMIT 1', 'si', [$nombre, (int) $id]);
  if ($dup && $dup->fetch_assoc()) return [false, "Ya existe una localización llamada «{$nombre}»."];

  $desc = trim((string) ($in['descripcion_del_contenido'] ?? ''));
  $cat  = trim((string) ($in['categoria'] ?? ''));
  $moved = 0;
  if ($id) {
    $old = inv_loc_get($id);
    $r = inv_exec('UPDATE inv_localizaciones SET nombre=?, descripcion_del_contenido=?, categoria=?, foto_http=? WHERE id=?', 'ssssi', [$nombre, $desc, $cat, $foto, $id]);
    if ($r['ok'] && $old && $old['nombre'] !== $nombre) { // los objetos siguen enlazados por id; solo se refresca el texto de copia
      $affected = inv_objects_in_location($id);
      inv_sync_location_text($affected);
      $moved = count($affected);
    }
  } else {
    $r = inv_exec('INSERT INTO inv_localizaciones (nombre, descripcion_del_contenido, categoria, foto_http) VALUES (?, ?, ?, ?)', 'ssss', [$nombre, $desc, $cat, $foto]);
  }
  return $r['ok'] ? [true, $id ?: $r['insert_id'], $moved] : [false, 'No se pudo guardar la localización.'];
}

function inv_loc_delete(int $id): ?array {
  $l = inv_loc_get($id);
  if (!$l) return null;
  $affected = inv_objects_in_location($id);
  inv_exec('DELETE FROM inv_objeto_localizacion WHERE localizacion_id = ?', 'i', [$id]);
  inv_exec('DELETE FROM inv_localizaciones WHERE id = ?', 'i', [$id]);
  inv_sync_location_text($affected);
  return ['nombre' => $l['nombre'], 'objetos' => count($affected)];
}

// ---------------------------------------------------------------- RESUMEN

function inv_stats(): array {
  $rows = inv_rows(inv_query('SELECT id, objeto, tipo, tipo_de_objeto, cantidad, portada_http, precio_de_venta FROM inv_objetos'));
  $locsOf = inv_locations_of();
  $s = ['objetos' => count($rows), 'unidades' => 0, 'por_tipo' => [], 'por_cat' => [], 'por_loc' => [],
        'sin_foto' => 0, 'sin_loc' => 0, 'venta_n' => 0, 'venta_total' => 0.0, 'localizaciones' => 0];
  foreach ($rows as $r) {
    $q = max(1, (int) $r['cantidad']);
    $s['unidades'] += $q;
    $tipo = $r['tipo'] ?: 'Sin tipo';
    $s['por_tipo'][$tipo] = ($s['por_tipo'][$tipo] ?? 0) + 1;
    if (($r['tipo_de_objeto'] ?? '') !== '') $s['por_cat'][$r['tipo_de_objeto']] = ($s['por_cat'][$r['tipo_de_objeto']] ?? 0) + 1;
    if (trim((string) $r['portada_http']) === '') $s['sin_foto']++;
    $locs = $locsOf[(int) $r['id']] ?? [];
    if (!$locs) $s['sin_loc']++;
    foreach ($locs as $l) $s['por_loc'][$l] = ($s['por_loc'][$l] ?? 0) + 1;
    $p = (float) $r['precio_de_venta'];
    if ($p > 0) { $s['venta_n']++; $s['venta_total'] += $p * $q; }
  }
  arsort($s['por_tipo']); arsort($s['por_cat']); arsort($s['por_loc']);
  $s['localizaciones'] = (int) (inv_query('SELECT COUNT(*) AS n FROM inv_localizaciones')?->fetch_assoc()['n'] ?? 0);
  return $s;
}

// ---------------------------------------------------------------- ESQUEMA

// ¿Está creada la tabla de relación objeto-localización? (la crea el SQL de migración)
function inv_schema_ready(): bool {
  return inv_query('SELECT 1 FROM inv_objeto_localizacion LIMIT 1') !== false;
}

// Si falta el SQL de migración se muestra este aviso en vez de fallar a medias
function inv_migration_page(): void {
  global $src;
  http_response_code(503);
  $page_title = 'Inventario';
  ?>
<!DOCTYPE html>
<html lang="es" data-bs-theme="dark">
<?php include __DIR__ . '/../../../backend/config/ini.php'; ?>
<body>
  <?php include __DIR__ . '/../../../frontend/menu.php'; ?>
  <main class="auth-wrap"><div class="card shadow auth-card text-center" style="max-width:560px">
    <h1 class="h4">Falta actualizar la base de datos</h1>
    <p class="text-muted mb-2">El inventario necesita ejecutar el SQL de migración (tabla de localizaciones separadas).</p>
    <p class="small text-muted mb-0">Ejecuta <code>backend/database/migraciones/002_roles_y_mejoras.sql</code> en phpMyAdmin y recarga esta página.</p>
  </div></main>
  <?php include __DIR__ . '/../../../frontend/footer.php'; ?>
</body>
</html>
<?php
  exit();
}

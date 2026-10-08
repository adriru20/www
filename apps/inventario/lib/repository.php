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
    $where[] = '(objeto LIKE ? OR descripcion LIKE ? OR plataformas LIKE ? OR localizacion LIKE ? OR tipo_de_objeto LIKE ?)';
    $types .= 'sssss'; array_push($params, $like, $like, $like, $like, $like);
  }
  if ($f['f_loc']) {
    $or = [];
    foreach ($f['f_loc'] as $loc) { $or[] = 'localizacion LIKE ?'; $types .= 's'; $params[] = '%' . $loc . '%'; }
    $where[] = '(' . implode(' OR ', $or) . ')';
  }
  if ($f['f_tipo'] !== '') { $where[] = 'tipo = ?'; $types .= 's'; $params[] = $f['f_tipo']; }
  if ($f['f_cat'] !== '')  { $where[] = 'tipo_de_objeto = ?'; $types .= 's'; $params[] = $f['f_cat']; }
  if ($f['ver'] === 'venta')     $where[] = 'CAST(precio_de_venta AS DECIMAL(10,2)) > 0';
  if ($f['ver'] === 'sin_foto')  $where[] = "(portada_http IS NULL OR portada_http = '')";
  if ($f['ver'] === 'sin_loc')   $where[] = "(localizacion IS NULL OR localizacion = '')";
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
  return ['rows' => $rows, 'total' => $total, 'pages' => $pages, 'page' => $page];
}

function inv_obj_get(int $id): ?array {
  return inv_query('SELECT * FROM inv_objetos WHERE id = ?', 'i', [$id])->fetch_assoc() ?: null;
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
  $loc  = implode(', ', inv_split_list($in['localizacion'] ?? ''));
  $plat = implode(', ', inv_split_list($in['plataformas'] ?? ''));
  $gen  = implode(', ', inv_split_list($in['generos'] ?? ''));
  $desc = trim((string) ($in['descripcion'] ?? ''));
  $cat  = trim((string) ($in['tipo_de_objeto'] ?? ''));
  $fa   = trim((string) ($in['formato_de_archivo'] ?? ''));

  $vals = [$titulo, $loc, $desc, $tipo, $cat, $plat, $portada, $cantidad, $gen, $formato, $fa, $caja, $precio];
  if ($id) {
    $r = inv_exec('UPDATE inv_objetos SET objeto=?, localizacion=?, descripcion=?, tipo=?, tipo_de_objeto=?, plataformas=?, portada_http=?, cantidad=?, generos=?, formato=?, formato_de_archivo=?, en_la_caja=?, precio_de_venta=? WHERE id=?',
      'sssssssisssidi', array_merge($vals, [$id]));
  } else {
    $r = inv_exec('INSERT INTO inv_objetos (objeto, localizacion, descripcion, tipo, tipo_de_objeto, plataformas, portada_http, cantidad, generos, formato, formato_de_archivo, en_la_caja, precio_de_venta) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
      'sssssssisssid', $vals);
  }
  return $r['ok'] ? [true, $id ?: $r['insert_id']] : [false, 'No se pudo guardar el objeto.'];
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
  foreach (inv_rows(inv_query("SELECT localizacion FROM inv_objetos WHERE localizacion IS NOT NULL AND localizacion != ''")) as $r)
    foreach (inv_split_list($r['localizacion']) as $l) $use[$l] = ($use[$l] ?? 0) + 1;
  return $use;
}

function inv_loc_get(int $id): ?array {
  return inv_query('SELECT * FROM inv_localizaciones WHERE id = ?', 'i', [$id])->fetch_assoc() ?: null;
}

// Si se renombra una localización, los objetos que la usan deben seguirla
function inv_loc_rename_in_objects(string $old, string $new): int {
  $n = 0;
  foreach (inv_rows(inv_query('SELECT id, localizacion FROM inv_objetos WHERE localizacion LIKE ?', 's', ['%' . $old . '%'])) as $o) {
    $list = inv_split_list($o['localizacion']);
    if (!in_array($old, $list, true)) continue;
    $list = array_map(fn($l) => $l === $old ? $new : $l, $list);
    inv_exec('UPDATE inv_objetos SET localizacion = ? WHERE id = ?', 'si', [implode(', ', array_unique($list)), (int) $o['id']]);
    $n++;
  }
  return $n;
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
    if ($r['ok'] && $old && $old['nombre'] !== $nombre) $moved = inv_loc_rename_in_objects($old['nombre'], $nombre);
  } else {
    $r = inv_exec('INSERT INTO inv_localizaciones (nombre, descripcion_del_contenido, categoria, foto_http) VALUES (?, ?, ?, ?)', 'ssss', [$nombre, $desc, $cat, $foto]);
  }
  return $r['ok'] ? [true, $id ?: $r['insert_id'], $moved] : [false, 'No se pudo guardar la localización.'];
}

function inv_loc_delete(int $id): ?array {
  $l = inv_loc_get($id);
  if (!$l) return null;
  inv_exec('DELETE FROM inv_localizaciones WHERE id = ?', 'i', [$id]);
  return ['nombre' => $l['nombre'], 'objetos' => inv_location_usage()[$l['nombre']] ?? 0];
}

// ---------------------------------------------------------------- RESUMEN

function inv_stats(): array {
  $rows = inv_rows(inv_query('SELECT id, objeto, localizacion, tipo, tipo_de_objeto, cantidad, portada_http, precio_de_venta FROM inv_objetos'));
  $s = ['objetos' => count($rows), 'unidades' => 0, 'por_tipo' => [], 'por_cat' => [], 'por_loc' => [],
        'sin_foto' => 0, 'sin_loc' => 0, 'venta_n' => 0, 'venta_total' => 0.0, 'localizaciones' => 0];
  foreach ($rows as $r) {
    $q = max(1, (int) $r['cantidad']);
    $s['unidades'] += $q;
    $tipo = $r['tipo'] ?: 'Sin tipo';
    $s['por_tipo'][$tipo] = ($s['por_tipo'][$tipo] ?? 0) + 1;
    if (($r['tipo_de_objeto'] ?? '') !== '') $s['por_cat'][$r['tipo_de_objeto']] = ($s['por_cat'][$r['tipo_de_objeto']] ?? 0) + 1;
    if (trim((string) $r['portada_http']) === '') $s['sin_foto']++;
    $locs = inv_split_list($r['localizacion']);
    if (!$locs) $s['sin_loc']++;
    foreach ($locs as $l) $s['por_loc'][$l] = ($s['por_loc'][$l] ?? 0) + 1;
    $p = (float) $r['precio_de_venta'];
    if ($p > 0) { $s['venta_n']++; $s['venta_total'] += $p * $q; }
  }
  arsort($s['por_tipo']); arsort($s['por_cat']); arsort($s['por_loc']);
  $s['localizaciones'] = (int) (inv_query('SELECT COUNT(*) AS n FROM inv_localizaciones')->fetch_assoc()['n'] ?? 0);
  return $s;
}

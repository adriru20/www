<?php
session_start();
$src = '../../';

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if (!isset($_SESSION['user_id'])) {
    header("Location: {$src}src/login/index.php");
    exit();
}

require $src . 'backend/config/db.php';
global $conn;

// IMPORTANTE: Eliminamos la restricción de Clave Foránea para permitir múltiples localizaciones separadas por coma
try {
    $conn->query("ALTER TABLE inv_objetos DROP FOREIGN KEY fk_loc_obj");
} catch(Exception $e) {
    // Si da error significa que ya se había borrado antes, lo ignoramos.
}

// --- CONFIGURACIÓN DE PARÁMETROS ---
$tab = $_GET['tab'] ?? 'objetos';
$page = max(1, isset($_GET['p']) ? (int)$_GET['p'] : 1);
$limit = 24; 
$offset = ($page - 1) * $limit;

// Parámetros de búsqueda y filtros
$search = $_GET['q'] ?? '';
$f_tipo = $_GET['f_tipo'] ?? '';
$f_t_obj = $_GET['f_t_obj'] ?? '';
$sort = $_GET['sort'] ?? 'newest';

function urlParam($updates) {
    $params = $_GET;
    foreach($updates as $k => $v) $params[$k] = $v;
    return '?' . http_build_query($params);
}

// --- ACCIONES DE BORRADO ---
if (isset($_GET['delete_obj'])) {
    $stmt = $conn->prepare("DELETE FROM inv_objetos WHERE id = ?");
    $stmt->bind_param("i", $_GET['delete_obj']);
    $stmt->execute();
    header("Location: " . urlParam(['delete_obj' => null])); exit();
}
if (isset($_GET['delete_loc'])) {
    $stmt = $conn->prepare("DELETE FROM inv_localizaciones WHERE id = ?");
    $stmt->bind_param("i", $_GET['delete_loc']);
    $stmt->execute();
    header("Location: " . urlParam(['delete_loc' => null])); exit();
}

// --- ACCIONES DE GUARDADO (POST) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['save_obj'])) {
        $id = $_POST['id'] ?? null;
        
        $stmt = $id 
            ? $conn->prepare("UPDATE inv_objetos SET objeto=?, localizacion=?, descripcion=?, tipo=?, tipo_de_objeto=?, plataformas=?, portada_http=? WHERE id=?")
            : $conn->prepare("INSERT INTO inv_objetos (objeto, localizacion, descripcion, tipo, tipo_de_objeto, plataformas, portada_http) VALUES (?, ?, ?, ?, ?, ?, ?)");
        
        if ($id) $stmt->bind_param("sssssssi", $_POST['titulo'], $_POST['localizacion'], $_POST['descripcion'], $_POST['tipo'], $_POST['tipo_de_objeto'], $_POST['plataformas'], $_POST['portada_http'], $id);
        else $stmt->bind_param("sssssss", $_POST['titulo'], $_POST['localizacion'], $_POST['descripcion'], $_POST['tipo'], $_POST['tipo_de_objeto'], $_POST['plataformas'], $_POST['portada_http']);
        
        $stmt->execute();
        header("Location: " . $_SERVER['REQUEST_URI']); exit();
    }
    
    if (isset($_POST['save_loc'])) {
        $id = $_POST['id'] ?? null;
        $stmt = $id 
            ? $conn->prepare("UPDATE inv_localizaciones SET nombre=?, descripcion_del_contenido=?, categoria=?, foto_http=? WHERE id=?")
            : $conn->prepare("INSERT INTO inv_localizaciones (nombre, descripcion_del_contenido, categoria, foto_http) VALUES (?, ?, ?, ?)");
        
        // ¡Corregido! Ahora lee el campo correcto del formulario HTML
        if ($id) $stmt->bind_param("ssssi", $_POST['nombre'], $_POST['descripcion_del_contenido'], $_POST['categoria'], $_POST['foto_http'], $id);
        else $stmt->bind_param("ssss", $_POST['nombre'], $_POST['descripcion_del_contenido'], $_POST['categoria'], $_POST['foto_http']);
        
        $stmt->execute();
        header("Location: index.php?tab=localizaciones"); exit();
    }
}

// --- LÓGICA DE OBJETOS: BÚSQUEDA Y FILTROS ---
$where_sql = "";
$where_parts = [];

if ($search !== '') {
    $s = "%" . $conn->real_escape_string($search) . "%";
    // Ahora el buscador también busca en localizaciones por si buscas "Estantería 1"
    $where_parts[] = "(objeto LIKE '$s' OR descripcion LIKE '$s' OR plataformas LIKE '$s' OR localizacion LIKE '$s')";
}
if ($f_tipo !== '') $where_parts[] = "tipo = '" . $conn->real_escape_string($f_tipo) . "'";
if ($f_t_obj !== '') $where_parts[] = "tipo_de_objeto = '" . $conn->real_escape_string($f_t_obj) . "'";

if (count($where_parts) > 0) $where_sql = "WHERE " . implode(" AND ", $where_parts);

$order_sql = "ORDER BY id DESC";
if ($sort === 'nombre_asc') $order_sql = "ORDER BY objeto ASC";
if ($sort === 'tipo_nombre') $order_sql = "ORDER BY tipo ASC, objeto ASC";
if ($sort === 'loc_tipo_nombre') $order_sql = "ORDER BY localizacion ASC, tipo ASC, objeto ASC";

// --- EXTRACCIÓN INTELIGENTE DE ETIQUETAS MULTIPLES ---
$tipos_db = $conn->query("SELECT DISTINCT tipo FROM inv_objetos WHERE tipo IS NOT NULL AND tipo != '' ORDER BY tipo");

$cat_options = [];
$tipos_obj_db = $conn->query("SELECT DISTINCT tipo_de_objeto FROM inv_objetos WHERE tipo_de_objeto IS NOT NULL AND tipo_de_objeto != '' ORDER BY tipo_de_objeto");
while($to = $tipos_obj_db->fetch_assoc()) $cat_options[] = $to['tipo_de_objeto'];
$tipos_obj_db->data_seek(0);

// Extraer Plataformas (Separando por comas)
$plat_options = [];
$plataformas_db = $conn->query("SELECT DISTINCT plataformas FROM inv_objetos WHERE plataformas IS NOT NULL AND plataformas != ''");
while($p = $plataformas_db->fetch_assoc()) {
    $parts = array_map('trim', explode(',', $p['plataformas']));
    foreach($parts as $part) {
        if ($part !== '' && !in_array($part, $plat_options)) $plat_options[] = $part;
    }
}
sort($plat_options);

// Extraer Localizaciones de su tabla
$localizaciones = $conn->query("SELECT * FROM inv_localizaciones ORDER BY nombre ASC");
$loc_options = [];
while($row = $localizaciones->fetch_assoc()) $loc_options[] = $row['nombre'];
$localizaciones->data_seek(0);

// Resultados paginados
$count_obj = $conn->query("SELECT COUNT(*) as total FROM inv_objetos $where_sql")->fetch_assoc()['total'];
$total_pages_obj = ceil($count_obj / $limit);
$objetos = $conn->query("SELECT * FROM inv_objetos $where_sql $order_sql LIMIT $offset, $limit");

$count_loc = $conn->query("SELECT COUNT(*) as total FROM inv_localizaciones")->fetch_assoc()['total'];
$total_pages_loc = ceil($count_loc / $limit);
$loc_paginadas = $conn->query("SELECT * FROM inv_localizaciones ORDER BY nombre ASC LIMIT $offset, $limit");

$show_filters = ($f_tipo !== '' || $f_t_obj !== '' || $sort !== 'newest') ? 'show' : '';
?>

<!DOCTYPE html>
<html lang="es" data-bs-theme="dark">
<?php include "{$src}backend/config/ini.php"; ?>
<body>
  <?php include "{$src}frontend/menu.php"; ?>
  
  <main class="container-fluid px-4 my-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-3">
        <h2 class="mb-0">📦 Memento <span class="text-info fs-5">(<?php echo ($tab == 'objetos') ? $count_obj : $count_loc; ?> ítems)</span></h2>
        <div>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modal-<?php echo $tab; ?>">
                + Añadir <?php echo ($tab == 'objetos') ? 'Objeto' : 'Localización'; ?>
            </button>
            <a href="csv.php" class="btn btn-outline-info">Importar</a>
        </div>
    </div>

    <ul class="nav nav-tabs mb-3 border-secondary">
        <li class="nav-item">
            <a class="nav-link <?php echo $tab == 'objetos' ? 'active bg-dark text-info border-secondary border-bottom-0' : 'text-light'; ?>" href="?tab=objetos">Colección</a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?php echo $tab == 'localizaciones' ? 'active bg-dark text-info border-secondary border-bottom-0' : 'text-light'; ?>" href="?tab=localizaciones">Localizaciones</a>
        </li>
    </ul>

    <?php if ($tab == 'objetos'): ?>
    <form method="GET" action="index.php" class="mb-4">
        <input type="hidden" name="tab" value="objetos">
        
        <div class="search-container shadow-sm d-flex gap-2">
            <input type="text" name="q" class="form-control" placeholder="🔍 Buscar por título, localización, consola..." value="<?php echo htmlspecialchars($search); ?>">
            <button type="submit" class="btn btn-info text-white fw-bold px-4">Buscar</button>
            <button class="btn btn-outline-secondary d-flex align-items-center gap-2" type="button" data-bs-toggle="collapse" data-bs-target="#filtrosAvanzados">⚙️ Filtros</button>
        </div>

        <div class="collapse <?php echo $show_filters; ?> mt-2" id="filtrosAvanzados">
            <div class="toolbar shadow-sm">
                <div class="row g-3 align-items-end">
                    <div class="col-md-3">
                        <label class="form-label small text-muted mb-1">Filtrar por Tipo</label>
                        <select name="f_tipo" class="form-select form-select-sm" onchange="this.form.submit()">
                            <option value="">- Todos los tipos -</option>
                            <?php while($t = $tipos_db->fetch_assoc()): ?>
                                <option value="<?php echo htmlspecialchars($t['tipo']); ?>" <?php echo $f_tipo == $t['tipo'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($t['tipo']); ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    
                    <div class="col-md-3">
                        <label class="form-label small text-muted mb-1">Filtrar por Categoría</label>
                        <select name="f_t_obj" class="form-select form-select-sm" onchange="this.form.submit()">
                            <option value="">- Todas las categorías -</option>
                            <?php while($to = $tipos_obj_db->fetch_assoc()): ?>
                                <option value="<?php echo htmlspecialchars($to['tipo_de_objeto']); ?>" <?php echo $f_t_obj == $to['tipo_de_objeto'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($to['tipo_de_objeto']); ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label small text-muted mb-1">Ordenar por</label>
                        <select name="sort" class="form-select form-select-sm" onchange="this.form.submit()">
                            <option value="newest" <?php echo $sort == 'newest' ? 'selected' : ''; ?>>🕒 Más recientes primero</option>
                            <option value="nombre_asc" <?php echo $sort == 'nombre_asc' ? 'selected' : ''; ?>>🔤 Nombre (A - Z)</option>
                            <option value="tipo_nombre" <?php echo $sort == 'tipo_nombre' ? 'selected' : ''; ?>>📁 Tipo > Nombre</option>
                            <option value="loc_tipo_nombre" <?php echo $sort == 'loc_tipo_nombre' ? 'selected' : ''; ?>>📍 Localización > Tipo > Nombre</option>
                        </select>
                    </div>

                    <div class="col-md-2 d-grid">
                        <a href="index.php?tab=objetos" class="btn btn-outline-danger btn-sm">Limpiar filtros</a>
                    </div>
                </div>
            </div>
        </div>
    </form>
    <?php endif; ?>

    <?php if ($tab == 'objetos'): ?>
    <div class="row row-cols-2 row-cols-md-4 row-cols-lg-6 g-3">
        <?php if ($objetos->num_rows > 0): ?>
            <?php while($obj = $objetos->fetch_assoc()): 
                $titulo = !empty($obj['objeto']) ? $obj['objeto'] : 'Sin Título';
                $img = !empty($obj['portada_http']) ? $obj['portada_http'] : 'https://via.placeholder.com/540x720?text=Sin+Foto';
            ?>
            <div class="col">
                <div class="card memento-card shadow-sm bg-dark position-relative" data-bs-toggle="modal" data-bs-target="#editObj<?php echo $obj['id']; ?>">
                    
                    <div class="position-absolute top-0 end-0 p-2 d-flex flex-column gap-1 align-items-end" style="z-index: 10;">
                        <?php
                        $locs = array_map('trim', explode(',', $obj['localizacion'] ?? ''));
                        foreach($locs as $l): if($l):
                        ?>
                            <span class="badge bg-dark bg-opacity-75 border border-secondary text-light shadow-sm" style="font-size: 0.7rem;"><i class="bi bi-geo-alt"></i> <?php echo htmlspecialchars($l); ?></span>
                        <?php endif; endforeach; ?>
                    </div>

                    <img src="<?php echo htmlspecialchars($img); ?>" class="card-img-top memento-img" alt="Portada" loading="lazy">
                    <div class="card-body p-2 text-center d-flex flex-column justify-content-center">
                        <div class="card-title text-truncate mb-1" title="<?php echo htmlspecialchars($titulo); ?>"><?php echo htmlspecialchars($titulo); ?></div>
                        <small class="text-info" style="font-size:0.7rem;"><?php echo htmlspecialchars($obj['tipo'] ?? ''); ?> <?php echo !empty($obj['tipo_de_objeto']) ? ' > '.$obj['tipo_de_objeto'] : ''; ?></small>
                    </div>
                </div>
            </div>

            <div class="modal fade" id="editObj<?php echo $obj['id']; ?>" tabindex="-1">
                <div class="modal-dialog">
                    <form class="modal-content" method="POST">
                        <div class="modal-header">
                            <h5 class="modal-title fs-6">Editar: <?php echo htmlspecialchars($titulo); ?></h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <input type="hidden" name="id" value="<?php echo $obj['id']; ?>">
                            <div class="mb-2"><label class="small text-muted">Título</label><input type="text" name="titulo" class="form-control form-control-sm" value="<?php echo htmlspecialchars($titulo); ?>" required></div>
                            <div class="mb-2"><label class="small text-muted">URL Imagen</label><input type="text" name="portada_http" class="form-control form-control-sm" value="<?php echo htmlspecialchars($obj['portada_http']); ?>"></div>
                            
                            <div class="row">
                                <div class="col-6 mb-2">
                                    <label class="small text-muted">Tipo principal</label>
                                    <select name="tipo" class="form-select form-select-sm">
                                        <option value="Objetos" <?php echo ($obj['tipo'] == 'Objetos') ? 'selected' : ''; ?>>Objetos</option>
                                        <option value="Juegos" <?php echo ($obj['tipo'] == 'Juegos') ? 'selected' : ''; ?>>Juegos</option>
                                        <option value="Pelis" <?php echo ($obj['tipo'] == 'Pelis') ? 'selected' : ''; ?>>Pelis</option>
                                    </select>
                                </div>
                                <div class="col-6 mb-2">
                                    <label class="small text-muted">Categoría</label>
                                    <input type="text" name="tipo_de_objeto" class="form-control form-control-sm" value="<?php echo htmlspecialchars($obj['tipo_de_objeto']); ?>" list="listaCategorias" autocomplete="off" placeholder="Elegir o escribir">
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="small text-muted fw-bold">Localizaciones (Separadas por coma)</label>
                                <input type="text" name="localizacion" id="loc_edit_<?php echo $obj['id']; ?>" class="form-control form-control-sm" value="<?php echo htmlspecialchars($obj['localizacion']); ?>">
                                <div class="tag-container mt-1">
                                    <?php foreach($loc_options as $lo): ?>
                                        <span class="badge bg-secondary tag-btn" onclick="toggleTag('loc_edit_<?php echo $obj['id']; ?>', '<?php echo addslashes($lo); ?>')">+ <?php echo htmlspecialchars($lo); ?></span>
                                    <?php endforeach; ?>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="small text-muted fw-bold">Plataformas (Separadas por coma)</label>
                                <input type="text" name="plataformas" id="plat_edit_<?php echo $obj['id']; ?>" class="form-control form-control-sm" value="<?php echo htmlspecialchars($obj['plataformas']); ?>">
                                <div class="tag-container mt-1">
                                    <?php foreach($plat_options as $po): ?>
                                        <span class="badge bg-secondary tag-btn" onclick="toggleTag('plat_edit_<?php echo $obj['id']; ?>', '<?php echo addslashes($po); ?>')">+ <?php echo htmlspecialchars($po); ?></span>
                                    <?php endforeach; ?>
                                </div>
                            </div>

                            <div class="mb-2"><label class="small text-muted">Descripción</label><textarea name="descripcion" class="form-control form-control-sm" rows="2"><?php echo htmlspecialchars($obj['descripcion']); ?></textarea></div>
                        </div>
                        <div class="modal-footer justify-content-between p-2">
                            <a href="<?php echo urlParam(['delete_obj' => $obj['id']]); ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('¿Borrar este objeto?')">Eliminar</a>
                            <button type="submit" name="save_obj" class="btn btn-sm btn-primary">Guardar</button>
                        </div>
                    </form>
                </div>
            </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div class="col-12 text-center py-5 text-muted">No se han encontrado resultados. Intenta limpiar los filtros o la búsqueda.</div>
        <?php endif; ?>
    </div>
    
    <?php if ($total_pages_obj > 1): ?>
        <nav class="mt-4"><ul class="pagination justify-content-center pagination-sm">
            <?php for($i=1; $i<=$total_pages_obj; $i++): if($i == 1 || $i == $total_pages_obj || ($i >= $page-2 && $i <= $page+2)): ?>
                <li class="page-item <?php echo $i==$page?'active':''; ?>"><a class="page-link" href="<?php echo urlParam(['p'=>$i]); ?>"><?php echo $i; ?></a></li>
            <?php elseif($i == $page-3 || $i == $page+3): ?>
                <li class="page-item disabled"><span class="page-link">...</span></li>
            <?php endif; endfor; ?>
        </ul></nav>
    <?php endif; ?>
    
    <?php endif; ?>

    <?php if ($tab == 'localizaciones'): ?>
    <div class="row row-cols-2 row-cols-md-4 row-cols-lg-6 g-3">
        <?php while($loc = $loc_paginadas->fetch_assoc()): 
            $img = !empty($loc['foto_http']) ? $loc['foto_http'] : 'https://via.placeholder.com/540x720?text=Sin+Foto';
        ?>
        <div class="col">
            <div class="card memento-card shadow-sm bg-dark" data-bs-toggle="modal" data-bs-target="#editLoc<?php echo $loc['id']; ?>">
                <img src="<?php echo htmlspecialchars($img); ?>" class="card-img-top memento-img" alt="Foto" loading="lazy">
                <div class="card-body p-2 text-center d-flex flex-column justify-content-center">
                    <div class="card-title text-info text-truncate mb-1"><?php echo htmlspecialchars($loc['nombre']); ?></div>
                    <small class="text-secondary" style="font-size:0.7rem;"><?php echo htmlspecialchars($loc['categoria']); ?></small>
                </div>
            </div>
        </div>

        <div class="modal fade" id="editLoc<?php echo $loc['id']; ?>" tabindex="-1">
            <div class="modal-dialog">
                <form class="modal-content" method="POST">
                    <div class="modal-header"><h5 class="modal-title fs-6">Editar Localización</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                    <div class="modal-body">
                        <input type="hidden" name="id" value="<?php echo $loc['id']; ?>">
                        <div class="mb-2"><label class="small text-muted">Nombre</label><input type="text" name="nombre" class="form-control form-control-sm" value="<?php echo htmlspecialchars($loc['nombre']); ?>" required></div>
                        <div class="mb-2"><label class="small text-muted">Categoría</label><input type="text" name="categoria" class="form-control form-control-sm" value="<?php echo htmlspecialchars($loc['categoria']); ?>"></div>
                        <div class="mb-2"><label class="small text-muted">URL Foto</label><input type="text" name="foto_http" class="form-control form-control-sm" value="<?php echo htmlspecialchars($loc['foto_http']); ?>"></div>
                        <div class="mb-2"><label class="small text-muted">Descripción</label><textarea name="descripcion_del_contenido" class="form-control form-control-sm" rows="3"><?php echo htmlspecialchars($loc['descripcion_del_contenido']); ?></textarea></div>
                    </div>
                    <div class="modal-footer justify-content-between p-2">
                        <a href="<?php echo urlParam(['delete_loc' => $loc['id']]); ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('¿Borrar localización?')">Borrar</a>
                        <button type="submit" name="save_loc" class="btn btn-sm btn-primary">Guardar</button>
                    </div>
                </form>
            </div>
        </div>
        <?php endwhile; ?>
    </div>
    
    <?php if ($total_pages_loc > 1): ?>
        <nav class="mt-4"><ul class="pagination justify-content-center pagination-sm">
            <?php for($i=1; $i<=$total_pages_loc; $i++): if($i == 1 || $i == $total_pages_loc || ($i >= $page-2 && $i <= $page+2)): ?>
                <li class="page-item <?php echo $i==$page?'active':''; ?>"><a class="page-link" href="<?php echo urlParam(['p'=>$i]); ?>"><?php echo $i; ?></a></li>
            <?php elseif($i == $page-3 || $i == $page+3): ?>
                <li class="page-item disabled"><span class="page-link">...</span></li>
            <?php endif; endfor; ?>
        </ul></nav>
    <?php endif; ?>
    
    <?php endif; ?>

  </main>

  <div class="modal fade" id="modal-objetos" tabindex="-1">
      <div class="modal-dialog"><form class="modal-content" method="POST">
          <div class="modal-header"><h5 class="modal-title">Nuevo Objeto</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
          <div class="modal-body">
              <div class="mb-2"><label class="small">Título</label><input type="text" name="titulo" class="form-control" required></div>
              <div class="mb-2"><label class="small">URL Imagen</label><input type="text" name="portada_http" class="form-control"></div>
              <div class="row">
                  <div class="col-6 mb-2">
                      <label class="small">Tipo</label>
                      <select name="tipo" class="form-select">
                          <option value="Objetos">Objetos</option><option value="Juegos">Juegos</option><option value="Pelis">Pelis</option>
                      </select>
                  </div>
                  <div class="col-6 mb-2">
                      <label class="small">Categoría</label>
                      <input type="text" name="tipo_de_objeto" class="form-control" list="listaCategorias" autocomplete="off" placeholder="Elegir o escribir">
                  </div>
              </div>
              
              <div class="mb-3">
                  <label class="small fw-bold">Localizaciones (Separadas por coma)</label>
                  <input type="text" name="localizacion" id="loc_new" class="form-control">
                  <div class="tag-container mt-1">
                      <?php foreach($loc_options as $lo): ?>
                          <span class="badge bg-secondary tag-btn" onclick="toggleTag('loc_new', '<?php echo addslashes($lo); ?>')">+ <?php echo htmlspecialchars($lo); ?></span>
                      <?php endforeach; ?>
                  </div>
              </div>

              <div class="mb-3">
                  <label class="small fw-bold">Plataformas (Separadas por coma)</label>
                  <input type="text" name="plataformas" id="plat_new" class="form-control">
                  <div class="tag-container mt-1">
                      <?php foreach($plat_options as $po): ?>
                          <span class="badge bg-secondary tag-btn" onclick="toggleTag('plat_new', '<?php echo addslashes($po); ?>')">+ <?php echo htmlspecialchars($po); ?></span>
                      <?php endforeach; ?>
                  </div>
              </div>

              <div class="mb-2"><label class="small">Descripción</label><textarea name="descripcion" class="form-control"></textarea></div>
          </div>
          <div class="modal-footer"><button type="submit" name="save_obj" class="btn btn-primary">Añadir</button></div>
      </form></div>
  </div>

  <div class="modal fade" id="modal-localizaciones" tabindex="-1">
      <div class="modal-dialog"><form class="modal-content" method="POST"><div class="modal-header"><h5 class="modal-title">Nueva Localización</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body"><div class="mb-2"><label>Nombre (Único)</label><input type="text" name="nombre" class="form-control" required></div><div class="mb-2"><label>Categoría</label><input type="text" name="categoria" class="form-control"></div><div class="mb-2"><label>URL Foto</label><input type="text" name="foto_http" class="form-control"></div><div class="mb-2"><label>Descripción</label><textarea name="descripcion_del_contenido" class="form-control"></textarea></div></div><div class="modal-footer"><button type="submit" name="save_loc" class="btn btn-primary">Añadir</button></div></form></div>
  </div>

  <datalist id="listaCategorias">
      <?php foreach($cat_options as $cat): ?><option value="<?php echo htmlspecialchars($cat); ?>"></option><?php endforeach; ?>
  </datalist>

  <script>
      function toggleTag(inputId, value) {
          let input = document.getElementById(inputId);
          // Separamos por comas y limpiamos espacios vacíos
          let parts = input.value.split(',');
          let currentTags = [];
          for(let i = 0; i < parts.length; i++) {
              let t = parts[i].trim();
              if(t !== '') currentTags.push(t);
          }
          
          // Si el valor ya existe en el input, lo quitamos. Si no existe, lo añadimos.
          let index = currentTags.indexOf(value);
          if (index > -1) {
              currentTags.splice(index, 1);
          } else {
              currentTags.push(value);
          }
          
          // Volvemos a juntar todo con comas y lo ponemos en el input
          input.value = currentTags.join(', ');
      }
  </script>

  <?php include "{$src}frontend/footer.php"; ?>
</body>
</html>
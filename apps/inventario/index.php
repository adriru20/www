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

// --- PARCHES AUTOMÁTICOS DE BASE DE DATOS ---
try { $conn->query("ALTER TABLE inv_objetos DROP FOREIGN KEY fk_loc_obj"); } catch(Exception $e) {}
try { 
    $conn->query("ALTER TABLE inv_objetos ADD COLUMN cantidad INT DEFAULT 1");
    $conn->query("ALTER TABLE inv_objetos ADD COLUMN generos VARCHAR(255) DEFAULT ''");
    $conn->query("ALTER TABLE inv_objetos ADD COLUMN formato VARCHAR(50) DEFAULT 'Físico'");
    $conn->query("ALTER TABLE inv_objetos ADD COLUMN formato_de_archivo VARCHAR(255) DEFAULT ''");
    $conn->query("ALTER TABLE inv_objetos ADD COLUMN en_la_caja TINYINT(1) DEFAULT 0");
    $conn->query("ALTER TABLE inv_objetos ADD COLUMN precio_de_venta DECIMAL(10,2) DEFAULT 0.00");
    
    $conn->query("UPDATE inv_objetos SET tipo = 'Películas' WHERE tipo = 'Pelis'");
} catch(Exception $e) {}

// --- CONFIGURACIÓN DE PARÁMETROS ---
$tab = $_GET['tab'] ?? 'objetos';
$page = max(1, isset($_GET['p']) ? (int)$_GET['p'] : 1);
$limit = 24; 
$offset = ($page - 1) * $limit;

// Parámetros de Filtros
$search = $_GET['q'] ?? '';
$f_loc = isset($_GET['f_loc']) ? (is_array($_GET['f_loc']) ? $_GET['f_loc'] : [$_GET['f_loc']]) : [];
$f_tipo = $_GET['f_tipo'] ?? '';
$f_t_obj = $_GET['f_t_obj'] ?? '';
$sort = $_GET['sort'] ?? 'newest';

$q_loc = $_GET['q_loc'] ?? '';
$f_cat_loc = $_GET['f_cat_loc'] ?? '';
$sort_loc = $_GET['sort_loc'] ?? 'cat_nombre'; 

// LIMPIEZA DE URL
function urlParam($updates) {
    $params = $_GET;
    foreach($updates as $k => $v) {
        if ($v === null || $v === '') unset($params[$k]);
        else $params[$k] = $v;
    }
    $query = http_build_query($params);
    return $query ? '?' . $query : '';
}

// RENDERIZADO VISUAL
function renderImg($val) {
    if (empty($val)) return 'https://via.placeholder.com/540x720?text=Sin+Foto';
    if (strpos($val, 'http') === 0) return $val;
    return './img/' . basename($val);
}

// --- ESCANEO DE CARPETA DE IMÁGENES ---
$img_dir = './img/';
if (!is_dir($img_dir)) {
    @mkdir($img_dir, 0777, true);
}
$local_images = [];
if (is_dir($img_dir)) {
    $files = scandir($img_dir);
    foreach ($files as $file) {
        $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'])) {
            $local_images[] = $file; 
        }
    }
}

// SUBIDA DE ARCHIVOS
function handleImageUpload($fileArray, $customName = '') {
    global $img_dir;
    if (!empty($fileArray['name']) && $fileArray['error'] === UPLOAD_ERR_OK) {
        $ext = strtolower(pathinfo($fileArray['name'], PATHINFO_EXTENSION));
        if (!empty($customName)) {
            $cleanName = preg_replace("/[^a-zA-Z0-9\-_]/", "", $customName);
            $fileName = $cleanName . '.' . $ext;
        } else {
            $fileName = basename($fileArray['name']);
            $fileName = preg_replace("/[^a-zA-Z0-9.\-_]/", "", $fileName); 
        }
        $targetFilePath = $img_dir . $fileName;
        if (move_uploaded_file($fileArray['tmp_name'], $targetFilePath)) return $fileName; 
    }
    return null;
}

// --- ACCIONES DIRECTAS ---
if (!empty($_GET['delete_img'])) {
    $img_to_delete = basename($_GET['delete_img']);
    $target = $img_dir . $img_to_delete;
    if (file_exists($target)) unlink($target);
    header("Location: index.php?tab=imagenes"); exit();
}
if (!empty($_GET['delete_obj'])) {
    $stmt = $conn->prepare("DELETE FROM inv_objetos WHERE id = ?");
    $stmt->bind_param("i", $_GET['delete_obj']);
    $stmt->execute();
    header("Location: index.php" . urlParam(['delete_obj' => null])); exit();
}
if (!empty($_GET['delete_loc'])) {
    $stmt = $conn->prepare("DELETE FROM inv_localizaciones WHERE id = ?");
    $stmt->bind_param("i", $_GET['delete_loc']);
    $stmt->execute();
    header("Location: index.php" . urlParam(['delete_loc' => null])); exit();
}

// --- GUARDADO / EDICIÓN (POST) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['rename_img'])) {
        $old_name = basename($_POST['old_name']);
        $new_name = preg_replace("/[^a-zA-Z0-9\-_]/", "", $_POST['new_name']);
        $ext = strtolower(pathinfo($old_name, PATHINFO_EXTENSION));
        $final_new_name = $new_name . '.' . $ext;
        if (file_exists($img_dir . $old_name) && !empty($new_name)) rename($img_dir . $old_name, $img_dir . $final_new_name);
        header("Location: index.php?tab=imagenes"); exit();
    }

    if (isset($_POST['save_obj'])) {
        $id = $_POST['id'] ?? null;
        $portada_val = $_POST['portada_http'] ?? '';
        $uploadedFile = handleImageUpload($_FILES['portada_file'] ?? [], $_POST['portada_custom_name'] ?? '');
        if ($uploadedFile) $portada_val = $uploadedFile; 
        
        $cantidad = !empty($_POST['cantidad']) ? (int)$_POST['cantidad'] : 1;
        $generos = $_POST['generos'] ?? '';
        $formato = $_POST['formato'] ?? 'Físico';
        $formato_archivo = $_POST['formato_de_archivo'] ?? '';
        $en_la_caja = isset($_POST['en_la_caja']) ? 1 : 0;
        $precio = !empty($_POST['precio_de_venta']) ? (float)$_POST['precio_de_venta'] : 0.00;

        $stmt = $id 
            ? $conn->prepare("UPDATE inv_objetos SET objeto=?, localizacion=?, descripcion=?, tipo=?, tipo_de_objeto=?, plataformas=?, portada_http=?, cantidad=?, generos=?, formato=?, formato_de_archivo=?, en_la_caja=?, precio_de_venta=? WHERE id=?")
            : $conn->prepare("INSERT INTO inv_objetos (objeto, localizacion, descripcion, tipo, tipo_de_objeto, plataformas, portada_http, cantidad, generos, formato, formato_de_archivo, en_la_caja, precio_de_venta) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        
        if ($id) $stmt->bind_param("sssssssisssidi", $_POST['titulo'], $_POST['localizacion'], $_POST['descripcion'], $_POST['tipo'], $_POST['tipo_de_objeto'], $_POST['plataformas'], $portada_val, $cantidad, $generos, $formato, $formato_archivo, $en_la_caja, $precio, $id);
        else $stmt->bind_param("sssssssisssid", $_POST['titulo'], $_POST['localizacion'], $_POST['descripcion'], $_POST['tipo'], $_POST['tipo_de_objeto'], $_POST['plataformas'], $portada_val, $cantidad, $generos, $formato, $formato_archivo, $en_la_caja, $precio);
        
        $stmt->execute();
        header("Location: index.php" . urlParam([])); exit();
    }
    
    if (isset($_POST['save_loc'])) {
        $id = $_POST['id'] ?? null;
        $foto_val = $_POST['foto_http'] ?? '';
        $uploadedFile = handleImageUpload($_FILES['foto_file'] ?? [], $_POST['foto_custom_name'] ?? '');
        if ($uploadedFile) $foto_val = $uploadedFile;

        $stmt = $id 
            ? $conn->prepare("UPDATE inv_localizaciones SET nombre=?, descripcion_del_contenido=?, categoria=?, foto_http=? WHERE id=?")
            : $conn->prepare("INSERT INTO inv_localizaciones (nombre, descripcion_del_contenido, categoria, foto_http) VALUES (?, ?, ?, ?)");
        
        if ($id) $stmt->bind_param("ssssi", $_POST['nombre'], $_POST['descripcion_del_contenido'], $_POST['categoria'], $foto_val, $id);
        else $stmt->bind_param("ssss", $_POST['nombre'], $_POST['descripcion_del_contenido'], $_POST['categoria'], $foto_val);
        
        $stmt->execute();
        header("Location: index.php?tab=localizaciones"); exit();
    }
}

// ==========================================
// AISLAMIENTO DE CONSULTAS SEGÚN PESTAÑA
// ==========================================
$count_obj = 0; $total_pages_obj = 0;
$count_loc = 0; $total_pages_loc = 0;
$total_images = count($local_images);

if ($tab === 'objetos') {
    $where_sql = ""; $where_parts = [];
    if ($search !== '') {
        $s = "%" . $conn->real_escape_string($search) . "%";
        $where_parts[] = "(objeto LIKE '$s' OR descripcion LIKE '$s' OR plataformas LIKE '$s' OR localizacion LIKE '$s')";
    }
    if (!empty($f_loc)) {
        $loc_queries = [];
        foreach ($f_loc as $loc_val) { if ($loc_val !== '') $loc_queries[] = "localizacion LIKE '%" . $conn->real_escape_string($loc_val) . "%'"; }
        if (count($loc_queries) > 0) $where_parts[] = "(" . implode(" OR ", $loc_queries) . ")";
    }
    if ($f_tipo !== '') $where_parts[] = "tipo = '" . $conn->real_escape_string($f_tipo) . "'";
    if ($f_t_obj !== '') $where_parts[] = "tipo_de_objeto = '" . $conn->real_escape_string($f_t_obj) . "'";
    if (count($where_parts) > 0) $where_sql = "WHERE " . implode(" AND ", $where_parts);

    $order_sql = "ORDER BY id DESC";
    if ($sort === 'nombre_asc') $order_sql = "ORDER BY objeto ASC";
    if ($sort === 'tipo_nombre') $order_sql = "ORDER BY tipo ASC, objeto ASC";

    $count_obj = $conn->query("SELECT COUNT(*) as total FROM inv_objetos $where_sql")->fetch_assoc()['total'];
    $total_pages_obj = ceil($count_obj / $limit);
    $objetos = $conn->query("SELECT * FROM inv_objetos $where_sql $order_sql LIMIT $offset, $limit");

    $tipos_db = $conn->query("SELECT DISTINCT tipo FROM inv_objetos WHERE tipo IS NOT NULL AND tipo != '' ORDER BY tipo");
    
    $cat_options = [];
    $tipos_obj_db = $conn->query("SELECT DISTINCT tipo_de_objeto FROM inv_objetos WHERE tipo_de_objeto IS NOT NULL AND tipo_de_objeto != '' ORDER BY tipo_de_objeto");
    while($to = $tipos_obj_db->fetch_assoc()) $cat_options[] = $to['tipo_de_objeto'];
    
    $plat_options = [];
    $plataformas_db = $conn->query("SELECT DISTINCT plataformas FROM inv_objetos WHERE plataformas IS NOT NULL AND plataformas != ''");
    while($p = $plataformas_db->fetch_assoc()) {
        $parts = array_map('trim', explode(',', $p['plataformas']));
        foreach($parts as $part) { if ($part !== '' && !in_array($part, $plat_options)) $plat_options[] = $part; }
    }
    sort($plat_options);

    $gen_options = [];
    $gen_db = $conn->query("SELECT DISTINCT generos FROM inv_objetos WHERE generos IS NOT NULL AND generos != ''");
    while($g = $gen_db->fetch_assoc()) {
        $parts = array_map('trim', explode(',', $g['generos']));
        foreach($parts as $part) { if ($part !== '' && !in_array($part, $gen_options)) $gen_options[] = $part; }
    }
    sort($gen_options);

    $fa_options = [];
    $fa_db = $conn->query("SELECT DISTINCT formato_de_archivo FROM inv_objetos WHERE formato_de_archivo IS NOT NULL AND formato_de_archivo != '' ORDER BY formato_de_archivo");
    while($fa = $fa_db->fetch_assoc()) {
        if ($fa['formato_de_archivo'] !== '') $fa_options[] = $fa['formato_de_archivo'];
    }

    $loc_options = [];
    $localizaciones = $conn->query("SELECT nombre FROM inv_localizaciones ORDER BY nombre ASC");
    while($row = $localizaciones->fetch_assoc()) $loc_options[] = $row['nombre'];
    
    $show_filters = (!empty($f_loc) || $f_tipo !== '' || $f_t_obj !== '' || $sort !== 'newest') ? 'show' : '';
} 
elseif ($tab === 'localizaciones') {
    $where_loc_sql = ""; $where_loc_parts = [];
    if ($q_loc !== '') {
        $sl = "%" . $conn->real_escape_string($q_loc) . "%";
        $where_loc_parts[] = "(nombre LIKE '$sl' OR descripcion_del_contenido LIKE '$sl' OR categoria LIKE '$sl')";
    }
    if ($f_cat_loc !== '') $where_loc_parts[] = "categoria = '" . $conn->real_escape_string($f_cat_loc) . "'";
    if (count($where_loc_parts) > 0) $where_loc_sql = "WHERE " . implode(" AND ", $where_loc_parts);

    $order_loc_sql = "ORDER BY categoria ASC, nombre ASC"; 
    if ($sort_loc === 'nombre_asc') $order_loc_sql = "ORDER BY nombre ASC";
    if ($sort_loc === 'nombre_desc') $order_loc_sql = "ORDER BY nombre DESC";
    if ($sort_loc === 'newest') $order_loc_sql = "ORDER BY id DESC";

    $count_loc = $conn->query("SELECT COUNT(*) as total FROM inv_localizaciones $where_loc_sql")->fetch_assoc()['total'];
    $total_pages_loc = ceil($count_loc / $limit);
    $loc_paginadas = $conn->query("SELECT * FROM inv_localizaciones $where_loc_sql $order_loc_sql LIMIT $offset, $limit");

    $cat_loc_options = [];
    $cat_loc_db = $conn->query("SELECT DISTINCT categoria FROM inv_localizaciones WHERE categoria IS NOT NULL AND categoria != '' ORDER BY categoria");
    while($cl = $cat_loc_db->fetch_assoc()) $cat_loc_options[] = $cl['categoria'];
    
    $show_filters_loc = ($f_cat_loc !== '' || $sort_loc !== 'cat_nombre') ? 'show' : ''; 
} 
elseif ($tab === 'imagenes') {
    $total_pages_img = ceil($total_images / $limit);
    $paginated_images = array_slice($local_images, $offset, $limit);
}
?>

<!DOCTYPE html>
<html lang="es" data-bs-theme="dark">
<?php include "{$src}backend/config/ini.php"; ?>
<body>
  <?php include "{$src}frontend/menu.php"; ?>
  
  <main class="container-fluid px-4 my-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-3">
        <h2 class="mb-0">📦Inventario <span class="text-info fs-5">(<?php 
            if($tab == 'objetos') echo $count_obj; 
            elseif($tab == 'localizaciones') echo $count_loc; 
            else echo $total_images; 
        ?> ítems)</span></h2>
        <div>
            <?php if($tab != 'imagenes'): ?>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modal-<?php echo $tab; ?>">
                + Añadir <?php echo ($tab == 'objetos') ? 'Objeto' : 'Localización'; ?>
            </button>
            <?php endif; ?>
        </div>
    </div>

    <ul class="nav nav-tabs mb-3 border-secondary">
        <li class="nav-item"><a class="nav-link <?php echo $tab == 'objetos' ? 'active bg-dark text-info border-secondary border-bottom-0' : 'text-light'; ?>" href="?tab=objetos">Colección</a></li>
        <li class="nav-item"><a class="nav-link <?php echo $tab == 'localizaciones' ? 'active bg-dark text-info border-secondary border-bottom-0' : 'text-light'; ?>" href="?tab=localizaciones">Localizaciones</a></li>
        <li class="nav-item"><a class="nav-link <?php echo $tab == 'imagenes' ? 'active bg-dark text-info border-secondary border-bottom-0' : 'text-light'; ?>" href="?tab=imagenes">Imágenes</a></li>
    </ul>

    <?php if ($tab == 'objetos'): ?>
    <form method="GET" action="index.php" class="mb-4">
        <input type="hidden" name="tab" value="objetos">
        <div class="search-container shadow-sm d-flex gap-2">
            <input type="text" name="q" class="form-control" placeholder="🔍 Buscar por título, localización..." value="<?php echo htmlspecialchars($search); ?>">
            <button type="submit" class="btn btn-info text-white fw-bold px-3" title="Buscar">🔍</button>
            <button class="btn btn-outline-secondary d-flex align-items-center gap-2" type="button" data-bs-toggle="collapse" data-bs-target="#filtrosAvanzados">⚙️ Filtros</button>
        </div>
        <div class="collapse <?php echo $show_filters; ?> mt-2" id="filtrosAvanzados">
            <div class="toolbar shadow-sm">
                <div class="row g-2 align-items-end">
                    <div class="col-md-3">
                        <label class="form-label small text-muted mb-1">Localizaciones <small class="text-info">(Ctrl+Click)</small></label>
                        <select name="f_loc[]" class="form-select form-select-sm" multiple size="4">
                            <?php foreach($loc_options as $loc): ?>
                                <option value="<?php echo htmlspecialchars($loc); ?>" <?php echo in_array($loc, $f_loc) ? 'selected' : ''; ?>><?php echo htmlspecialchars($loc); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small text-muted mb-1">Tipo</label>
                        <select name="f_tipo" class="form-select form-select-sm">
                            <option value="">- Todos -</option>
                            <?php while($t = $tipos_db->fetch_assoc()): ?>
                                <option value="<?php echo htmlspecialchars($t['tipo']); ?>" <?php echo $f_tipo == $t['tipo'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($t['tipo']); ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small text-muted mb-1">Categoría</label>
                        <select name="f_t_obj" class="form-select form-select-sm">
                            <option value="">- Todas -</option>
                            <?php foreach($cat_options as $cat): ?>
                                <option value="<?php echo htmlspecialchars($cat); ?>" <?php echo $f_t_obj == $cat ? 'selected' : ''; ?>><?php echo htmlspecialchars($cat); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small text-muted mb-1">Ordenar por</label>
                        <select name="sort" class="form-select form-select-sm">
                            <option value="newest" <?php echo $sort == 'newest' ? 'selected' : ''; ?>>🕒 Recientes</option>
                            <option value="nombre_asc" <?php echo $sort == 'nombre_asc' ? 'selected' : ''; ?>>🔤 Nombre (A-Z)</option>
                            <option value="tipo_nombre" <?php echo $sort == 'tipo_nombre' ? 'selected' : ''; ?>>📁 Tipo > Nombre</option>
                        </select>
                    </div>
                    <div class="col-md-2 d-grid mt-4 pt-1"><a href="index.php?tab=objetos" class="btn btn-outline-danger btn-sm">Limpiar</a></div>
                </div>
            </div>
        </div>
    </form>
    
    <div class="row row-cols-2 row-cols-md-4 row-cols-lg-6 g-3">
        <?php if ($objetos->num_rows > 0): ?>
            <?php while($obj = $objetos->fetch_assoc()): 
                $titulo = !empty($obj['objeto']) ? $obj['objeto'] : 'Sin Título';
                $img = renderImg($obj['portada_http']);
            ?>
            <div class="col">
                <div class="card memento-card shadow-sm bg-dark position-relative" data-bs-toggle="modal" data-bs-target="#editObj<?php echo $obj['id']; ?>">
                    
                    <div class="position-absolute top-0 start-0 p-2" style="z-index: 10;">
                        <?php if($obj['cantidad'] > 1): ?>
                            <span class="badge bg-primary shadow-sm border border-secondary">x<?php echo htmlspecialchars($obj['cantidad']); ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="position-absolute top-0 end-0 p-2 d-flex flex-column gap-1 align-items-end" style="z-index: 10;">
                        <?php $locs = array_map('trim', explode(',', $obj['localizacion'] ?? '')); foreach($locs as $l): if($l): ?>
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
                    <form class="modal-content" method="POST" enctype="multipart/form-data">
                        <div class="modal-header"><h5 class="modal-title fs-6">Editar: <?php echo htmlspecialchars($titulo); ?></h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                        <div class="modal-body">
                            <input type="hidden" name="id" value="<?php echo $obj['id']; ?>">
                            
                            <div class="row">
                                <div class="col-9 mb-2"><label class="small text-muted">Título</label><input type="text" name="titulo" class="form-control form-control-sm" value="<?php echo htmlspecialchars($titulo); ?>" required></div>
                                <div class="col-3 mb-2"><label class="small text-muted text-info fw-bold">Cantidad</label><input type="number" name="cantidad" class="form-control form-control-sm border-info" value="<?php echo (int)($obj['cantidad'] ?? 1); ?>" min="1"></div>
                            </div>
                            
                            <div class="mb-2">
                                <label class="small text-muted">Imagen / Archivo</label>
                                <div class="d-flex flex-column gap-2">
                                    <div class="d-flex gap-2 align-items-center">
                                        <input type="text" name="portada_http" id="txt_edit_obj_<?php echo $obj['id']; ?>" class="form-control form-control-sm" list="listaImagenesInventario" value="<?php echo htmlspecialchars(basename($obj['portada_http'])); ?>" oninput="updatePreview('preview_edit_obj_<?php echo $obj['id']; ?>', this.value)">
                                        <input type="file" name="portada_file" id="file_edit_obj_<?php echo $obj['id']; ?>" class="d-none" accept="image/*" onchange="previewFile(this, 'preview_edit_obj_<?php echo $obj['id']; ?>', 'txt_edit_obj_<?php echo $obj['id']; ?>')">
                                        <div class="btn-group btn-group-sm">
                                            <button type="button" class="btn btn-secondary" onclick="openPicker('file_edit_obj_<?php echo $obj['id']; ?>', true)" title="Hacer foto">📷</button>
                                            <button type="button" class="btn btn-secondary" onclick="openPicker('file_edit_obj_<?php echo $obj['id']; ?>', false)" title="Adjuntar archivo de galería">📁</button>
                                        </div>
                                        <img id="preview_edit_obj_<?php echo $obj['id']; ?>" src="<?php echo htmlspecialchars($img); ?>" class="preview-img shadow-sm" onerror="this.src='https://via.placeholder.com/540x720?text=Error'">
                                    </div>
                                    <input type="text" name="portada_custom_name" class="form-control form-control-sm" placeholder="Nombre personalizado de archivo (Opcional)">
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-6 mb-2">
                                    <label class="small text-muted">Tipo</label>
                                    <select name="tipo" id="tipo_edit_<?php echo $obj['id']; ?>" class="form-select form-select-sm" onchange="toggleConditionals('edit_<?php echo $obj['id']; ?>')">
                                        <option value="Objetos" <?php echo ($obj['tipo'] == 'Objetos') ? 'selected' : ''; ?>>Objetos</option>
                                        <option value="Juegos" <?php echo ($obj['tipo'] == 'Juegos') ? 'selected' : ''; ?>>Juegos</option>
                                        <option value="Películas" <?php echo ($obj['tipo'] == 'Películas') ? 'selected' : ''; ?>>Películas</option>
                                    </select>
                                </div>
                                <div class="col-6 mb-2">
                                    <label class="small text-muted">Categoría</label>
                                    <input type="text" name="tipo_de_objeto" class="form-control form-control-sm" value="<?php echo htmlspecialchars($obj['tipo_de_objeto']); ?>" list="listaCategorias" autocomplete="off">
                                </div>
                            </div>

                            <div id="game_fields_edit_<?php echo $obj['id']; ?>" class="d-none bg-secondary bg-opacity-25 p-2 rounded mb-2 border border-secondary shadow-sm">
                                <div class="mb-2">
                                    <label class="small fw-bold">Géneros</label>
                                    <input type="text" name="generos" id="gen_edit_<?php echo $obj['id']; ?>" class="form-control form-control-sm" value="<?php echo htmlspecialchars($obj['generos'] ?? ''); ?>">
                                    <div class="tag-container mt-1">
                                        <?php foreach($gen_options as $go): ?>
                                            <span class="badge bg-secondary tag-btn" onclick="toggleTag('gen_edit_<?php echo $obj['id']; ?>', '<?php echo addslashes($go); ?>')">+ <?php echo htmlspecialchars($go); ?></span>
                                        <?php endforeach; ?>
                                    </div>
                                </div>

                                <div class="mb-2">
                                    <label class="small fw-bold">Formato (Físico/Digital)</label>
                                    <select name="formato" id="formato_edit_<?php echo $obj['id']; ?>" class="form-select form-select-sm" onchange="toggleConditionals('edit_<?php echo $obj['id']; ?>')">
                                        <option value="Físico" <?php echo (($obj['formato'] ?? '') == 'Físico') ? 'selected' : ''; ?>>💿 Físico</option>
                                        <option value="Digital" <?php echo (($obj['formato'] ?? '') == 'Digital') ? 'selected' : ''; ?>>☁️ Digital</option>
                                    </select>
                                </div>

                                <div id="phys_fields_edit_<?php echo $obj['id']; ?>" class="d-none mt-2 border-top border-secondary pt-2">
                                    <div class="mb-2">
                                        <label class="small fw-bold">Formato de Archivo</label>
                                        <input type="text" name="formato_de_archivo" class="form-control form-control-sm" value="<?php echo htmlspecialchars($obj['formato_de_archivo'] ?? ''); ?>" list="listaFormatosArchivo" autocomplete="off">
                                    </div>

                                    <div class="form-check form-switch mb-2">
                                        <input class="form-check-input" type="checkbox" name="en_la_caja" id="en_la_caja_edit_<?php echo $obj['id']; ?>" value="1" <?php echo (!empty($obj['en_la_caja'])) ? 'checked' : ''; ?> onchange="toggleConditionals('edit_<?php echo $obj['id']; ?>')">
                                        <label class="form-check-label small" for="en_la_caja_edit_<?php echo $obj['id']; ?>">📦 En la caja original (Completo)</label>
                                    </div>

                                    <div id="price_fields_edit_<?php echo $obj['id']; ?>" class="d-none">
                                        <label class="small fw-bold text-success">Precio de Venta (€)</label>
                                        <input type="number" name="precio_de_venta" class="form-control form-control-sm border-success" step="0.01" min="0" value="<?php echo htmlspecialchars($obj['precio_de_venta'] ?? '0.00'); ?>">
                                    </div>
                                </div>
                            </div>
                            <script>document.addEventListener("DOMContentLoaded", function() { toggleConditionals("edit_<?php echo $obj['id']; ?>"); });</script>

                            <div class="mb-3">
                                <label class="small text-muted fw-bold">Localizaciones</label>
                                <input type="text" name="localizacion" id="loc_edit_<?php echo $obj['id']; ?>" class="form-control form-control-sm" value="<?php echo htmlspecialchars($obj['localizacion']); ?>">
                                <div class="tag-container mt-1">
                                    <?php foreach($loc_options as $lo): ?>
                                        <span class="badge bg-secondary tag-btn" onclick="toggleTag('loc_edit_<?php echo $obj['id']; ?>', '<?php echo addslashes($lo); ?>')">+ <?php echo htmlspecialchars($lo); ?></span>
                                    <?php endforeach; ?>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="small text-muted fw-bold">Plataformas</label>
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
            <div class="col-12 text-center py-5 text-muted">No se han encontrado resultados.</div>
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

    <div class="modal fade" id="modal-objetos" tabindex="-1">
        <div class="modal-dialog"><form class="modal-content" method="POST" enctype="multipart/form-data">
            <div class="modal-header"><h5 class="modal-title">Nuevo Objeto</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-9 mb-2"><label class="small">Título</label><input type="text" name="titulo" class="form-control" required></div>
                    <div class="col-3 mb-2"><label class="small text-info fw-bold">Cantidad</label><input type="number" name="cantidad" class="form-control border-info" value="1" min="1"></div>
                </div>

                <div class="mb-2">
                    <label class="small">Imagen / Archivo</label>
                    <div class="d-flex flex-column gap-2">
                        <div class="d-flex gap-2 align-items-center">
                            <input type="text" name="portada_http" id="txt_new_obj" class="form-control" list="listaImagenesInventario" oninput="updatePreview('preview_new_obj', this.value)">
                            <input type="file" name="portada_file" id="file_new_obj" class="d-none" accept="image/*" onchange="previewFile(this, 'preview_new_obj', 'txt_new_obj')">
                            <div class="btn-group">
                                <button type="button" class="btn btn-secondary" onclick="openPicker('file_new_obj', true)" title="Hacer foto">📷</button>
                                <button type="button" class="btn btn-secondary" onclick="openPicker('file_new_obj', false)" title="Adjuntar archivo de galería">📁</button>
                            </div>
                            <img id="preview_new_obj" src="https://via.placeholder.com/540x720?text=Foto" class="preview-img shadow-sm" onerror="this.src='https://via.placeholder.com/540x720?text=Error'">
                        </div>
                        <input type="text" name="portada_custom_name" class="form-control form-control-sm" placeholder="Nombre personalizado (Opcional)">
                    </div>
                </div>

                <div class="row">
                    <div class="col-6 mb-2">
                        <label class="small">Tipo</label>
                        <select name="tipo" id="tipo_new" class="form-select" onchange="toggleConditionals('new')">
                            <option value="Objetos">Objetos</option>
                            <option value="Juegos">Juegos</option>
                            <option value="Películas">Películas</option>
                        </select>
                    </div>
                    <div class="col-6 mb-2">
                        <label class="small">Categoría</label>
                        <input type="text" name="tipo_de_objeto" class="form-control" list="listaCategorias" autocomplete="off">
                    </div>
                </div>

                <div id="game_fields_new" class="d-none bg-secondary bg-opacity-25 p-2 rounded mb-2 border border-secondary shadow-sm">
                    <div class="mb-2">
                        <label class="small fw-bold">Géneros</label>
                        <input type="text" name="generos" id="gen_new" class="form-control form-control-sm">
                        <div class="tag-container mt-1">
                            <?php foreach($gen_options as $go): ?>
                                <span class="badge bg-secondary tag-btn" onclick="toggleTag('gen_new', '<?php echo addslashes($go); ?>')">+ <?php echo htmlspecialchars($go); ?></span>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="mb-2">
                        <label class="small fw-bold">Formato (Físico/Digital)</label>
                        <select name="formato" id="formato_new" class="form-select form-select-sm" onchange="toggleConditionals('new')">
                            <option value="Físico">💿 Físico</option>
                            <option value="Digital">☁️ Digital</option>
                        </select>
                    </div>

                    <div id="phys_fields_new" class="mt-2 border-top border-secondary pt-2">
                        <div class="mb-2">
                            <label class="small fw-bold">Formato de Archivo</label>
                            <input type="text" name="formato_de_archivo" class="form-control form-control-sm" list="listaFormatosArchivo" autocomplete="off">
                        </div>

                        <div class="form-check form-switch mb-2">
                            <input class="form-check-input" type="checkbox" name="en_la_caja" id="en_la_caja_new" value="1" onchange="toggleConditionals('new')">
                            <label class="form-check-label small" for="en_la_caja_new">📦 En la caja original (Completo)</label>
                        </div>

                        <div id="price_fields_new" class="d-none">
                            <label class="small fw-bold text-success">Precio de Venta (€)</label>
                            <input type="number" name="precio_de_venta" class="form-control form-control-sm border-success" step="0.01" min="0" value="0.00">
                        </div>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="small fw-bold">Localizaciones</label>
                    <input type="text" name="localizacion" id="loc_new" class="form-control">
                    <div class="tag-container mt-1">
                        <?php foreach($loc_options as $lo): ?>
                            <span class="badge bg-secondary tag-btn" onclick="toggleTag('loc_new', '<?php echo addslashes($lo); ?>')">+ <?php echo htmlspecialchars($lo); ?></span>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="small fw-bold">Plataformas</label>
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
    
    <datalist id="listaCategorias"><?php foreach($cat_options as $cat): ?><option value="<?php echo htmlspecialchars($cat); ?>"></option><?php endforeach; ?></datalist>
    <datalist id="listaPlataformas"><?php foreach($plat_options as $plat): ?><option value="<?php echo htmlspecialchars($plat); ?>"></option><?php endforeach; ?></datalist>
    <datalist id="listaFormatosArchivo"><?php foreach($fa_options as $fo): ?><option value="<?php echo htmlspecialchars($fo); ?>"></option><?php endforeach; ?></datalist>
    <datalist id="listaImagenesInventario"><?php foreach($local_images as $img): ?><option value="<?php echo htmlspecialchars($img); ?>"></option><?php endforeach; ?></datalist>
    <?php endif; ?>


    <?php if ($tab == 'localizaciones'): ?>
    <form method="GET" action="index.php" class="mb-4">
        <input type="hidden" name="tab" value="localizaciones">
        <div class="search-container shadow-sm d-flex gap-2">
            <input type="text" name="q_loc" class="form-control" placeholder="🔍 Buscar nombre..." value="<?php echo htmlspecialchars($q_loc); ?>">
            <button type="submit" class="btn btn-info text-white fw-bold px-3" title="Buscar">🔍</button>
            <button class="btn btn-outline-secondary d-flex align-items-center gap-2" type="button" data-bs-toggle="collapse" data-bs-target="#filtrosLoc">⚙️ Filtros</button>
        </div>
        <div class="collapse <?php echo $show_filters_loc; ?> mt-2" id="filtrosLoc">
            <div class="toolbar shadow-sm">
                <div class="row g-2 align-items-end">
                    <div class="col-md-4">
                        <label class="form-label small text-muted mb-1">Categoría</label>
                        <select name="f_cat_loc" class="form-select form-select-sm">
                            <option value="">- Todas -</option>
                            <?php foreach($cat_loc_options as $cat): ?>
                                <option value="<?php echo htmlspecialchars($cat); ?>" <?php echo $f_cat_loc == $cat ? 'selected' : ''; ?>><?php echo htmlspecialchars($cat); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small text-muted mb-1">Ordenar por</label>
                        <select name="sort_loc" class="form-select form-select-sm">
                            <option value="cat_nombre" <?php echo $sort_loc == 'cat_nombre' ? 'selected' : ''; ?>>📁 Categoría > Nombre</option>
                            <option value="nombre_asc" <?php echo $sort_loc == 'nombre_asc' ? 'selected' : ''; ?>>🔤 Nombre (A - Z)</option>
                            <option value="nombre_desc" <?php echo $sort_loc == 'nombre_desc' ? 'selected' : ''; ?>>🔤 Nombre (Z - A)</option>
                            <option value="newest" <?php echo $sort_loc == 'newest' ? 'selected' : ''; ?>>🕒 Recientes</option>
                        </select>
                    </div>
                    <div class="col-md-4 d-grid"><a href="index.php?tab=localizaciones" class="btn btn-outline-danger btn-sm">Limpiar</a></div>
                </div>
            </div>
        </div>
    </form>

    <div class="row row-cols-2 row-cols-md-4 row-cols-lg-6 g-3">
        <?php if ($loc_paginadas->num_rows > 0): ?>
            <?php while($loc = $loc_paginadas->fetch_assoc()): 
                $img = renderImg($loc['foto_http']);
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
                    <form class="modal-content" method="POST" enctype="multipart/form-data">
                        <div class="modal-header"><h5 class="modal-title fs-6">Editar Localización</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                        <div class="modal-body">
                            <input type="hidden" name="id" value="<?php echo $loc['id']; ?>">
                            <div class="mb-2"><label class="small text-muted">Nombre</label><input type="text" name="nombre" class="form-control form-control-sm" value="<?php echo htmlspecialchars($loc['nombre']); ?>" required></div>
                            <div class="mb-2"><label class="small text-muted">Categoría</label><input type="text" name="categoria" class="form-control form-control-sm" value="<?php echo htmlspecialchars($loc['categoria']); ?>" list="listaCategoriasLoc" autocomplete="off"></div>
                            
                            <div class="mb-2">
                                <label class="small text-muted">Foto / Archivo</label>
                                <div class="d-flex flex-column gap-2">
                                    <div class="d-flex gap-2 align-items-center">
                                        <input type="text" name="foto_http" id="txt_edit_loc_<?php echo $loc['id']; ?>" class="form-control form-control-sm" list="listaImagenesInventario" value="<?php echo htmlspecialchars(basename($loc['foto_http'])); ?>" oninput="updatePreview('preview_edit_loc_<?php echo $loc['id']; ?>', this.value)">
                                        <input type="file" name="foto_file" id="file_edit_loc_<?php echo $loc['id']; ?>" class="d-none" accept="image/*" onchange="previewFile(this, 'preview_edit_loc_<?php echo $loc['id']; ?>', 'txt_edit_loc_<?php echo $loc['id']; ?>')">
                                        <div class="btn-group btn-group-sm">
                                            <button type="button" class="btn btn-secondary" onclick="openPicker('file_edit_loc_<?php echo $loc['id']; ?>', true)" title="Hacer foto">📷</button>
                                            <button type="button" class="btn btn-secondary" onclick="openPicker('file_edit_loc_<?php echo $loc['id']; ?>', false)" title="Adjuntar archivo de galería">📁</button>
                                        </div>
                                        <img id="preview_edit_loc_<?php echo $loc['id']; ?>" src="<?php echo htmlspecialchars($img); ?>" class="preview-loc shadow-sm" onerror="this.src='https://via.placeholder.com/540x720?text=Error'">
                                    </div>
                                    <input type="text" name="foto_custom_name" class="form-control form-control-sm" placeholder="Nombre personalizado (Opcional)">
                                </div>
                            </div>
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
        <?php else: ?>
            <div class="col-12 text-center py-5 text-muted">No se han encontrado resultados.</div>
        <?php endif; ?>
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

    <div class="modal fade" id="modal-localizaciones" tabindex="-1">
        <div class="modal-dialog"><form class="modal-content" method="POST" enctype="multipart/form-data">
            <div class="modal-header"><h5 class="modal-title">Nueva Localización</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <div class="mb-2"><label>Nombre (Único)</label><input type="text" name="nombre" class="form-control" required></div>
                <div class="mb-2"><label>Categoría</label><input type="text" name="categoria" class="form-control" list="listaCategoriasLoc" autocomplete="off"></div>
                <div class="mb-2">
                    <label class="small">Foto / Archivo</label>
                    <div class="d-flex flex-column gap-2">
                        <div class="d-flex gap-2 align-items-center">
                            <input type="text" name="foto_http" id="txt_new_loc" class="form-control" list="listaImagenesInventario" oninput="updatePreview('preview_new_loc', this.value)">
                            <input type="file" name="foto_file" id="file_new_loc" class="d-none" accept="image/*" onchange="previewFile(this, 'preview_new_loc', 'txt_new_loc')">
                            <div class="btn-group">
                                <button type="button" class="btn btn-secondary" onclick="openPicker('file_new_loc', true)" title="Hacer foto">📷</button>
                                <button type="button" class="btn btn-secondary" onclick="openPicker('file_new_loc', false)" title="Adjuntar archivo de galería">📁</button>
                            </div>
                            <img id="preview_new_loc" src="https://via.placeholder.com/540x720?text=Foto" class="preview-loc shadow-sm" onerror="this.src='https://via.placeholder.com/540x720?text=Error'">
                        </div>
                        <input type="text" name="foto_custom_name" class="form-control form-control-sm" placeholder="Nombre personalizado (Opcional)">
                    </div>
                </div>
                <div class="mb-2"><label>Descripción</label><textarea name="descripcion_del_contenido" class="form-control"></textarea></div>
            </div>
            <div class="modal-footer"><button type="submit" name="save_loc" class="btn btn-primary">Añadir</button></div>
        </form></div>
    </div>
    
    <datalist id="listaCategoriasLoc"><?php foreach($cat_loc_options as $cloc): ?><option value="<?php echo htmlspecialchars($cloc); ?>"></option><?php endforeach; ?></datalist>
    <datalist id="listaImagenesInventario"><?php foreach($local_images as $img): ?><option value="<?php echo htmlspecialchars($img); ?>"></option><?php endforeach; ?></datalist>
    <?php endif; ?>

    <?php if ($tab == 'imagenes'): ?>
    <div class="row row-cols-2 row-cols-md-4 row-cols-lg-6 g-3">
        <?php if (!empty($paginated_images)): ?>
            <?php foreach($paginated_images as $index => $img_name): 
                $img_url = './img/' . $img_name;
            ?>
            <div class="col">
                <div class="card memento-card shadow-sm bg-dark">
                    <img src="<?php echo htmlspecialchars($img_url); ?>" class="card-img-top memento-img" alt="Foto" style="cursor:zoom-in;" data-bs-toggle="modal" data-bs-target="#popupImg<?php echo $index; ?>" loading="lazy">
                    <div class="card-body p-2 text-center">
                        <div class="small text-truncate text-light mb-2" title="<?php echo htmlspecialchars($img_name); ?>"><?php echo htmlspecialchars($img_name); ?></div>
                        <div class="d-flex gap-1 justify-content-center">
                            <button class="btn btn-sm btn-outline-info" data-bs-toggle="modal" data-bs-target="#modalRenombrarImg<?php echo $index; ?>" title="Renombrar">✏️</button>
                            <a href="?tab=imagenes&delete_img=<?php echo urlencode($img_name); ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('¿Eliminar definitivamente la foto del servidor?')" title="Eliminar">🗑️</a>
                        </div>
                    </div>
                </div>
            </div>

            <div class="modal fade" id="popupImg<?php echo $index; ?>" tabindex="-1">
                <div class="modal-dialog modal-dialog-centered modal-lg">
                    <div class="modal-content bg-transparent border-0 text-center">
                        <div class="modal-body p-0 position-relative">
                            <button type="button" class="btn-close btn-close-white position-absolute top-0 end-0 m-3" data-bs-dismiss="modal"></button>
                            <img src="<?php echo htmlspecialchars($img_url); ?>" class="img-fluid rounded shadow" style="max-height: 82vh;">
                            <div class="text-white mt-2 bg-dark bg-opacity-75 p-2 rounded d-inline-block small"><?php echo htmlspecialchars($img_name); ?></div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="modal fade" id="modalRenombrarImg<?php echo $index; ?>" tabindex="-1">
                <div class="modal-dialog modal-sm modal-dialog-centered">
                    <form class="modal-content" method="POST">
                        <div class="modal-header"><h6 class="modal-title">Renombrar Archivo</h6><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                        <div class="modal-body">
                            <input type="hidden" name="old_name" value="<?php echo htmlspecialchars($img_name); ?>">
                            <div class="mb-2">
                                <label class="small text-muted">Nombre nuevo (sin extensión)</label>
                                <input type="text" name="new_name" class="form-control form-control-sm" value="<?php echo htmlspecialchars(pathinfo($img_name, PATHINFO_FILENAME)); ?>" required autocomplete="off">
                            </div>
                        </div>
                        <div class="modal-footer p-2">
                            <button type="submit" name="rename_img" class="btn btn-sm btn-primary w-100">Guardar</button>
                        </div>
                    </form>
                </div>
            </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="col-12 text-center py-5 text-muted">La carpeta img/ no tiene imágenes compatibles o está vacía.</div>
        <?php endif; ?>
    </div>

    <?php if ($total_pages_img > 1): ?>
        <nav class="mt-4"><ul class="pagination justify-content-center pagination-sm">
            <?php for($i=1; $i<=$total_pages_img; $i++): if($i == 1 || $i == $total_pages_img || ($i >= $page-2 && $i <= $page+2)): ?>
                <li class="page-item <?php echo $i==$page?'active':''; ?>"><a class="page-link" href="<?php echo urlParam(['p'=>$i]); ?>"><?php echo $i; ?></a></li>
            <?php elseif($i == $page-3 || $i == $page+3): ?>
                <li class="page-item disabled"><span class="page-link">...</span></li>
            <?php endif; endfor; ?>
        </ul></nav>
    <?php endif; ?>
    <?php endif; ?>

  </main>

  <?php include "{$src}frontend/footer.php"; ?>
</body>
</html>
<?php
session_start();
$src = '../../';

// Modo Debug para evitar el Error 500 en blanco
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if (!isset($_SESSION['user_id'])) {
    header("Location: {$src}src/login/index.php");
    exit();
}

require $src . 'backend/config/db.php';
global $conn;

$tab = $_GET['tab'] ?? 'objetos';

// --- ACCIONES DE BORRADO ---
if (isset($_GET['delete_obj'])) {
    $stmt = $conn->prepare("DELETE FROM inv_objetos WHERE id = ?");
    $stmt->bind_param("i", $_GET['delete_obj']);
    $stmt->execute();
    $stmt->close();
    header("Location: index.php?tab=objetos"); exit();
}
if (isset($_GET['delete_loc'])) {
    $stmt = $conn->prepare("DELETE FROM inv_localizaciones WHERE id = ?");
    $stmt->bind_param("i", $_GET['delete_loc']);
    $stmt->execute();
    $stmt->close();
    header("Location: index.php?tab=localizaciones"); exit();
}

// --- ACCIONES DE GUARDADO (POST) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['save_obj'])) {
        $id = $_POST['id'] ?? null;
        $titulo = $_POST['titulo'];
        
        // ACTUALIZADO: Ya no usamos juego ni peli, va todo a 'objeto'
        $stmt = $id 
            ? $conn->prepare("UPDATE inv_objetos SET objeto=?, localizacion=?, descripcion=?, tipo=?, plataformas=?, portada_http=? WHERE id=?")
            : $conn->prepare("INSERT INTO inv_objetos (objeto, localizacion, descripcion, tipo, plataformas, portada_http) VALUES (?, ?, ?, ?, ?, ?)");
        
        if ($stmt === false) {
            die("<div class='alert alert-danger m-4'>Error en SQL: " . $conn->error . "</div>");
        }

        if ($id) $stmt->bind_param("ssssssi", $titulo, $_POST['localizacion'], $_POST['descripcion'], $_POST['tipo'], $_POST['plataformas'], $_POST['portada_http'], $id);
        else $stmt->bind_param("ssssss", $titulo, $_POST['localizacion'], $_POST['descripcion'], $_POST['tipo'], $_POST['plataformas'], $_POST['portada_http']);
        
        $stmt->execute();
        header("Location: index.php?tab=objetos"); exit();
    }
    
    if (isset($_POST['save_loc'])) {
        $id = $_POST['id'] ?? null;
        $stmt = $id 
            ? $conn->prepare("UPDATE inv_localizaciones SET nombre=?, descripcion_del_contenido=?, categoria=?, foto_http=? WHERE id=?")
            : $conn->prepare("INSERT INTO inv_localizaciones (nombre, descripcion_del_contenido, categoria, foto_http) VALUES (?, ?, ?, ?)");
        
        if ($id) $stmt->bind_param("ssssi", $_POST['nombre'], $_POST['descripcion'], $_POST['categoria'], $_POST['foto_http'], $id);
        else $stmt->bind_param("ssss", $_POST['nombre'], $_POST['descripcion'], $_POST['categoria'], $_POST['foto_http']);
        
        $stmt->execute();
        header("Location: index.php?tab=localizaciones"); exit();
    }
}

// --- OBTENER DATOS ---
$objetos = $conn->query("SELECT * FROM inv_objetos ORDER BY id DESC");
$localizaciones = $conn->query("SELECT * FROM inv_localizaciones ORDER BY nombre ASC");

$loc_options = [];
while($row = $localizaciones->fetch_assoc()) {
    $loc_options[] = $row['nombre'];
}
$localizaciones->data_seek(0); 
?>
<!DOCTYPE html>
<html lang="es" data-bs-theme="dark">
<?php include "{$src}backend/config/ini.php"; ?>
<head>
    <style>
        .memento-card { transition: transform 0.2s; height: 100%; cursor: pointer; }
        .memento-card:hover { transform: scale(1.02); border-color: #0dcaf0; }
        .memento-img { height: 220px; object-fit: cover; background-color: #2c2c2c; border-bottom: 1px solid #3d3d3d; }
        .card-title { font-size: 0.9rem; font-weight: 600; }
        .badge-loc { position: absolute; top: 10px; right: 10px; background: rgba(0,0,0,0.7); }
    </style>
</head>
<body>
  <?php include "{$src}frontend/menu.php"; ?>
  
  <main class="containerB my-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modal-<?php echo $tab; ?>">
            + Añadir <?php echo ($tab == 'objetos') ? 'Objeto' : 'Localización'; ?>
        </button>
        <a href="csv.php"><button class="btn btn-outline-info">Importar</button></a>
    </div>

    <ul class="nav nav-tabs mb-4">
        <li class="nav-item">
            <a class="nav-link <?php echo $tab == 'objetos' ? 'active' : ''; ?>" href="?tab=objetos">Objetos</a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?php echo $tab == 'localizaciones' ? 'active' : ''; ?>" href="?tab=localizaciones">Localizaciones</a>
        </li>
    </ul>

    <?php if ($tab == 'objetos'): ?>
    <div class="row row-cols-2 row-cols-md-3 row-cols-lg-5 g-4">
        <?php while($obj = $objetos->fetch_assoc()): 
            // ACTUALIZADO: Leemos directamente la columna "objeto"
            $titulo = !empty($obj['objeto']) ? $obj['objeto'] : 'Sin Título';
            $img = !empty($obj['portada_http']) ? $obj['portada_http'] : 'https://via.placeholder.com/250x350?text=Sin+Foto';
        ?>
        <div class="col">
            <div class="card memento-card shadow-sm position-relative" data-bs-toggle="modal" data-bs-target="#editObj<?php echo $obj['id']; ?>">
                <span class="badge badge-loc text-white rounded-pill px-2 py-1"><i class="bi bi-geo-alt"></i> <?php echo htmlspecialchars($obj['localizacion'] ?? '-'); ?></span>
                <img src="<?php echo htmlspecialchars($img); ?>" class="card-img-top memento-img" alt="Portada">
                <div class="card-body p-2 text-center">
                    <h6 class="card-title text-truncate mb-1" title="<?php echo htmlspecialchars($titulo); ?>"><?php echo htmlspecialchars($titulo); ?></h6>
                    <small class="text-muted"><?php echo htmlspecialchars($obj['tipo'] ?? 'Objeto'); ?> | <?php echo htmlspecialchars($obj['plataformas'] ?? '-'); ?></small>
                </div>
            </div>
        </div>

        <div class="modal fade" id="editObj<?php echo $obj['id']; ?>" tabindex="-1">
            <div class="modal-dialog">
                <form class="modal-content" method="POST">
                    <div class="modal-header">
                        <h5 class="modal-title">Editar Objeto</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="id" value="<?php echo $obj['id']; ?>">
                        <div class="mb-2"><label>Título</label><input type="text" name="titulo" class="form-control" value="<?php echo htmlspecialchars($titulo); ?>" required></div>
                        <div class="mb-2"><label>URL Imagen (Portada)</label><input type="text" name="portada_http" class="form-control" value="<?php echo htmlspecialchars($obj['portada_http']); ?>"></div>
                        <div class="row">
                            <div class="col-6 mb-2">
                                <label>Tipo</label>
                                <input type="text" name="tipo" class="form-control" value="<?php echo htmlspecialchars($obj['tipo']); ?>" placeholder="Juego, Peli, etc.">
                            </div>
                            <div class="col-6 mb-2">
                                <label>Localización</label>
                                <select name="localizacion" class="form-select">
                                    <option value="">- Ninguna -</option>
                                    <?php foreach($loc_options as $loc): ?>
                                        <option value="<?php echo htmlspecialchars($loc); ?>" <?php echo ($obj['localizacion'] == $loc) ? 'selected' : ''; ?>><?php echo htmlspecialchars($loc); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="mb-2"><label>Plataformas</label><input type="text" name="plataformas" class="form-control" value="<?php echo htmlspecialchars($obj['plataformas']); ?>"></div>
                        <div class="mb-2"><label>Descripción</label><textarea name="descripcion" class="form-control" rows="3"><?php echo htmlspecialchars($obj['descripcion']); ?></textarea></div>
                    </div>
                    <div class="modal-footer justify-content-between">
                        <a href="?delete_obj=<?php echo $obj['id']; ?>" class="btn btn-outline-danger" onclick="return confirm('¿Seguro que quieres borrar este objeto?')">Borrar</a>
                        <button type="submit" name="save_obj" class="btn btn-primary">Guardar</button>
                    </div>
                </form>
            </div>
        </div>
        <?php endwhile; ?>
    </div>
    <?php endif; ?>

    <?php if ($tab == 'localizaciones'): ?>
    <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4">
        <?php while($loc = $localizaciones->fetch_assoc()): 
            $img = !empty($loc['foto_http']) ? $loc['foto_http'] : 'https://via.placeholder.com/400x200?text=Sin+Foto';
        ?>
        <div class="col">
            <div class="card memento-card shadow-sm" data-bs-toggle="modal" data-bs-target="#editLoc<?php echo $loc['id']; ?>">
                <img src="<?php echo htmlspecialchars($img); ?>" class="card-img-top" style="height:200px; object-fit:cover;">
                <div class="card-body">
                    <h5 class="card-title text-info"><?php echo htmlspecialchars($loc['nombre']); ?></h5>
                    <h6 class="card-subtitle mb-2 text-muted"><?php echo htmlspecialchars($loc['categoria']); ?></h6>
                    <p class="card-text small text-truncate"><?php echo htmlspecialchars($loc['descripcion_del_contenido']); ?></p>
                </div>
            </div>
        </div>

        <div class="modal fade" id="editLoc<?php echo $loc['id']; ?>" tabindex="-1">
            <div class="modal-dialog">
                <form class="modal-content" method="POST">
                    <div class="modal-header">
                        <h5 class="modal-title">Editar Localización</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="id" value="<?php echo $loc['id']; ?>">
                        <div class="mb-2"><label>Nombre</label><input type="text" name="nombre" class="form-control" value="<?php echo htmlspecialchars($loc['nombre']); ?>" required></div>
                        <div class="mb-2"><label>Categoría</label><input type="text" name="categoria" class="form-control" value="<?php echo htmlspecialchars($loc['categoria']); ?>"></div>
                        <div class="mb-2"><label>URL Foto</label><input type="text" name="foto_http" class="form-control" value="<?php echo htmlspecialchars($loc['foto_http']); ?>"></div>
                        <div class="mb-2"><label>Descripción</label><textarea name="descripcion_del_contenido" class="form-control" rows="3"><?php echo htmlspecialchars($loc['descripcion_del_contenido']); ?></textarea></div>
                    </div>
                    <div class="modal-footer justify-content-between">
                        <a href="?delete_loc=<?php echo $loc['id']; ?>" class="btn btn-outline-danger" onclick="return confirm('¿Borrar localización?')">Borrar</a>
                        <button type="submit" name="save_loc" class="btn btn-primary">Guardar</button>
                    </div>
                </form>
            </div>
        </div>
        <?php endwhile; ?>
    </div>
    <?php endif; ?>

  </main>

  <div class="modal fade" id="modal-objetos" tabindex="-1">
      <div class="modal-dialog"><form class="modal-content" method="POST"><div class="modal-header"><h5 class="modal-title">Nuevo Objeto</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body"><div class="mb-2"><label>Título</label><input type="text" name="titulo" class="form-control" required></div><div class="mb-2"><label>URL Imagen</label><input type="text" name="portada_http" class="form-control"></div><div class="row"><div class="col-6 mb-2"><label>Tipo</label><input type="text" name="tipo" class="form-control" value="Juego"></div><div class="col-6 mb-2"><label>Localización</label><select name="localizacion" class="form-select"><option value="">- Ninguna -</option><?php foreach($loc_options as $loc): ?><option value="<?php echo htmlspecialchars($loc); ?>"><?php echo htmlspecialchars($loc); ?></option><?php endforeach; ?></select></div></div><div class="mb-2"><label>Plataformas</label><input type="text" name="plataformas" class="form-control"></div><div class="mb-2"><label>Descripción</label><textarea name="descripcion" class="form-control"></textarea></div></div><div class="modal-footer"><button type="submit" name="save_obj" class="btn btn-primary">Añadir</button></div></form></div>
  </div>

  <div class="modal fade" id="modal-localizaciones" tabindex="-1">
      <div class="modal-dialog"><form class="modal-content" method="POST"><div class="modal-header"><h5 class="modal-title">Nueva Localización</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body"><div class="mb-2"><label>Nombre (Único)</label><input type="text" name="nombre" class="form-control" required></div><div class="mb-2"><label>Categoría</label><input type="text" name="categoria" class="form-control"></div><div class="mb-2"><label>URL Foto</label><input type="text" name="foto_http" class="form-control"></div><div class="mb-2"><label>Descripción</label><textarea name="descripcion_del_contenido" class="form-control"></textarea></div></div><div class="modal-footer"><button type="submit" name="save_loc" class="btn btn-primary">Añadir</button></div></form></div>
  </div>

  <?php include "{$src}frontend/footer.php"; ?>
</body>
</html>
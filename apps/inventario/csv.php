<?php
session_start();
$src = '../../';

// Protección de sesión
if (!isset($_SESSION['user_id'])) {
    header("Location: {$src}src/login/");
    exit();
}

require $src . 'backend/config/db.php';
global $conn;

// --- CONFIGURACIÓN DE CARPETA BACKUP ---
$backup_dir = './backup/';
if (!is_dir($backup_dir)) {
    @mkdir($backup_dir, 0777, true);
}

// =========================================================================
// FUNCIONES AUXILIARES DE EXPORTACIÓN
// =========================================================================

function generarCsvLocalizaciones($conn, $outputStream) {
    fputcsv($outputStream, ['portada_http', 'nombre', 'descripcion_del_contenido', 'categoria']);
    $query = $conn->query("SELECT foto_http, nombre, descripcion_del_contenido, categoria FROM inv_localizaciones ORDER BY id ASC");
    while ($row = $query->fetch_assoc()) {
        fputcsv($outputStream, [
            basename($row['foto_http'] ?? ''),
            $row['nombre'] ?? '',
            $row['descripcion_del_contenido'] ?? '',
            $row['categoria'] ?? ''
        ]);
    }
}

function generarCsvObjetos($conn, $outputStream) {
    fputcsv($outputStream, [
        'portada_http', 'objeto', 'objeto_v2', 'objeto_v3', 'localizacion', 'descripcion', 
        'tipo', 'tipo_de_objeto', 'cantidad', 'generos', 'plataformas', 'anio_de_estreno', 
        'formato', 'precio_de_venta', 'duracion', 'formato_de_archivo', 'en_la_caja'
    ]);
    $query = $conn->query("SELECT * FROM inv_objetos ORDER BY id ASC");
    while ($row = $query->fetch_assoc()) {
        fputcsv($outputStream, [
            basename($row['portada_http'] ?? ''),
            $row['objeto'] ?? '',
            '', '', 
            $row['localizacion'] ?? '',
            $row['descripcion'] ?? '',
            $row['tipo'] ?? '',
            $row['tipo_de_objeto'] ?? '',
            $row['cantidad'] ?? 1,
            $row['generos'] ?? '',
            $row['plataformas'] ?? '',
            $row['anio_de_estreno'] ?? '',
            $row['formato'] ?? '',
            $row['precio_de_venta'] ?? '0.00',
            $row['duracion'] ?? '',
            $row['formato_de_archivo'] ?? '',
            $row['en_la_caja'] ?? 0
        ]);
    }
}

// Función centralizada para crear el ZIP en una ruta específica
function crearBackupZip($conn, $rutaDestino) {
    $fileLoc = tmpfile();
    $fileObj = tmpfile();
    
    generarCsvLocalizaciones($conn, $fileLoc);
    generarCsvObjetos($conn, $fileObj);
    
    $metaLoc = stream_get_meta_data($fileLoc);
    $metaObj = stream_get_meta_data($fileObj);
    
    $zip = new ZipArchive();
    $exito = false;
    if ($zip->open($rutaDestino, ZipArchive::CREATE | ZipArchive::OVERWRITE) === TRUE) {
        $zip->addFile($metaLoc['uri'], 'localizaciones.csv');
        $zip->addFile($metaObj['uri'], 'objetos.csv');
        $zip->close();
        $exito = true;
    }
    
    fclose($fileLoc);
    fclose($fileObj);
    return $exito;
}

// =========================================================================
// ACCIONES DIRECTAS (DESCARGAS INDIVIDUALES)
// =========================================================================

// 1. Exportar solo Localizaciones
if (isset($_GET['export']) && $_GET['export'] === 'loc') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="localizaciones_' . date('Y-m-d') . '.csv"');
    $output = fopen('php://output', 'w');
    generarCsvLocalizaciones($conn, $output);
    fclose($output);
    exit();
}

// 2. Exportar solo Objetos
if (isset($_GET['export']) && $_GET['export'] === 'obj') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="objetos_' . date('Y-m-d') . '.csv"');
    $output = fopen('php://output', 'w');
    generarCsvObjetos($conn, $output);
    fclose($output);
    exit();
}

// =========================================================================
// PROCESAMIENTO DE FORMULARIOS (POST) Y MENSAJES
// =========================================================================
$mensaje = '';
$tipo_mensaje = '';

// Guardar Backup en Servidor
if (isset($_POST['save_backup_server'])) {
    // Añadimos hora, minutos y segundos para no sobreescribir si se hacen varios el mismo día
    $zipName = date('Y-m-d_H-i-s') . '_inventario-adriru.es.zip';
    $rutaFinal = $backup_dir . $zipName;
    
    if (crearBackupZip($conn, $rutaFinal)) {
        $mensaje = "✅ Backup generado y guardado en el servidor correctamente: <b>$zipName</b>";
        $tipo_mensaje = "success";
    } else {
        $mensaje = "❌ Error al crear el archivo ZIP en el servidor.";
        $tipo_mensaje = "danger";
    }
}

// Borrar Backups (Individual o Múltiple)
if (isset($_POST['delete_backups'])) {
    $archivos_a_borrar = $_POST['backups_to_delete'] ?? [];
    $borrados = 0;
    foreach ($archivos_a_borrar as $archivo) {
        $archivoLimpio = basename($archivo); // Seguridad: Evita salir de la carpeta
        $ruta = $backup_dir . $archivoLimpio;
        if (file_exists($ruta) && is_file($ruta) && pathinfo($ruta, PATHINFO_EXTENSION) === 'zip') {
            if (unlink($ruta)) $borrados++;
        }
    }
    if ($borrados > 0) {
        $mensaje = "✅ Se han eliminado $borrados backup(s).";
        $tipo_mensaje = "info";
    }
}

// --- IMPORTAR LOCALIZACIONES ---
if (isset($_POST['import_loc']) && isset($_FILES['file_loc'])) {
    $file_tmp = $_FILES['file_loc']['tmp_name'];
    if (($handle = fopen($file_tmp, "r")) !== FALSE) {
        fgetcsv($handle); 
        $contador = 0;
        while (($data = fgetcsv($handle, 10000, ",")) !== FALSE) {
            if (empty(trim($data[1] ?? ''))) continue;
            $nombre = trim($data[1] ?? '');
            $desc = trim($data[2] ?? '');
            $cat = trim($data[3] ?? '');
            $foto = basename(trim($data[0] ?? ''));

            $stmt = $conn->prepare("INSERT IGNORE INTO inv_localizaciones (nombre, descripcion_del_contenido, categoria, foto_http) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("ssss", $nombre, $desc, $cat, $foto);
            if ($stmt->execute() && $stmt->affected_rows > 0) $contador++;
        }
        fclose($handle);
        $mensaje = "✅ Localizaciones: $contador importadas correctamente.";
        $tipo_mensaje = "success";
    }
}

// --- IMPORTAR OBJETOS ---
if (isset($_POST['import_obj']) && isset($_FILES['file_obj'])) {
    $file_tmp = $_FILES['file_obj']['tmp_name'];
    if (($handle = fopen($file_tmp, "r")) !== FALSE) {
        fgetcsv($handle); 
        $contador = 0;
        $conn->query("SET FOREIGN_KEY_CHECKS = 0;");

        while (($data = fgetcsv($handle, 10000, ",")) !== FALSE) {
            $nombre_final = trim($data[1] ?? '');
            if (empty($nombre_final)) $nombre_final = trim($data[2] ?? '');
            if (empty($nombre_final)) $nombre_final = trim($data[3] ?? '');

            if (empty($nombre_final)) continue;

            $portada = basename(trim($data[0] ?? '')); 
            $loc     = (isset($data[4]) && trim($data[4]) !== '') ? trim($data[4]) : null;
            $desc    = trim($data[5] ?? '');
            $tipo    = trim($data[6] ?? '');
            $t_obj   = trim($data[7] ?? '');
            $cant    = is_numeric($data[8] ?? '') ? (int)$data[8] : 1;
            $gen     = trim($data[9] ?? '');
            $plat    = trim($data[10] ?? '');
            $anio    = trim($data[11] ?? '');
            $form    = trim($data[12] ?? '');
            $precio  = trim($data[13] ?? '');
            $dur     = trim($data[14] ?? '');
            $f_arc   = trim($data[15] ?? '');
            $caja    = trim($data[16] ?? '');

            try {
                $stmt = $conn->prepare("INSERT INTO inv_objetos (objeto, localizacion, descripcion, tipo, tipo_de_objeto, cantidad, generos, plataformas, anio_de_estreno, formato, precio_de_venta, duracion, formato_de_archivo, en_la_caja, portada_http) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

                $stmt->bind_param("sssssssisssssss",
                    $nombre_final, $loc, $desc, $tipo, $t_obj, $cant,
                    $gen, $plat, $anio, $form, $precio, $dur, $f_arc, $caja, $portada
                );

                if ($stmt->execute()) $contador++;
            } catch (Exception $e) {}
        }
        $conn->query("SET FOREIGN_KEY_CHECKS = 1;");
        fclose($handle);
        $mensaje .= ($mensaje ? "<br>" : "") . "✅ Objetos: $contador importados correctamente.";
        $tipo_mensaje = "success";
    }
}

// Escanear carpeta de backups para la tabla
$backups_guardados = [];
if (is_dir($backup_dir)) {
    $archivos = scandir($backup_dir);
    foreach ($archivos as $archivo) {
        if (pathinfo($archivo, PATHINFO_EXTENSION) === 'zip') {
            $backups_guardados[] = [
                'nombre' => $archivo,
                'tamano' => round(filesize($backup_dir . $archivo) / 1024, 2) . ' KB',
                'fecha' => date("d/m/Y H:i:s", filemtime($backup_dir . $archivo))
            ];
        }
    }
    // Ordenar del más reciente al más antiguo
    usort($backups_guardados, function($a, $b) {
        return strtotime(str_replace('/', '-', $b['fecha'])) - strtotime(str_replace('/', '-', $a['fecha']));
    });
}
?>

<!DOCTYPE html>
<html lang="es" data-bs-theme="dark">
<?php include "{$src}backend/config/ini.php"; ?>
<body>
  <?php include "{$src}frontend/menu.php"; ?>
  
  <main class="container my-5" style="max-width: 900px;">
    
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
        <a href="./" class="btn btn-outline-info">⬅️ Volver al Inventario</a>
        
        <div class="d-flex gap-2 flex-wrap">
            <form method="POST" class="m-0">
                <button type="submit" name="save_backup_server" class="btn btn-warning fw-bold text-dark shadow-sm">📦 Hacer Backup</button>
            </form>
        </div>
    </div>
    
    <?php if ($mensaje): ?> 
        <div class="alert alert-<?php echo $tipo_mensaje; ?> alert-dismissible fade show shadow-sm" role="alert">
            <?php echo $mensaje; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div> 
    <?php endif; ?>
    
    <div class="row g-4 mb-5">
        <div class="col-md-6">
            <div class="card border-primary h-100 shadow-sm">
                <div class="card-header bg-primary text-white fw-bold d-flex justify-content-between align-items-center">
                    <span>1. Localizaciones</span>
                    <a href="?export=loc" class="btn btn-sm btn-light text-primary fw-bold p-1 px-2" title="Descargar CSV actual">⬇️ Exportar</a>
                </div>
                <div class="card-body d-flex flex-column justify-content-between">
                    <p class="small text-muted mb-3">Sube un archivo .CSV para rellenar las localizaciones de tus almacenes, cajas o disqueteras.</p>
                    <form method="POST" enctype="multipart/form-data">
                        <input type="file" name="file_loc" class="form-control mb-3" accept=".csv" required>
                        <button type="submit" name="import_loc" class="btn btn-primary w-100 fw-bold">📤 Importar CSV</button>
                    </form>
                </div>
            </div>
        </div>
        
        <div class="col-md-6">
            <div class="card border-success h-100 shadow-sm">
                <div class="card-header bg-success text-white fw-bold d-flex justify-content-between align-items-center">
                    <span>2. Objetos / Colección</span>
                    <a href="?export=obj" class="btn btn-sm btn-light text-success fw-bold p-1 px-2" title="Descargar CSV actual">⬇️ Exportar</a>
                </div>
                <div class="card-body d-flex flex-column justify-content-between">
                    <p class="small text-muted mb-3">Sube un archivo .CSV para añadir de golpe juegos, películas u objetos dentro de sus localizaciones.</p>
                    <form method="POST" enctype="multipart/form-data">
                        <input type="file" name="file_obj" class="form-control mb-3" accept=".csv" required>
                        <button type="submit" name="import_obj" class="btn btn-success w-100 fw-bold">📤 Importar CSV</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="card border-secondary shadow-sm">
        <div class="card-header bg-secondary text-white fw-bold d-flex justify-content-between align-items-center">
            <span>📁 Backups guardados en el Servidor</span>
            <span class="badge bg-dark"><?php echo count($backups_guardados); ?></span>
        </div>
        <div class="card-body p-0">
            <?php if(empty($backups_guardados)): ?>
                <div class="p-4 text-center text-muted">No hay copias de seguridad guardadas en la carpeta <code>./backup/</code>.</div>
            <?php else: ?>
                <form method="POST" onsubmit="return confirm('¿Estás seguro de que deseas eliminar los backups seleccionados?');">
                    <div class="table-responsive">
                        <table class="table table-dark table-hover table-striped m-0 align-middle">
                            <thead>
                                <tr>
                                    <th style="width: 40px;" class="text-center">
                                        <input class="form-check-input border-secondary" type="checkbox" id="selectAllBackups" onclick="toggleAll(this)">
                                    </th>
                                    <th>Nombre del Archivo</th>
                                    <th>Fecha</th>
                                    <th>Tamaño</th>
                                    <th class="text-end">Acción</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($backups_guardados as $b): ?>
                                <tr>
                                    <td class="text-center">
                                        <input class="form-check-input border-secondary backup-checkbox" type="checkbox" name="backups_to_delete[]" value="<?php echo htmlspecialchars($b['nombre']); ?>">
                                    </td>
                                    <td class="text-info fw-medium font-monospace small"><?php echo htmlspecialchars($b['nombre']); ?></td>
                                    <td class="small"><?php echo htmlspecialchars($b['fecha']); ?></td>
                                    <td class="small text-muted"><?php echo htmlspecialchars($b['tamano']); ?></td>
                                    <td class="text-end">
                                        <a href="<?php echo $backup_dir . htmlspecialchars($b['nombre']); ?>" class="btn btn-sm btn-outline-info" title="Descargar" download>⬇️</a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="p-3 border-top border-secondary bg-dark bg-opacity-50 text-end">
                        <button type="submit" name="delete_backups" class="btn btn-danger btn-sm fw-bold">🗑️ Borrar Seleccionados</button>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </div>
  </main>

  <script>
      function toggleAll(source) {
          checkboxes = document.querySelectorAll('.backup-checkbox');
          for(var i=0, n=checkboxes.length;i<n;i++) {
              checkboxes[i].checked = source.checked;
          }
      }
  </script>

</body>
</html>
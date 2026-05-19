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

// =========================================================================
// LÓGICA DE EXPORTACIÓN (Debe ejecutarse antes de cualquier salida HTML)
// =========================================================================

// Función auxiliar para generar el stream de un CSV de Localizaciones
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

// Función auxiliar para generar el stream de un CSV de Objetos
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
            '', // objeto_v2 vacío por compatibilidad con tus columnas de importación
            '', // objeto_v3 vacío
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

// 3. Exportar TODO comprimido en un archivo .ZIP
if (isset($_GET['export']) && $_GET['export'] === 'zip') {
    $zipName = date('Y-m-d') . '_inventario-adriru.es.zip';
    
    // Creamos archivos temporales en memoria / sistema seguro
    $fileLoc = tmpfile();
    $fileObj = tmpfile();
    
    generarCsvLocalizaciones($conn, $fileLoc);
    generarCsvObjetos($conn, $fileObj);
    
    // Obtenemos las rutas de los archivos temporales para guardarlos en el Zip
    $metaLoc = stream_get_meta_data($fileLoc);
    $metaObj = stream_get_meta_data($fileObj);
    
    $zip = new ZipArchive();
    $zipFile = tempnam(sys_get_temp_dir(), 'zip');
    
    if ($zip->open($zipFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) === TRUE) {
        $zip->addFile($metaLoc['uri'], 'localizaciones.csv');
        $zip->addFile($metaObj['uri'], 'objetos.csv');
        $zip->close();
        
        // Lanzamos la descarga del ZIP
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="' . $zipName . '"');
        header('Content-Length: ' . filesize($zipFile));
        readfile($zipFile);
        
        // Limpieza de temporales
        unlink($zipFile);
        fclose($fileLoc);
        fclose($fileObj);
        exit();
    }
}

// =========================================================================
// LÓGICA DE IMPORTACIÓN (Procesa los formularios POST enviados)
// =========================================================================
$mensaje = '';
$tipo_mensaje = '';

// --- IMPORTAR LOCALIZACIONES ---
if (isset($_POST['import_loc']) && isset($_FILES['file_loc'])) {
    $file_tmp = $_FILES['file_loc']['tmp_name'];
    if (($handle = fopen($file_tmp, "r")) !== FALSE) {
        fgetcsv($handle); // Saltamos cabecera
        $contador = 0;
        while (($data = fgetcsv($handle, 10000, ",")) !== FALSE) {
            if (empty(trim($data[1] ?? ''))) continue;
            $nombre = trim($data[1] ?? '');
            $desc = trim($data[2] ?? '');
            $cat = trim($data[3] ?? '');
            $foto = basename(trim($data[0] ?? '')); // Limpiamos la ruta guardando solo el archivo

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
        fgetcsv($handle); // Saltamos cabecera
        $contador = 0;
        $conn->query("SET FOREIGN_KEY_CHECKS = 0;");

        while (($data = fgetcsv($handle, 10000, ",")) !== FALSE) {
            $nombre_final = trim($data[1] ?? '');
            if (empty($nombre_final)) $nombre_final = trim($data[2] ?? '');
            if (empty($nombre_final)) $nombre_final = trim($data[3] ?? '');

            if (empty($nombre_final)) continue;

            $portada = basename(trim($data[0] ?? '')); // Limpiamos la ruta guardando solo el archivo
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
?>

<!DOCTYPE html>
<html lang="es" data-bs-theme="dark">
<?php include "{$src}backend/config/ini.php"; ?>
<body>
  <?php include "{$src}frontend/menu.php"; ?>
  
  <main class="container my-5" style="max-width: 900px;">
    
    <div class="d-flex justify-content-between align-items-center mb-4">
        <a href="./" class="btn btn-outline-info">⬅️ Volver al Inventario</a>
        <a href="?export=zip" class="btn btn-warning fw-bold text-dark shadow-sm">📦 Exportar Todo de una vez (.ZIP)</a>
    </div>
    
    <?php if ($mensaje): ?> 
        <div class="alert alert-<?php echo $tipo_mensaje; ?> alert-dismissible fade show shadow-sm" role="alert">
            <?php echo $mensaje; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div> 
    <?php endif; ?>
    
    <div class="row g-4">
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
  </main>
</body>
</html>
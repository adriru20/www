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

$mensaje = '';
$tipo_mensaje = '';

// --- IMPORTAR LOCALIZACIONES (Se mantiene igual) ---
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
            $foto = trim($data[0] ?? ''); // Foto en col 0

            $stmt = $conn->prepare("INSERT IGNORE INTO inv_localizaciones (nombre, descripcion_del_contenido, categoria, foto_http) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("ssss", $nombre, $desc, $cat, $foto);
            if ($stmt->execute() && $stmt->affected_rows > 0) $contador++;
        }
        fclose($handle);
        $mensaje = "✅ Localizaciones: $contador importadas.";
        $tipo_mensaje = "success";
    }
}

// --- IMPORTAR OBJETOS (Actualizado con campo único) ---
if (isset($_POST['import_obj']) && isset($_FILES['file_obj'])) {
    $file_tmp = $_FILES['file_obj']['tmp_name'];
    if (($handle = fopen($file_tmp, "r")) !== FALSE) {
        fgetcsv($handle);
        $contador = 0;
        $conn->query("SET FOREIGN_KEY_CHECKS = 0;");

        while (($data = fgetcsv($handle, 10000, ",")) !== FALSE) {
            // Lógica para elegir el nombre: Miramos col 1, luego 2, luego 3
            $nombre_final = trim($data[1] ?? '');
            if (empty($nombre_final)) $nombre_final = trim($data[2] ?? '');
            if (empty($nombre_final)) $nombre_final = trim($data[3] ?? '');

            if (empty($nombre_final)) continue; // Si sigue vacío, no hay título

            $portada = trim($data[0] ?? '');
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

                $stmt->bind_param("sssssisssssssss",
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
  <main class="container my-5">
    <a href="./" class="btn btn-outline-info">Volver al Inventario</a>
    <br/>
    <?php if ($mensaje): ?> <div class="alert alert-<?php echo $tipo_mensaje; ?>"><?php echo $mensaje; ?></div> <?php endif; ?>
    <div class="row g-4">
        <div class="col-md-6">
            <div class="card border-primary h-100">
                <div class="card-header bg-primary">1. Localizaciones</div>
                <div class="card-body"><form method="POST" enctype="multipart/form-data"><input type="file" name="file_loc" class="form-control mb-3" accept=".csv" required><button type="submit" name="import_loc" class="btn btn-primary w-100">Subir</button></form></div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card border-success h-100">
                <div class="card-header bg-success">2. Objetos</div>
                <div class="card-body"><form method="POST" enctype="multipart/form-data"><input type="file" name="file_obj" class="form-control mb-3" accept=".csv" required><button type="submit" name="import_obj" class="btn btn-success w-100">Subir</button></form></div>
            </div>
        </div>
    </div>
  </main>
</body>
</html>
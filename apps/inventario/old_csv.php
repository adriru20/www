<?php
session_start();
$src = '../../';

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Protección de sesión
if (!isset($_SESSION['user_id'])) {
    header("Location: {$src}src/login/index.php");
    exit();
}

require $src . 'backend/config/db.php';
global $conn;

$mensaje = '';
$tipo_mensaje = '';

// --- IMPORTAR LOCALIZACIONES ---
if (isset($_POST['import_loc']) && isset($_FILES['file_loc'])) {
    $file_tmp = $_FILES['file_loc']['tmp_name'];
    
    if (($handle = fopen($file_tmp, "r")) !== FALSE) {
        fgetcsv($handle); 
        $contador = 0;
        
        while (($data = fgetcsv($handle, 10000, ",")) !== FALSE) {
            if (empty(trim($data[1] ?? ''))) continue; 

            // CORRECCIÓN: La foto está en la columna 0, no en la 4
            $foto = trim($data[0] ?? ''); 
            $nombre = trim($data[1] ?? '');
            $desc = trim($data[2] ?? '');
            $cat = trim($data[3] ?? '');

            try {
                $stmt = $conn->prepare("INSERT IGNORE INTO inv_localizaciones (nombre, descripcion_del_contenido, categoria, foto_http) VALUES (?, ?, ?, ?)");
                $stmt->bind_param("ssss", $nombre, $desc, $cat, $foto);
                if ($stmt->execute() && $stmt->affected_rows > 0) {
                    $contador++;
                }
            } catch (Exception $e) {}
        }
        fclose($handle);
        $mensaje = "✅ Se han importado $contador nuevas localizaciones.";
        $tipo_mensaje = "success";
    }
}

// --- IMPORTAR OBJETOS ---
if (isset($_POST['import_obj']) && isset($_FILES['file_obj'])) {
    $file_tmp = $_FILES['file_obj']['tmp_name'];
    
    if (($handle = fopen($file_tmp, "r")) !== FALSE) {
        fgetcsv($handle); 
        $contador = 0;

        // Desactivamos la restricción de llaves foráneas temporalmente 
        // para asegurar que importe TODOS los objetos, aunque haya fallos de nombre en la localización
        $conn->query("SET FOREIGN_KEY_CHECKS = 0;");

        while (($data = fgetcsv($handle, 10000, ",")) !== FALSE) {
            if (empty(trim($data[1] ?? '')) && empty(trim($data[2] ?? '')) && empty(trim($data[3] ?? ''))) continue;

            $cant = isset($data[8]) && is_numeric($data[8]) ? (int)$data[8] : 1;
            
            // CORRECCIÓN: La imagen está en la columna 0, no en la 17
            $portada_http = trim($data[0] ?? ''); 
            
            $d1 = trim($data[1] ?? '');
            $d2 = trim($data[2] ?? '');
            $d3 = trim($data[3] ?? '');
            $d4 = (isset($data[4]) && trim($data[4]) !== '') ? trim($data[4]) : null;
            $d5 = trim($data[5] ?? '');
            $d6 = trim($data[6] ?? '');
            $d7 = trim($data[7] ?? '');
            $d9 = trim($data[9] ?? '');
            $d10 = trim($data[10] ?? '');
            $d11 = trim($data[11] ?? '');
            $d12 = trim($data[12] ?? '');
            $d13 = trim($data[13] ?? '');
            $d14 = trim($data[14] ?? '');
            $d15 = trim($data[15] ?? '');
            $d16 = trim($data[16] ?? '');

            try {
                $stmt = $conn->prepare("INSERT INTO inv_objetos (objeto, juego, peli, localizacion, descripcion, tipo, tipo_de_objeto, cantidad, generos, plataformas, anio_de_estreno, formato, precio_de_venta, duracion, formato_de_archivo, en_la_caja, portada_http) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                
                $stmt->bind_param("sssssssisssssssss", 
                    $d1, $d2, $d3, $d4, $d5, $d6, $d7, 
                    $cant, 
                    $d9, $d10, $d11, $d12, $d13, $d14, $d15, $d16, $portada_http
                );
                
                if ($stmt->execute()) {
                    $contador++;
                }
            } catch (Exception $e) {}
        }
        // Volvemos a activar la seguridad de base de datos
        $conn->query("SET FOREIGN_KEY_CHECKS = 1;");
        fclose($handle);
        
        $mensaje = "✅ Se han importado $contador objetos correctamente.";
        $tipo_mensaje = "success";
    }
}
?>

<!DOCTYPE html>
<html lang="es" data-bs-theme="dark">
<?php include "{$src}backend/config/ini.php"; ?>
<head>
    <title>Importar Memento a MySQL</title>
</head>
<body>
  <?php include "{$src}frontend/menu.php"; ?>
  
  <main class="container my-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>📥 Importador de Archivos (.csv)</h2>
        <a href="index.php" class="btn btn-outline-info">Volver al Inventario</a>
    </div>

    <?php if ($mensaje != ''): ?>
        <div class="alert alert-<?php echo $tipo_mensaje; ?> shadow-sm">
            <?php echo $mensaje; ?>
        </div>
    <?php endif; ?>

    <div class="row g-4">
        <div class="col-md-6">
            <div class="card border-primary shadow-sm h-100">
                <div class="card-header bg-primary text-white fw-bold">1. Importar Localizaciones</div>
                <div class="card-body d-flex flex-column">
                    <form action="" method="POST" enctype="multipart/form-data" class="mt-auto">
                        <div class="mb-3">
                            <input class="form-control" type="file" name="file_loc" accept=".csv" required>
                        </div>
                        <button type="submit" name="import_loc" class="btn btn-primary w-100">Subir Localizaciones</button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="card border-success shadow-sm h-100">
                <div class="card-header bg-success text-white fw-bold">2. Importar Objetos</div>
                <div class="card-body d-flex flex-column">
                    <form action="" method="POST" enctype="multipart/form-data" class="mt-auto">
                        <div class="mb-3">
                            <input class="form-control" type="file" name="file_obj" accept=".csv" required>
                        </div>
                        <button type="submit" name="import_obj" class="btn btn-success w-100">Subir Objetos</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
  </main>

  <?php include "{$src}frontend/footer.php"; ?>
</body>
</html>
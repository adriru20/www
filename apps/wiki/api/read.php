<?php
$file = $_GET['file'] ?? '';
$baseDir = realpath(__DIR__ . '/../vault');
$filePath = realpath($baseDir . '/' . $file);

// Seguridad: Validar que el archivo esté dentro de la carpeta vault
if ($filePath && strpos($filePath, $baseDir) === 0 && file_exists($filePath)) {
    echo file_get_contents($filePath);
} else {
    http_response_code(404);
    echo "Archivo no encontrado.";
}
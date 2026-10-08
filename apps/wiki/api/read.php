<?php
require_once __DIR__ . '/../../../backend/config/session.php';
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    exit('No autorizado.');
}

$file = (string)($_GET['file'] ?? '');
$baseDir = realpath(__DIR__ . '/../vault');
$filePath = realpath($baseDir . '/' . $file);

// Seguridad: el archivo debe estar dentro de vault (con separador, para no aceptar
// carpetas hermanas como "vault2") y ser una nota Markdown
if ($baseDir && $filePath
    && strpos($filePath, $baseDir . DIRECTORY_SEPARATOR) === 0
    && is_file($filePath)
    && strtolower(pathinfo($filePath, PATHINFO_EXTENSION)) === 'md') {
    header('Content-Type: text/plain; charset=utf-8');
    echo file_get_contents($filePath);
} else {
    http_response_code(404);
    echo "Archivo no encontrado.";
}

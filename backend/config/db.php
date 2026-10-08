<?php
// Las credenciales NO van en el repositorio.
// Se leen de backend/config/db.local.php (ignorado por Git, se crea a mano en cada
// entorno; ver db.local.example.php) o, si no existe, de variables de entorno.
$local = __DIR__ . '/db.local.php';
if (is_file($local)) {
    require $local; // define $servername, $username, $password, $dbname
} else {
    $servername = getenv('DB_HOST') ?: '';
    $username   = getenv('DB_USER') ?: '';
    $password   = getenv('DB_PASS') ?: '';
    $dbname     = getenv('DB_NAME') ?: '';
}

mysqli_report(MYSQLI_REPORT_OFF);
$conn = @new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    error_log('Error de conexión a la BD: ' . $conn->connect_error);
    http_response_code(500);
    die('<p>Error en la conexión a la base de datos.</p>');
}

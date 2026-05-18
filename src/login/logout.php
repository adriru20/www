<?php
session_start(); // Iniciar la sesión para poder destruirla

// Eliminar todas las variables de sesión
$_SESSION = array();

// Si se desea destruir la sesión completamente, borramos también la cookie de sesión.
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Finalmente, destruir la sesión.
session_destroy();

// Redirigir al índice principal (o al login) indicando que la sesión está cerrada
header("Location: /src/login/index.php?msg=session_closed");

exit();
?>
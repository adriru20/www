<?php
// Protección CSRF para formularios (POST) y enlaces de acción (GET).
// Requiere sesión iniciada.
// Las funciones van dentro de un if para poder incluir el fichero más de una vez
// (ini.php incluye todo backend/functions/ y las apps también lo requieren).
if (!function_exists('csrf_token')) {

function csrf_token(): string {
  if (session_status() === PHP_SESSION_NONE) session_start();
  if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
  }
  return $_SESSION['csrf'];
}

// Campo oculto para formularios POST
function csrf_input(): string {
  return '<input type="hidden" name="csrf" value="' . htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') . '">';
}

// Parámetro para añadir a enlaces de acción (borrados, etc.)
function csrf_query(): string {
  return 'csrf=' . urlencode(csrf_token());
}

function csrf_valid(?string $token): bool {
  return is_string($token) && $token !== '' && hash_equals(csrf_token(), $token);
}

function csrf_fail(): void {
  http_response_code(403);
  die('Petición no válida (token CSRF). Recarga la página e inténtalo de nuevo.');
}

// Exige token válido en cualquier POST
function csrf_verify_post(): void {
  if ($_SERVER['REQUEST_METHOD'] === 'POST' && !csrf_valid($_POST['csrf'] ?? null)) csrf_fail();
}

// Exige token válido en acciones lanzadas por GET (borrados)
function csrf_verify_get(): void {
  if (!csrf_valid($_GET['csrf'] ?? null)) csrf_fail();
}

}

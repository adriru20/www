<?php
// Autenticación centralizada: todas las páginas usan estas funciones.

function is_logged_in(): bool {
  return !empty($_SESSION['user_id']);
}

// Acepta solo rutas internas ("/apps/wiki/"), nunca URLs externas (evita open redirect)
function safe_next(?string $next): string {
  $next = trim((string) $next);
  if ($next === '' || $next[0] !== '/' || str_starts_with($next, '//') || str_contains($next, '\\')
      || preg_match('/[\x00-\x1f]/', $next) || str_starts_with($next, '/src/login')) {
    return '/';
  }
  return $next;
}

function login_url(?string $next = null): string {
  $next = safe_next($next);
  return '/src/login/' . ($next !== '/' ? '?next=' . urlencode($next) : '');
}

// Exige sesión; si no la hay, lleva al login y vuelve a la página pedida al entrar
function require_login(): void {
  if (!is_logged_in()) {
    header('Location: ' . login_url($_SERVER['REQUEST_URI'] ?? '/'));
    exit();
  }
}

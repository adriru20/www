<?php
// Inicio de sesión único con cookies endurecidas. Usar en lugar de session_start().
// - La sesión dura 14 días y se renueva con cada visita (no hay que volver a entrar cada rato).
// - Los ficheros de sesión van a backend/sessions/ (propios de esta web): en hostings
//   compartidos el directorio por defecto lo limpian otras webs y cierra sesiones antes de tiempo.
if (session_status() === PHP_SESSION_NONE) {
  $lifetime = 14 * 24 * 60 * 60;
  $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');

  $dir = dirname(__DIR__) . '/sessions';
  if (!is_dir($dir)) @mkdir($dir, 0700, true);
  if (is_dir($dir) && is_writable($dir)) session_save_path($dir);

  ini_set('session.gc_maxlifetime', (string) $lifetime);
  ini_set('session.use_strict_mode', 1);
  ini_set('session.use_only_cookies', 1);
  $cookie = [
    'lifetime' => $lifetime,
    'path'     => '/',
    'secure'   => $https,
    'httponly' => true,
    'samesite' => 'Lax',
  ];
  session_set_cookie_params($cookie);
  session_start();

  // Renueva la caducidad de la cookie en cada visita (sesión "deslizante")
  if (!headers_sent()) {
    setcookie(session_name(), session_id(), [
      'expires'  => time() + $lifetime,
      'path'     => '/',
      'secure'   => $https,
      'httponly' => true,
      'samesite' => 'Lax',
    ]);
  }
}

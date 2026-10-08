<?php
// Inicio de sesión único con cookies endurecidas. Usar en lugar de session_start().
if (session_status() === PHP_SESSION_NONE) {
  $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
  ini_set('session.use_strict_mode', 1);
  ini_set('session.use_only_cookies', 1);
  session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/',
    'secure'   => $https,
    'httponly' => true,
    'samesite' => 'Lax',
  ]);
  session_start();
}

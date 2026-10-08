<?php
function save_ip(): string {
  // El log vive en backend/logs/ (protegido por .htaccess), no en el directorio actual
  $dir = __DIR__ . '/../logs';
  if (!is_dir($dir)) @mkdir($dir, 0750, true);
  $file = "$dir/accesos.log";
  $ip = $_SERVER["REMOTE_ADDR"] ?? '-';
  $fecha = date("Y-m-d H:i:s");
  $conproxy = $_SERVER["HTTP_X_FORWARDED_FOR"] ?? '-';
  // Quita saltos de línea para que no se puedan falsificar entradas del log
  $conproxy = preg_replace('/[\r\n]+/', ' ', $conproxy);
  $log = "[$fecha] $conproxy-$ip\r\n";
  @file_put_contents($file, $log, FILE_APPEND | LOCK_EX);
  return $log;
}

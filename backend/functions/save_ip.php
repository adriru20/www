<?php
function save_ip(): string {
  $file = "accesos.log";
  $ip = $_SERVER["REMOTE_ADDR"];
  $fecha = date("Y-m-d H:i:s");
  $conproxy = $_SERVER["HTTP_X_FORWARDED_FOR"];
  $log = "[$fecha] $conproxy-$ip\x0D\x0A";
  $fp = fopen($file, "a");
  fwrite($fp, $log);
  fclose($fp);
  return $log;
}
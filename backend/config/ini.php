<?php
// Detectar el entorno
unset($url); unset($entorno);
if ($_SERVER["SERVER_NAME"] == 'adriru.es') {
  $url = "www.{$_SERVER["SERVER_NAME"]}";
  $entorno = substr(string: $url, offset: 0, length: -10);
} else {
  $url = $_SERVER["SERVER_NAME"];
  $entorno = substr(string: $url, offset: 0, length: -10);
}
// Detectar la ruta relativa
unset($src);
if (getcwd() === "/homepages/28/d1007113302/htdocs/$entorno") {
  $src = "./";
} else {
  $src = "../../";
}
include "{$src}backend/config/environment.php";
include "{$src}frontend/head.php";
// Incluye todos los ficheros de funciones
unset($directory);
$directory = "{$src}backend/functions/";
$directorio=opendir("$directory");
while ($archivo = readdir($directorio)) {
  if (($archivo!=".")and($archivo!="..")) {
    include "{$directory}$archivo";
  } else {
    $archivo=null;
  }
}

<?php
// Configuración y constantes de la app de inventario.
const INV_PAGE_SIZE   = 24;
const INV_TIPOS       = ['Objetos', 'Juegos', 'Películas'];
const INV_FORMATOS    = ['Físico', 'Digital'];
const INV_IMG_EXT     = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
const INV_MAX_UPLOAD  = 15 * 1024 * 1024;   // 15 MB por foto
const INV_THUMB_W     = 360;                // ancho de las miniaturas de las tarjetas
const INV_BACKUP_DAYS = 7;                  // copia automática si la última tiene más de N días
const INV_BACKUP_KEEP = 8;                  // cuántas copias automáticas se conservan

define('INV_DIR', dirname(__DIR__));
define('INV_IMG_DIR', INV_DIR . '/img/');
define('INV_BACKUP_DIR', INV_DIR . '/backup/');

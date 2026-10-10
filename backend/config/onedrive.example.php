<?php
// Copia este fichero como backend/config/onedrive.local.php (NO se sube a Git) y rellena los datos.
// Guía paso a paso: pantalla Administración → OneDrive de la web.
return [
    'client_id'     => '',      // «Id. de aplicación (cliente)» de tu app en Azure
    'client_secret' => '',      // el VALOR del secreto de cliente creado en Azure (no el «Id. del secreto»)
    'redirect_uri'  => 'https://www.adriru.es/src/onedrive/callback.php',   // debe coincidir EXACTAMENTE con el de Azure
    'folder'        => 'Obsidian/MiVault',   // ruta de la carpeta del vault dentro de tu OneDrive
    // 'refresh_minutes' => 10,              // cada cuántos minutos se mira si hay cambios al abrir la wiki
];

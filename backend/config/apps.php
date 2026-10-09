<?php
// Registro de secciones y permisos de la web. Es la ÚNICA lista: el menú, el inicio y el panel
// de administración se construyen a partir de aquí. Para añadir una sección nueva, añádela aquí.
//
// Permisos que se guardan en la tabla user_permissions (columna perm):
//   - la clave de la sección ('wiki', 'parking', 'giftlist', 'inventario')  -> puede entrar
//   - 'sección.sub' (p. ej. 'inventario.edit')                              -> puede hacer esa acción
// Los administradores lo tienen todo sin necesidad de filas.

const ROLES = [
  'admin'     => 'Administrador',
  'user'      => 'Usuario',
  'visitante' => 'Visitante',
];

function app_registry(): array {
  return [
    'wiki' => [
      'label' => 'Wiki', 'icon' => 'fa-book-open', 'href' => '/apps/wiki/',
      'desc' => 'Mis notas y apuntes de Obsidian.', 'subs' => [],
    ],
    'parking' => [
      'label' => 'Parking', 'icon' => 'fa-car', 'href' => '/apps/parking/',
      'desc' => 'Guarda dónde has aparcado el coche.', 'subs' => [],
    ],
    'giftlist' => [
      'label' => 'Gift list', 'icon' => 'fa-gift', 'href' => '/apps/giftlist/',
      'desc' => 'Listas de regalos de la familia.',
      'subs' => [
        'manage' => 'Gestionar las listas de los demás (añadir/editar/borrar)',
      ],
    ],
    'inventario' => [
      'label' => 'Inventario', 'icon' => 'fa-boxes-stacked', 'href' => '/apps/inventario/',
      'desc' => 'Objetos, juegos y dónde está cada cosa.',
      'subs' => [
        'add'    => 'Añadir objetos, localizaciones e imágenes',
        'edit'   => 'Editar y renombrar',
        'delete' => 'Borrar',
        'backup' => 'Backups, exportar e importar',
      ],
    ],
  ];
}

// Todas las claves de permiso válidas: ['wiki', ..., 'inventario', 'inventario.add', ...]
function all_perm_keys(): array {
  $keys = [];
  foreach (app_registry() as $app => $def) {
    $keys[] = $app;
    foreach ($def['subs'] as $sub => $_) $keys[] = "$app.$sub";
  }
  return $keys;
}

// Permisos que se marcan al elegir un rol en el panel (el administrador puede cambiarlos)
function role_preset_subs(string $role): array {
  return match ($role) {
    'visitante' => ['inventario.add'],
    'user'      => ['inventario.add', 'inventario.edit', 'inventario.delete', 'inventario.backup'],
    default     => [],
  };
}

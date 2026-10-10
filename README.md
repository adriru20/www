# www.adriru.es

Sitio personal en PHP (sin framework) con login y varias apps.

## Estructura

| Carpeta | Contenido |
|---|---|
| `index.php`, `img/` | Portada y logo/iconos |
| `styles/` | `theme.css` (paleta y estilos comunes), `wiki.css`, `inventario.css` |
| `js/vendor/` | Librerías locales (marked, DOMPurify) |
| `frontend/` | Plantilla común: `head.php`, `menu.php` (navbar), `footer.php` |
| `backend/config/` | `bootstrap.php` (sesión + rutas + login), `auth.php`, `session.php`, `ini.php` (cabecera HTML), `db.php` |
| `backend/functions/` | Funciones cargadas automáticamente (`csrf.php`, `save_ip.php`) |
| `backend/sessions/`, `backend/logs/` | Ficheros de sesión y logs (no accesibles por web) |
| `backend/database/` | Esquema SQL y `migrations/` |
| `backend/legacy/` | Código antiguo sin uso (no se carga) |
| `apps/` | `wiki`, `parking` (`api.php` + `parking.js`, tabla `parking_spots`), `giftlist` (`lib.php` + `giftlist.js`), `inventario` |
| `apps/inventario/` | `index.php` (controlador), `lib/` (config, datos, acciones, imágenes, backups), `views/` (HTML por pestaña y modales), `thumb.php` (miniaturas), `inventario.js`, `sw.js` |
| `src/` | `login` y páginas públicas (cartas, documentos) |

### Cómo crear una página nueva

```php
<?php
require_once __DIR__ . '/../../backend/config/bootstrap.php';
require_login();                       // lleva al login y vuelve aquí al entrar
$page_title = 'Mi página';             // opcional: $page_styles, $page_scripts
?>
<!DOCTYPE html>
<html lang="es" data-bs-theme="dark">
<?php include __DIR__ . '/../../backend/config/ini.php'; ?>
<body>
  <?php include __DIR__ . '/../../frontend/menu.php'; ?>
  <main class="page-container"> ... </main>
  <?php include __DIR__ . '/../../frontend/footer.php'; ?>
</body>
</html>
```

Colores: se cambian en las variables de `:root` de `styles/theme.css`.

## Configuración local / servidor

La conexión a la base de datos NO está en Git. Copia `backend/config/db.local.example.php`
a `backend/config/db.local.php` y rellénalo (o define `DB_HOST`, `DB_USER`, `DB_PASS`, `DB_NAME`).
Para VS Code SFTP copia `.vscode/sftp.example.json` a `.vscode/sftp.json`.

## Usuarios, roles y permisos

- Roles: **Administrador** (todo + gestiona usuarios), **Usuario** y **Visitante**. Se gestionan en `/src/admin/` (solo admin).
- Cada persona tiene una checklist de secciones (Wiki, Parking, Gift list, Inventario) y, dentro del inventario,
  subpermisos: añadir, editar, borrar y backups; en Gift list, «gestionar las listas de los demás» (añadir, editar y borrar en listas ajenas). Por defecto un usuario nuevo no ve ninguna sección. Un usuario puede ocultarse (Usuarios → Ocultar, migración 004): sigue entrando pero no sale en las listas de la web.
- El registro de secciones y permisos está en `backend/config/apps.php` (menú, inicio y panel se generan de ahí).
- En el código: `require_app('wiki')` en la página, `require_app_api('wiki')` en las APIs y `has_perm('inventario.edit')` para acciones.
- Tablas: `login_user` (rol, activo, último acceso) y `user_permissions` (qué puede hacer cada usuario).
- Antes de ejecutar `backend/database/migraciones/002_roles_y_mejoras.sql` todo funciona como antes (modo compatible).
- Gift list: en la lista de otra persona se puede marcar «lo he comprado yo» (quién y cuándo) y desmarcarlo. El dueño de la lista no ve nada de esto. Requiere `003_gift_comprado.sql`; sin él los botones no aparecen.

Cada usuario puede cambiar su contraseña en **Mi cuenta** (`/src/cuenta/`, menú superior): pide la actual, limita los intentos fallidos y
usa el ojito (`js/password-eye.js`, atributo `data-eye`) para ver lo que se escribe. El login también lo lleva.
Al cambiar una contraseña (la persona o el administrador) se cierran las sesiones abiertas en otros dispositivos: cada sesión guarda una huella de la
contraseña (`$_SESSION['pv']`, ver `pass_stamp()` en `backend/config/auth.php`) y deja de valer si no coincide con la actual.

## Despliegue

Un push a `main` ejecuta `.github/workflows/deploy.yml`: comprueba la sintaxis PHP y sube por SFTP.
Secrets necesarios: `FTP_SERVER`, `FTP_USERNAME`, `FTP_PASSWORD` y, recomendado,
`SFTP_KNOWN_HOST` (salida de `ssh-keyscan -t ed25519 <servidor>`, verificada una vez a mano).
No se borra nada en el servidor: `db.local.php`, imágenes subidas, backups y logs viven solo allí.

## Seguridad (resumen)

- Sesión con cookies `httponly`/`samesite`; login con límite de intentos.
- Tokens CSRF en formularios y enlaces de borrado (`backend/functions/csrf.php`).
- Subidas de imagen validadas y sin ejecución de scripts en `apps/inventario/img/`.
- Wiki y APIs solo con sesión; registro de usuarios desactivado (`src/login/signup.php`).

## Wiki desde OneDrive
La wiki lee las notas de `apps/wiki/vault/`. Si se conecta OneDrive (Administración → OneDrive), esa carpeta se mantiene sincronizada con la del vault en
OneDrive (solo notas `.md`, solo lectura). Código: `backend/lib/onedrive.php`; pantalla de administración: `src/onedrive/`; API: `apps/wiki/api/sync.php`.
- Configuración (no va en Git): `backend/config/onedrive.local.php`, a partir de `onedrive.example.php` (id y secreto de la app de Azure, ruta de la carpeta).
- Token, estado y bloqueo: `backend/onedrive/` (protegida con `.htaccess`, ignorada por Git).
- Se actualiza sola al abrir la wiki si han pasado más de 10 minutos (o con el botón ↻). Usa la API «delta» y, si Microsoft no la admite para la carpeta, cambia sola al listado completo.
- El despliegue **no toca** `apps/wiki/vault/` (excluido en `deploy.yml`): la fuente de verdad es OneDrive. El vault antiguo sigue en el repositorio como copia.

## Pendientes (ideas aparcadas)
- Papelera de recuperación del inventario; quitar la columna antigua `inv_objetos.localizacion`; revisar la localización «•».
- Cuando la sincronización con OneDrive lleve tiempo funcionando: sacar `apps/wiki/vault/` del repositorio (`git rm -r --cached`).

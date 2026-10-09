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
| `apps/` | `wiki` (+`wiki.js`), `parking` (+`parking.js`), `giftlist`, `inventario` |
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

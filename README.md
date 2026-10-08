# www.adriru.es

Sitio personal en PHP (sin framework) con login y varias apps.

## Estructura

| Carpeta | Contenido |
|---|---|
| `index.php`, `js/`, `styles/`, `img/` | Portada y recursos comunes |
| `frontend/` | Cabecera (`head.php`), menú y pie reutilizables |
| `backend/config/` | `ini.php` (arranque de páginas), `session.php`, `db.php` (conexión) |
| `backend/functions/` | Funciones cargadas automáticamente por `ini.php` (`csrf.php`, `save_ip.php`) |
| `backend/database/` | Esquema SQL y `migrations/` |
| `backend/legacy/` | Código antiguo sin uso (no se carga) |
| `apps/` | `wiki` (vault de Obsidian), `parking`, `giftlist`, `inventario` |
| `src/` | `login`, cartas y documentos públicos |

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

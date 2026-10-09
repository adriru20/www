<?php
// Inventario: controlador. La lógica vive en lib/ (datos, acciones, imágenes) y el HTML en views/.
require_once __DIR__ . '/../../backend/config/bootstrap.php';
require_app('inventario');
require_once __DIR__ . '/../../backend/config/db.php';

// Producción: los errores se registran, no se muestran
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

foreach (['config', 'helpers', 'repository', 'images', 'backup', 'actions'] as $lib) {
  require_once __DIR__ . "/lib/$lib.php";
}
global $conn;

// Sustituto de portada cuando una foto no existe
define('INV_FALLBACK_SVG', "data:image/svg+xml;charset=UTF-8,%3Csvg xmlns='http://www.w3.org/2000/svg' width='540' height='720' viewBox='0 0 540 720'%3E%3Crect width='540' height='720' fill='%232a2e34'/%3E%3Ctext x='50%25' y='50%25' fill='%2395a5a6' font-size='40' text-anchor='middle' dominant-baseline='middle'%3ESin foto%3C/text%3E%3C/svg%3E");

if (!inv_schema_ready()) inv_migration_page();

// Qué puede hacer el usuario actual (subpermisos del inventario)
$perm = ['add' => has_perm('inventario.add'), 'edit' => has_perm('inventario.edit'),
         'delete' => has_perm('inventario.delete'), 'backup' => has_perm('inventario.backup')];

// Contenido de una localización (JSON para el panel «qué hay aquí»)
if (($_GET['ajax'] ?? '') === 'loc_objs') {
  header('Content-Type: application/json; charset=utf-8');
  header('Cache-Control: no-store');
  $loc = inv_loc_get((int) ($_GET['id'] ?? 0));
  if (!$loc) { http_response_code(404); echo json_encode(['error' => 'No existe esa localización.']); exit; }
  echo json_encode(['loc' => $loc, 'objs' => inv_objects_in_location_rows((int) $loc['id'])], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
  exit;
}

inv_handle_actions();   // POST: guarda/borra y redirige

// ---------------------------------------------------------------- Datos de la pestaña
$tab  = in_array($_GET['tab'] ?? '', ['resumen', 'objetos', 'localizaciones', 'imagenes'], true) ? $_GET['tab'] : 'resumen';
$page = max(1, (int) ($_GET['p'] ?? 1));

// Copia de seguridad semanal automática (si toca); nunca debe romper la página
try { inv_auto_backup(); } catch (Throwable $e) { error_log('auto backup: ' . $e->getMessage()); }

$flash   = inv_take_flash();
$opts    = inv_options();
$images  = inv_list_images();
$locMap  = array_column(inv_rows(inv_query('SELECT nombre, categoria FROM inv_localizaciones')), 'categoria', 'nombre');
$f = $list = $locUsage = $usage = $stats = $img_stats = null;
$q_img = $ver_img = '';
$data = ['obj' => [], 'loc' => [], 'perm' => $perm];   // datos para rellenar los formularios de edición

switch ($tab) {
  case 'objetos':
    $f = inv_obj_filters();
    $list = inv_obj_list($f, $page);
    foreach ($list['rows'] as $o) $data['obj'][$o['id']] = $o;
    $count = $list['total'];
    break;
  case 'localizaciones':
    $f = inv_loc_filters();
    $list = inv_loc_list($f, $page);
    $locUsage = inv_location_usage();
    foreach ($list['rows'] as $l) $data['loc'][$l['id']] = $l + ['usos' => $locUsage[$l['nombre']] ?? 0];
    $count = $list['total'];
    break;
  case 'imagenes':
    $q_img = trim((string) ($_GET['q_img'] ?? ''));
    $usage = inv_image_usage();                      // null = no se pudo comprobar
    $unused = inv_unused_images($images, $usage);
    $img_stats = ['total' => count($images), 'unused' => $unused === null ? null : count($unused), 'unused_size' => $unused === null ? 0 : inv_images_size($unused)];
    $ver_img = ($unused !== null && in_array($_GET['ver_img'] ?? '', ['sin_usar', 'usadas'], true)) ? $_GET['ver_img'] : '';
    $pool = $ver_img === 'sin_usar' ? $unused : ($ver_img === 'usadas' ? array_values(array_diff($images, $unused)) : $images);
    $names = $q_img === '' ? $pool : array_values(array_filter($pool, fn($n) => stripos($n, $q_img) !== false));
    $pages = max(1, (int) ceil(count($names) / INV_PAGE_SIZE));
    $page = min($page, $pages);
    $list = ['rows' => array_slice($names, ($page - 1) * INV_PAGE_SIZE, INV_PAGE_SIZE), 'total' => count($names), 'pages' => $pages, 'page' => $page];
    $usage ??= [];
    $count = $list['total'];
    break;
  case 'resumen':
    $stats = inv_stats();
    $count = $stats['objetos'];
    break;
}

$page_title   = 'Inventario';
$page_styles  = ['/styles/inventario.css'];
$page_scripts = ['/apps/inventario/inventario.js'];
?>
<!DOCTYPE html>
<html lang="es" data-bs-theme="dark">
<?php include __DIR__ . '/../../backend/config/ini.php'; ?>
<body>
  <script>const fallbackSvg = <?= json_encode(INV_FALLBACK_SVG) ?>;</script>
  <?php include __DIR__ . '/../../frontend/menu.php'; ?>

  <main class="page-container page-wide">
    <?php include __DIR__ . '/views/header.php'; ?>
    <?php include __DIR__ . "/views/$tab.php"; ?>
  </main>

  <?php
  include __DIR__ . '/views/datalists.php';
  include __DIR__ . '/views/modal_objeto.php';
  include __DIR__ . '/views/modal_localizacion.php';
  include __DIR__ . '/views/modal_loc_objetos.php';
  include __DIR__ . '/views/modals_comunes.php';
  ?>
  <script type="application/json" id="inv-data"><?= json_encode($data, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?></script>
  <?php include __DIR__ . '/../../frontend/footer.php'; ?>
</body>
</html>

<?php
// Variables opcionales que define la página antes de incluir ini.php:
//   $page_title  (string)  título de la pestaña
//   $page_styles (array)   CSS extra, p. ej. ['/styles/wiki.css']
$page_title  = $page_title  ?? 'Adriru';
$page_styles = $page_styles ?? [];
// Cada app se puede instalar por separado: el manifiesto depende de la sección (si no, el de la web en general)
$pwa_path = (string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$pwa = 'web';
foreach (['inventario', 'wiki', 'parking', 'giftlist'] as $pwa_app) {
  if (strpos($pwa_path, '/apps/' . $pwa_app . '/') === 0) { $pwa = $pwa_app; break; }
}
$pwa_names = ['web' => 'Adriru', 'inventario' => 'Inventario', 'wiki' => 'Wiki', 'parking' => 'Parking', 'giftlist' => 'Gift list'];
?>
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover"/>
  <title><?= htmlspecialchars($page_title) ?> · Adriru</title>
  <meta name="theme-color" content="#1a1c20"/>

  <link rel="manifest" href="/backend/PWA/manifest-<?= $pwa ?>.json"/>
  <meta name="apple-mobile-web-app-capable" content="yes"/>
  <meta name="mobile-web-app-capable" content="yes"/>
  <meta name="apple-mobile-web-app-title" content="<?= $pwa_names[$pwa] ?>"/>
  <link rel="apple-touch-icon" href="/img/Astronauta-Icono.jpg"/>
  <link rel="shortcut icon" href="/img/favicon.ico" type="image/x-icon"/>

  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous"/>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" integrity="sha512-iecdLmaskl7CVkqkXNQ/ZH/XLlvWZOJyj7Yy7tcenmpD1ypASozpmT/E0iPtmFIB46ZmdtAc9eNBvH0H/ZpiBw==" crossorigin="anonymous" referrerpolicy="no-referrer"/>
  <link rel="stylesheet" href="/styles/theme.css"/>
<?php foreach ($page_styles as $css): ?>
  <link rel="stylesheet" href="<?= htmlspecialchars($css) ?>"/>
<?php endforeach; ?>
</head>

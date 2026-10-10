<?php
// Administración → OneDrive: conectar la cuenta, ver el estado de la sincronización de la wiki y lanzarla a mano.
require_once __DIR__ . '/../../backend/config/bootstrap.php';
require_admin();
require_once __DIR__ . '/../../backend/lib/onedrive.php';

$cfg = od_config();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  csrf_verify_post();
  $action = (string) ($_POST['action'] ?? '');
  if (!$cfg) { flash_add('Falta la configuración de OneDrive (backend/config/onedrive.local.php).', 'warning'); header('Location: /src/onedrive/'); exit(); }
  switch ($action) {
    case 'connect':
      header('Location: ' . od_authorize_url());      // guarda state y verifier en la sesión y manda a Microsoft
      exit();
    case 'disconnect':
      od_disconnect();
      flash_add('OneDrive desconectado. Las notas que ya están en el servidor se conservan.', 'info');
      break;
    case 'sync':
    case 'reset':
      session_write_close();                     // no bloquear otras pestañas mientras se sincroniza
      $r = od_sync(20, $action === 'reset');
      session_start();
      flash_add($r['message'] . ($r['pending'] > 0 ? ' Quedan notas por descargar: pulsa de nuevo «Sincronizar ahora».' : '') . ($r['changed'] ? " ({$r['changed']} cambio(s))" : ''), $r['state'] === 'synced' ? 'success' : 'warning');
      break;
    case 'diagnose':
      $_SESSION['od_diag'] = od_diagnose();
      break;
    case 'prune':
      [$n, $err] = od_prune_untracked();
      flash_add($err !== '' ? $err : ($n . ' nota(s) antigua(s) borrada(s) del servidor.'), $err !== '' ? 'warning' : 'success');
      break;
    default:
      flash_add('Acción no reconocida.', 'danger');
  }
  header('Location: /src/onedrive/');
  exit();
}

$st = od_status();
$diag = $_SESSION['od_diag'] ?? null;
unset($_SESSION['od_diag']);
$ago = fn(int $t) => $t ? (time() - $t < 90 ? 'hace un momento' : 'hace ' . (time() - $t < 5400 ? round((time() - $t) / 60) . ' min' : (time() - $t < 172800 ? round((time() - $t) / 3600) . ' h' : round((time() - $t) / 86400) . ' días'))) : 'nunca';
$page_title = 'OneDrive';
?>
<!DOCTYPE html>
<html lang="es" data-bs-theme="dark">
<?php include __DIR__ . '/../../backend/config/ini.php'; ?>
<body>
  <?php include __DIR__ . '/../../frontend/menu.php'; ?>
  <main class="page-container">
    <?php flash_render(); ?>
    <div class="page-header"><h1 class="h3"><i class="fa-solid fa-cloud"></i> OneDrive · wiki</h1></div>

    <?php if (!$st['configured']): ?>
      <div class="card mb-3"><div class="card-body">
        <h2 class="h5">Falta configurar la conexión</h2>
        <p class="text-muted">La wiki puede leer tus notas directamente de tu OneDrive personal (solo lectura). Hay que hacer esto una sola vez:</p>
        <ol class="mb-3">
          <li>Entra en <a href="https://entra.microsoft.com" target="_blank" rel="noopener">entra.microsoft.com</a> con tu cuenta personal de Microsoft → <strong>Aplicaciones → Registros de aplicaciones → Nuevo registro</strong>.</li>
          <li>Nombre: <code>adriru wiki</code>. Tipos de cuenta: <strong>Solo cuentas personales de Microsoft</strong>. URI de redirección: tipo <strong>Web</strong> y valor <code><?= e('https://' . ($_SERVER['HTTP_HOST'] ?? 'www.adriru.es') . '/src/onedrive/callback.php') ?></code>.</li>
          <li>Copia el <strong>Id. de aplicación (cliente)</strong>.</li>
          <li><strong>Certificados y secretos → Nuevo secreto de cliente</strong>, caducidad 24 meses. Copia el <strong>Valor</strong> (solo se ve una vez).</li>
          <li>Crea en el servidor el fichero <code>backend/config/onedrive.local.php</code> a partir de <code>onedrive.example.php</code> con esos dos datos y la ruta de tu carpeta del vault.</li>
          <li>Recarga esta página y pulsa <strong>Conectar con OneDrive</strong>.</li>
        </ol>
        <p class="small text-muted mb-0">Permisos que se piden a Microsoft: <em>Files.Read</em> (leer tus archivos, sin poder modificarlos) y <em>offline_access</em> (para no tener que volver a iniciar sesión).</p>
      </div></div>
    <?php else: ?>
      <div class="card mb-3"><div class="card-body">
        <div class="d-flex flex-wrap gap-4 mb-3">
          <div><div class="text-muted small">Conexión</div>
            <?php if ($st['connected']): ?><span class="badge bg-success">Conectado</span><?php else: ?><span class="badge bg-warning text-dark">Sin conectar</span><?php endif; ?></div>
          <div><div class="text-muted small">Carpeta en OneDrive</div><code><?= e($st['folder']) ?></code></div>
          <div><div class="text-muted small">Notas en el servidor</div><strong><?= (int) $st['notes'] ?></strong></div>
          <div><div class="text-muted small">Última sincronización completa</div><?= e($ago($st['last_sync'])) ?></div>
          <div><div class="text-muted small">Último cambio de notas</div><?= e($ago($st['last_change'])) ?></div>
          <?php if ($st['pending'] > 0): ?><div><div class="text-muted small">Pendientes</div><span class="badge bg-info text-dark"><?= (int) $st['pending'] ?></span></div><?php endif; ?>
        </div>
        <?php if ($st['last_error'] !== ''): ?><div class="alert alert-warning small"><strong>Último aviso:</strong> <?= e($st['last_error']) ?></div><?php endif; ?>

        <div class="d-flex flex-wrap gap-2">
          <?php if (!$st['connected']): ?>
            <form method="POST"><?= csrf_input() ?><input type="hidden" name="action" value="connect"><button class="btn btn-accent" type="submit"><i class="fa-brands fa-microsoft"></i> Conectar con OneDrive</button></form>
          <?php else: ?>
            <form method="POST"><?= csrf_input() ?><input type="hidden" name="action" value="sync"><button class="btn btn-primary" type="submit"><i class="fa-solid fa-rotate"></i> Sincronizar ahora</button></form>
            <form method="POST" onsubmit="return confirm('Se vuelve a comprobar toda la carpeta de OneDrive desde cero. ¿Seguir?');"><?= csrf_input() ?><input type="hidden" name="action" value="reset"><button class="btn btn-outline-secondary" type="submit">Sincronización completa desde cero</button></form>
            <form method="POST" onsubmit="return confirm('Se borran del servidor las notas .md que no estén en OneDrive (restos del vault antiguo). ¿Seguir?');"><?= csrf_input() ?><input type="hidden" name="action" value="prune"><button class="btn btn-outline-secondary" type="submit">Limpiar notas antiguas del servidor</button></form>
            <form method="POST" onsubmit="return confirm('¿Desconectar OneDrive?');"><?= csrf_input() ?><input type="hidden" name="action" value="disconnect"><button class="btn btn-outline-danger" type="submit">Desconectar</button></form>
          <?php endif; ?>
        </div>
        <p class="small text-muted mt-3 mb-0">La wiki se actualiza sola al abrirla si han pasado más de <?= (int) $st['refresh_minutes'] ?> minutos desde la última comprobación. Solo se descargan las notas <code>.md</code>.</p>
      </div></div>
    <?php endif; ?>
    <?php if ($cfg): ?>
      <div class="card mb-3"><div class="card-body">
        <h2 class="h6">Diagnóstico</h2>
        <p class="small text-muted">Comprueba, sin usar ningún secreto, que el servidor llega a Microsoft y que la configuración tiene buena pinta.</p>
        <form method="POST" class="mb-2"><?= csrf_input() ?><input type="hidden" name="action" value="diagnose"><button class="btn btn-sm btn-outline-secondary" type="submit"><i class="fa-solid fa-stethoscope"></i> Probar conexión con Microsoft</button></form>
        <?php if ($diag): ?>
          <ul class="list-unstyled small mb-0">
            <?php foreach ($diag as [$estado, $texto]): ?>
              <li class="mb-1"><span class="badge <?= $estado === 'ok' ? 'bg-success' : ($estado === 'aviso' ? 'bg-warning text-dark' : 'bg-danger') ?>"><?= $estado === 'ok' ? 'OK' : ($estado === 'aviso' ? 'Aviso' : 'Error') ?></span> <?= e($texto) ?></li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>
      </div></div>
    <?php endif; ?>
  </main>
  <?php include __DIR__ . '/../../frontend/footer.php'; ?>
</body>
</html>

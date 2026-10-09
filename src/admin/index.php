<?php
require_once __DIR__ . '/../../backend/config/bootstrap.php';
require_admin();
require_once __DIR__ . '/../../backend/config/db.php';
require_once __DIR__ . '/lib.php';
global $conn;

$me    = (string) $_SESSION['user_id'];
$ready = acl_state()['ready'];   // ¿se ha ejecutado ya el SQL de roles?
if ($ready) admin_handle_actions($me);
$users = $ready ? admin_users() : [];
$registry = app_registry();

// Pestañas: usuarios activos / inactivos
$can_hide = $ready && admin_hidden_ready();   // ¿está la columna «oculto»? (migración 004)
$ver = in_array($_GET['ver'] ?? '', ['inactivos', 'ocultos'], true) ? $_GET['ver'] : 'activos';
if ($ver === 'ocultos' && !$can_hide) $ver = 'activos';
$isHidden = fn($u) => (int) $u['oculto'] === 1;
$isActive = fn($u) => (int) $u['active'] === 1;
$n_active   = count(array_filter($users, fn($u) => !$isHidden($u) && $isActive($u)));
$n_inactive = count(array_filter($users, fn($u) => !$isHidden($u) && !$isActive($u)));
$n_hidden   = count(array_filter($users, $isHidden));
$shown = array_values(array_filter($users, fn($u) => match ($ver) {
  'ocultos'   => $isHidden($u),
  'inactivos' => !$isHidden($u) && !$isActive($u),
  default     => !$isHidden($u) && $isActive($u),
}));

$page_title   = 'Usuarios';
$page_scripts = ['/src/admin/usuarios.js'];
$data = [];
foreach ($users as $u) $data[$u['id']] = ['user' => $u['user'], 'role' => $u['permission'], 'perms' => $u['perms'], 'active' => (int) $u['active']];
$presets = ['admin' => [], 'user' => role_preset_subs('user'), 'visitante' => role_preset_subs('visitante')];
$roleBadge = ['admin' => 'bg-danger', 'user' => 'bg-primary', 'visitante' => 'bg-secondary'];
$fmt = fn($d) => $d ? date('d/m/Y H:i', strtotime($d)) : '—';
?>
<!DOCTYPE html>
<html lang="es" data-bs-theme="dark">
<?php include __DIR__ . '/../../backend/config/ini.php'; ?>
<body>
  <?php include __DIR__ . '/../../frontend/menu.php'; ?>
  <main class="page-container">
    <?php flash_render(); ?>
    <div class="page-header">
      <h1 class="h3"><i class="fa-solid fa-users-gear"></i> Usuarios y permisos</h1>
      <?php if ($ready): ?><button class="btn btn-accent" type="button" data-user-new><i class="fa-solid fa-user-plus"></i> Nuevo usuario</button><?php endif; ?>
    </div>

    <?php if (!$ready): ?>
      <div class="alert alert-warning">
        <strong>Falta ejecutar el SQL de roles en la base de datos.</strong>
        Hasta entonces todos los usuarios con sesión pueden entrar en todo. Ejecuta el fichero
        <code>backend/database/migraciones/002_roles_y_mejoras.sql</code> en phpMyAdmin y recarga esta página.
      </div>
    <?php else: ?>
      <ul class="nav nav-tabs flex-nowrap mb-3" aria-label="Usuarios">
        <li class="nav-item"><a class="nav-link px-2 px-sm-3 <?= $ver === 'activos' ? 'active' : '' ?>" href="index.php"><i class="fa-solid fa-user-check d-none d-sm-inline"></i> Activos <span class="count-pill"><?= $n_active ?></span></a></li>
        <li class="nav-item"><a class="nav-link px-2 px-sm-3 <?= $ver === 'inactivos' ? 'active' : '' ?>" href="index.php?ver=inactivos"><i class="fa-solid fa-user-slash d-none d-sm-inline"></i> Inactivos <span class="count-pill"><?= $n_inactive ?></span></a></li>
        <?php if ($can_hide): ?>
          <li class="nav-item"><a class="nav-link px-2 px-sm-3 <?= $ver === 'ocultos' ? 'active' : '' ?>" href="index.php?ver=ocultos"><i class="fa-solid fa-eye-slash d-none d-sm-inline"></i> Ocultos <span class="count-pill"><?= $n_hidden ?></span></a></li>
        <?php endif; ?>
      </ul>
      <?php if (!$can_hide): ?>
        <div class="alert alert-secondary small py-2">Para poder <strong>ocultar usuarios</strong> de las listas, ejecuta en phpMyAdmin el fichero <code>backend/database/migraciones/004_usuarios_ocultos.sql</code> y recarga esta página.</div>
      <?php endif; ?>
      <?php if (!$shown): ?>
        <div class="empty-state"><p><?= match ($ver) {
          'ocultos'   => 'No hay usuarios ocultos. Los que ocultes no saldrán en las listas de la web y los gestionarás desde aquí.',
          'inactivos' => 'No hay usuarios inactivos. Los que desactives aparecerán aquí y podrás volver a activarlos.',
          default     => 'No hay usuarios activos.',
        } ?></p></div>
      <?php endif; ?>
      <div class="row g-3">
        <?php foreach ($shown as $u): $isMe = $u['id'] === $me; $off = (int) $u['active'] !== 1; ?>
          <div class="col-12 col-lg-6">
            <article class="card user-card h-100 <?= $off ? 'is-off' : '' ?>">
              <div class="card-body">
                <div class="d-flex justify-content-between align-items-start gap-2">
                  <div>
                    <h2 class="h5 mb-1"><?= e($u['user']) ?> <?php if ($isMe): ?><small class="text-muted">(tú)</small><?php endif; ?></h2>
                    <span class="badge <?= $roleBadge[$u['permission']] ?? 'bg-secondary' ?>"><?= e(ROLES[$u['permission']] ?? $u['permission']) ?></span>
                    <?php if ($off): ?><span class="badge bg-dark border">Desactivado</span><?php endif; ?>
                    <?php if ($isHidden($u)): ?><span class="badge bg-dark border"><i class="fa-solid fa-eye-slash"></i> Oculto</span><?php endif; ?>
                  </div>
                  <small class="text-muted text-end">Último acceso<br><?= e($fmt($u['last_login'])) ?></small>
                </div>
                <div class="spot-chips">
                  <?php if ($u['permission'] === 'admin'): ?>
                    <span class="chip chip-ok">Acceso a todo</span>
                  <?php else:
                    $any = false;
                    foreach ($registry as $key => $app): if (!in_array($key, $u['perms'], true)) continue; $any = true; ?>
                      <span class="chip"><i class="fa-solid <?= $app['icon'] ?>"></i> <?= e($app['label']) ?></span>
                    <?php endforeach;
                    if (!$any): ?><span class="chip chip-warn">Sin acceso a ninguna sección</span><?php endif;
                  endif; ?>
                </div>
                <?php if ($u['permission'] !== 'admin'): foreach ($registry as $key => $app): if (!$app['subs'] || !in_array($key, $u['perms'], true)) continue; $subKeys = array_keys($app['subs']); $last = end($subKeys); ?>
                  <p class="small text-muted mt-2 mb-0"><?= e($app['label']) ?>:
                    <?php foreach ($app['subs'] as $sub => $label): ?>
                      <span class="<?= in_array("$key.$sub", $u['perms'], true) ? 'text-success' : 'text-decoration-line-through opacity-50' ?>"><?= e(explode(',', $label)[0]) ?></span><?= $sub !== $last ? ' · ' : '' ?>
                    <?php endforeach; ?>
                  </p>
                <?php endforeach; endif; ?>
                <div class="spot-actions">
                  <button class="btn btn-sm btn-primary" type="button" data-user-edit="<?= e($u['id']) ?>"><i class="fa-solid fa-sliders"></i> Rol y secciones</button>
                  <button class="btn btn-sm btn-outline-secondary" type="button" data-user-pass="<?= e($u['id']) ?>"><i class="fa-solid fa-key"></i> Contraseña</button>
                  <?php if (!$isMe): ?>
                    <form method="POST" class="d-contents m-0">
                      <?= csrf_input() ?><input type="hidden" name="action" value="set_active"><input type="hidden" name="id" value="<?= e($u['id']) ?>">
                      <input type="hidden" name="active" value="<?= $off ? 1 : 0 ?>">
                      <button class="btn btn-sm btn-outline-secondary" type="submit"><?= $off ? '<i class="fa-solid fa-user-check"></i> Activar' : '<i class="fa-solid fa-user-slash"></i> Desactivar' ?></button>
                    </form>
                    <?php if ($can_hide): ?>
                      <form method="POST" class="d-contents m-0">
                        <?= csrf_input() ?><input type="hidden" name="action" value="set_hidden"><input type="hidden" name="id" value="<?= e($u['id']) ?>">
                        <input type="hidden" name="hidden" value="<?= $isHidden($u) ? 0 : 1 ?>">
                        <button class="btn btn-sm btn-outline-secondary" type="submit" title="<?= $isHidden($u) ? 'Volver a mostrarlo en las listas' : 'No mostrarlo en las listas de la web' ?>"><?= $isHidden($u) ? '<i class="fa-solid fa-eye"></i> Mostrar' : '<i class="fa-solid fa-eye-slash"></i> Ocultar' ?></button>
                      </form>
                    <?php endif; ?>
                    <button class="btn btn-sm btn-outline-danger" type="button" data-user-delete="<?= e($u['id']) ?>" data-name="<?= e($u['user']) ?>"><i class="fa-solid fa-trash"></i></button>
                  <?php endif; ?>
                </div>
              </div>
            </article>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </main>

  <?php if ($ready):
    // Lista de permisos del formulario (la usan "nuevo" y "editar")
    $checklist = function (string $prefix) use ($registry) { ?>
      <div class="perm-list" data-perm-list>
        <?php foreach ($registry as $key => $app): ?>
          <div class="perm-app">
            <div class="form-check">
              <input class="form-check-input" type="checkbox" name="perms[]" value="<?= e($key) ?>" id="<?= $prefix . $key ?>" data-app="<?= e($key) ?>">
              <label class="form-check-label fw-semibold" for="<?= $prefix . $key ?>"><i class="fa-solid <?= $app['icon'] ?>"></i> <?= e($app['label']) ?></label>
            </div>
            <?php if ($app['subs']): ?>
              <div class="perm-subs">
                <?php foreach ($app['subs'] as $sub => $label): ?>
                  <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="perms[]" value="<?= e("$key.$sub") ?>" id="<?= $prefix . "$key-$sub" ?>" data-sub-of="<?= e($key) ?>">
                    <label class="form-check-label small" for="<?= $prefix . "$key-$sub" ?>"><?= e($label) ?></label>
                  </div>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>
    <?php }; ?>

    <!-- Nuevo usuario -->
    <div class="modal fade" id="modalNew" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-dialog-scrollable"><form class="modal-content" method="POST" autocomplete="off">
      <?= csrf_input() ?><input type="hidden" name="action" value="create_user">
      <div class="modal-header"><h5 class="modal-title fs-6">Nuevo usuario</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button></div>
      <div class="modal-body">
        <div class="mb-3"><label class="form-label small text-muted" for="n_user">Nombre de usuario</label>
          <input type="text" name="user" id="n_user" class="form-control" required minlength="3" maxlength="50" autocomplete="off"></div>
        <div class="mb-3"><label class="form-label small text-muted" for="n_pass">Contraseña (mín. 8)</label>
          <div class="input-group"><input type="text" name="pass" id="n_pass" class="form-control" required minlength="8" autocomplete="new-password">
          <button class="btn btn-outline-secondary" type="button" data-gen-pass="n_pass" title="Generar"><i class="fa-solid fa-dice"></i></button></div></div>
        <div class="mb-3"><label class="form-label small text-muted" for="n_role">Rol</label>
          <select name="role" id="n_role" class="form-select" data-role-select><?php foreach (ROLES as $r => $label): ?><option value="<?= e($r) ?>" <?= $r === 'visitante' ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></div>
        <div class="form-label small text-muted mb-1">Secciones a las que puede entrar</div>
        <?php $checklist('n_'); ?>
        <p class="small text-muted mt-2 mb-0 d-none" data-admin-note>Los administradores tienen acceso a todo y pueden gestionar usuarios.</p>
      </div>
      <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button><button type="submit" class="btn btn-accent">Crear usuario</button></div>
    </form></div></div>

    <!-- Rol y secciones -->
    <div class="modal fade" id="modalEdit" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-dialog-scrollable"><form class="modal-content" method="POST">
      <?= csrf_input() ?><input type="hidden" name="action" value="update_user"><input type="hidden" name="id" id="u_id">
      <div class="modal-header"><h5 class="modal-title fs-6">Rol y secciones de <span id="u_name"></span></h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button></div>
      <div class="modal-body">
        <div class="mb-3"><label class="form-label small text-muted" for="u_role">Rol</label>
          <select name="role" id="u_role" class="form-select" data-role-select><?php foreach (ROLES as $r => $label): ?><option value="<?= e($r) ?>"><?= e($label) ?></option><?php endforeach; ?></select>
          <div class="form-text">Al cambiar de rol se marcan los subpermisos habituales del inventario; puedes ajustarlos después.</div></div>
        <div class="form-label small text-muted mb-1">Secciones a las que puede entrar</div>
        <?php $checklist('u_'); ?>
        <p class="small text-muted mt-2 mb-0 d-none" data-admin-note>Los administradores tienen acceso a todo y pueden gestionar usuarios.</p>
      </div>
      <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button><button type="submit" class="btn btn-primary">Guardar cambios</button></div>
    </form></div></div>

    <!-- Contraseña -->
    <div class="modal fade" id="modalPass" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-dialog-centered"><form class="modal-content" method="POST" autocomplete="off">
      <?= csrf_input() ?><input type="hidden" name="action" value="reset_password"><input type="hidden" name="id" id="p_id">
      <div class="modal-header"><h5 class="modal-title fs-6">Nueva contraseña para <span id="p_name"></span></h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button></div>
      <div class="modal-body">
        <label class="form-label small text-muted" for="p_pass">Contraseña (mín. 8)</label>
        <div class="input-group"><input type="text" name="pass" id="p_pass" class="form-control" required minlength="8" autocomplete="new-password">
        <button class="btn btn-outline-secondary" type="button" data-gen-pass="p_pass" title="Generar"><i class="fa-solid fa-dice"></i></button></div>
        <p class="small text-muted mt-2 mb-0">Quedará cifrada y no podrás volver a verla: apúntala antes de guardar.</p>
      </div>
      <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button><button type="submit" class="btn btn-primary">Cambiar contraseña</button></div>
    </form></div></div>

    <!-- Borrar -->
    <div class="modal fade" id="modalDelete" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-dialog-centered modal-sm"><form class="modal-content" method="POST">
      <?= csrf_input() ?><input type="hidden" name="action" value="delete_user"><input type="hidden" name="id" id="d_id">
      <div class="modal-body text-center pt-4"><p class="mb-1" id="d_text"></p><p class="small text-warning mb-0">Se borrarán también sus regalos, sus sitios de parking y sus permisos. No se puede deshacer. Si solo quieres que no entre, usa «Desactivar».</p></div>
      <div class="modal-footer justify-content-center border-0 pt-0"><button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button><button type="submit" class="btn btn-danger btn-sm">Eliminar</button></div>
    </form></div></div>

    <script type="application/json" id="admin-data"><?= json_encode(['users' => (object) $data, 'presets' => $presets], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?></script>
  <?php endif; ?>

  <?php include __DIR__ . '/../../frontend/footer.php'; ?>
</body>
</html>

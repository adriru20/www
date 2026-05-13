<?php
// ──────────────────────────────────────────────
//  GiftList · Panel de Administración
// ──────────────────────────────────────────────
include "../../backend/config/ini.php";
gl_session();
gl_auth('admin');
$cu    = gl_user();
$flash = gl_get_flash();

// ── Acciones POST ──────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    gl_csrf_verify();
    $action = $_POST['action'] ?? '';
    $back   = '?tab=' . ($_POST['tab'] ?? 'dashboard');

    switch ($action) {
        case 'create_user':
            $res = gl_create_user(
                trim($_POST['name'] ?? ''),
                trim($_POST['email'] ?? ''),
                $_POST['pass'] ?? '',
                $_POST['role'] ?? 'user'
            );
            gl_flash($res === true ? 'Usuario creado.' : $res, $res === true ? 'success' : 'danger');
            break;

        case 'update_user':
            $id  = (int)($_POST['uid'] ?? 0);
            $res = gl_update_user($id,
                trim($_POST['name'] ?? ''),
                trim($_POST['email'] ?? ''),
                $_POST['role'] ?? 'user',
                $_POST['pass'] ?? ''
            );
            gl_flash($res === true ? 'Usuario actualizado.' : $res, $res === true ? 'success' : 'danger');
            break;

        case 'delete_user':
            $id = (int)($_POST['uid'] ?? 0);
            if ($id === $cu['id']) {
                gl_flash('No puedes eliminarte a ti mismo.', 'danger');
            } else {
                gl_delete_user($id);
                gl_flash('Usuario eliminado.');
            }
            break;
    }
    header("Location: admin.php$back"); exit;
}

$tab   = $_GET['tab'] ?? 'dashboard';
$users = gl_get_all_users();
$stats = gl_get_stats();

// Vista de regalos de un usuario específico
$view_uid   = isset($_GET['uid']) ? (int)$_GET['uid'] : 0;
$view_user  = $view_uid ? gl_get_user($view_uid) : null;
$view_gifts = $view_uid ? gl_get_gifts($view_uid) : [];

$prio_colors = ['high' => 'danger', 'medium' => 'warning', 'low' => 'success'];
$prio_labels = ['high' => 'Alta', 'medium' => 'Media', 'low' => 'Baja'];
?>
<!DOCTYPE html>
<html lang="es" data-bs-theme="dark">
<style>
  .gl-sidebar { width: 220px; flex-shrink: 0; position: sticky; top: 80px; height: fit-content; }
  .gl-sidebar .nav-link { border-radius: 8px; font-size: .875rem; padding: .5rem .75rem; color: var(--bs-secondary-color); }
  .gl-sidebar .nav-link:hover { background: var(--bs-secondary-bg); color: var(--bs-body-color); }
  .gl-sidebar .nav-link.active { background: rgba(var(--bs-primary-rgb),.15); color: var(--bs-primary); }
  .gl-sidebar .nav-link i { width: 18px; }
  .gl-main { flex: 1; min-width: 0; }
  .gl-stat .n { font-size: 2.2rem; font-weight: 700; line-height: 1; }
  .gl-stat .l { font-size: .78rem; color: var(--bs-secondary-color); }
  .gl-gift-card { border-left: 3px solid; }
  .gift-high   { border-left-color: var(--bs-danger) !important; }
  .gift-medium { border-left-color: var(--bs-warning) !important; }
  .gift-low    { border-left-color: var(--bs-success) !important; }
  @media (max-width: 768px) {
    .gl-sidebar { display: none; }
    .gl-sidebar.show { display: block !important; position: relative; top: 0; width: 100%; }
  }
</style>
<body>
  <?php include "{$src}frontend/menu.php"; ?>

  <div class="container py-4" style="max-width:1100px">

    <!-- Flash -->
    <?php if ($flash): ?>
      <div class="alert alert-<?= gl_h($flash['type']) ?> alert-dismissible fade show py-2 mb-3">
        <?= gl_h($flash['msg']) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
      </div>
    <?php endif; ?>

    <div class="d-flex gap-4 align-items-start">

      <!-- ── Sidebar ── -->
      <aside class="gl-sidebar">
        <div class="card border-secondary">
          <div class="card-body p-2">
            <div class="px-2 py-1 mb-1">
              <div class="small fw-bold text-uppercase text-secondary" style="font-size:.7rem;letter-spacing:.09em">Panel</div>
            </div>
            <nav class="nav flex-column gap-1">
              <a href="?tab=dashboard" class="nav-link <?= $tab==='dashboard'?'active':'' ?>">
                <i class="fa fa-chart-simple me-2"></i> Dashboard
              </a>
            </nav>
            <div class="px-2 py-1 mt-2 mb-1">
              <div class="small fw-bold text-uppercase text-secondary" style="font-size:.7rem;letter-spacing:.09em">Gestión</div>
            </div>
            <nav class="nav flex-column gap-1">
              <a href="?tab=users" class="nav-link <?= $tab==='users'?'active':'' ?>">
                <i class="fa fa-users me-2"></i> Usuarios
              </a>
              <a href="?tab=gifts" class="nav-link <?= $tab==='gifts'?'active':'' ?>">
                <i class="fa fa-gift me-2"></i> Listas de regalos
              </a>
            </nav>
            <hr class="border-secondary my-2">
            <nav class="nav flex-column">
              <a href="dashboard.php" class="nav-link">
                <i class="fa fa-list-check me-2"></i> Mi lista
              </a>
              <a href="logout.php" class="nav-link text-danger">
                <i class="fa fa-right-from-bracket me-2"></i> Salir
              </a>
            </nav>
          </div>
        </div>
        <div class="card border-secondary mt-2">
          <div class="card-body py-2 px-3">
            <div class="small fw-bold">👑 <?= gl_h($cu['name']) ?></div>
            <div class="small text-warning">Administrador</div>
          </div>
        </div>
      </aside>

      <!-- ── Main ── -->
      <main class="gl-main">

        <!-- ════ DASHBOARD ════ -->
        <?php if ($tab === 'dashboard'): ?>
          <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
              <h4 class="mb-0">📊 Dashboard</h4>
              <p class="text-secondary small mb-0">Resumen general del sistema</p>
            </div>
          </div>

          <div class="row g-3 mb-4">
            <?php foreach ([
              ['val' => $stats['total_users'],  'lbl' => '👤 Usuarios',    'color' => 'primary'],
              ['val' => $stats['total_admins'], 'lbl' => '👑 Admins',      'color' => 'warning'],
              ['val' => $stats['total_gifts'],  'lbl' => '🎁 Regalos',     'color' => 'info'],
              ['val' => $stats['reserved'],     'lbl' => '✅ Reservados',  'color' => 'success'],
            ] as $s): ?>
            <div class="col-6 col-md-3">
              <div class="card border-secondary text-center py-3">
                <div class="gl-stat">
                  <div class="n text-<?= $s['color'] ?>"><?= (int)$s['val'] ?></div>
                  <div class="l mt-1"><?= $s['lbl'] ?></div>
                </div>
              </div>
            </div>
            <?php endforeach; ?>
          </div>

          <div class="card border-secondary">
            <div class="card-header border-secondary d-flex justify-content-between align-items-center">
              <span class="fw-bold">Usuarios recientes</span>
              <a href="?tab=users" class="btn btn-outline-secondary btn-sm">Ver todos →</a>
            </div>
            <div class="table-responsive">
              <table class="table table-dark table-hover mb-0">
                <thead class="text-secondary" style="font-size:.78rem;text-transform:uppercase">
                  <tr><th>Nombre</th><th>Email</th><th>Rol</th><th>Regalos</th><th>Registro</th></tr>
                </thead>
                <tbody>
                  <?php foreach (array_slice($users, 0, 5) as $u): ?>
                  <tr>
                    <td class="fw-semibold"><?= gl_h($u['name']) ?></td>
                    <td class="text-secondary small"><?= gl_h($u['email']) ?></td>
                    <td>
                      <span class="badge <?= $u['role']==='admin' ? 'bg-warning-subtle text-warning' : 'bg-secondary' ?>">
                        <?= $u['role'] ?>
                      </span>
                    </td>
                    <td><span class="badge bg-secondary"><?= (int)$u['gift_count'] ?></span></td>
                    <td class="text-secondary small"><?= date('d M Y', strtotime($u['created_at'])) ?></td>
                  </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          </div>

        <!-- ════ USUARIOS ════ -->
        <?php elseif ($tab === 'users'): ?>
          <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
              <h4 class="mb-0">👥 Usuarios</h4>
              <p class="text-secondary small mb-0"><?= count($users) ?> registrados</p>
            </div>
            <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modalCreate">
              <i class="fa fa-plus me-1"></i> Nuevo usuario
            </button>
          </div>

          <div class="card border-secondary">
            <div class="table-responsive">
              <table class="table table-dark table-hover mb-0">
                <thead class="text-secondary" style="font-size:.78rem;text-transform:uppercase">
                  <tr><th>Nombre</th><th>Email</th><th>Rol</th><th>Regalos</th><th>Registro</th><th>Acciones</th></tr>
                </thead>
                <tbody>
                  <?php foreach ($users as $u): ?>
                  <tr>
                    <td class="fw-semibold"><?= gl_h($u['name']) ?></td>
                    <td class="text-secondary small"><?= gl_h($u['email']) ?></td>
                    <td>
                      <span class="badge <?= $u['role']==='admin' ? 'bg-warning-subtle text-warning border border-warning-subtle' : 'bg-secondary' ?>">
                        <?= $u['role'] ?>
                      </span>
                    </td>
                    <td><span class="badge bg-secondary"><?= (int)$u['gift_count'] ?></span></td>
                    <td class="text-secondary small"><?= date('d M Y', strtotime($u['created_at'])) ?></td>
                    <td>
                      <div class="d-flex gap-1">
                        <button class="btn btn-outline-light btn-sm"
                          onclick="openEdit(<?= $u['id'] ?>,'<?= addslashes(gl_h($u['name'])) ?>','<?= addslashes(gl_h($u['email'])) ?>','<?= $u['role'] ?>')">
                          <i class="fa fa-pen"></i>
                        </button>
                        <?php if ((int)$u['id'] !== $cu['id']): ?>
                        <form method="post" onsubmit="return confirm('¿Eliminar a <?= addslashes(gl_h($u['name'])) ?>?')">
                          <?= gl_csrf_input() ?>
                          <input type="hidden" name="action" value="delete_user">
                          <input type="hidden" name="uid" value="<?= $u['id'] ?>">
                          <input type="hidden" name="tab" value="users">
                          <button type="submit" class="btn btn-outline-danger btn-sm"><i class="fa fa-trash"></i></button>
                        </form>
                        <?php endif; ?>
                      </div>
                    </td>
                  </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          </div>

        <!-- ════ LISTAS DE REGALOS ════ -->
        <?php elseif ($tab === 'gifts'): ?>

          <?php if ($view_user): ?>
            <!-- Vista detalle de lista -->
            <div class="d-flex align-items-center gap-3 mb-4 flex-wrap">
              <a href="?tab=gifts" class="btn btn-outline-secondary btn-sm">
                <i class="fa fa-arrow-left me-1"></i> Volver
              </a>
              <div>
                <h4 class="mb-0">🎀 Lista de <?= gl_h($view_user['name']) ?></h4>
                <p class="text-secondary small mb-0"><?= count($view_gifts) ?> regalos</p>
              </div>
            </div>

            <?php if (empty($view_gifts)): ?>
              <div class="card border-secondary text-center py-5">
                <div class="display-4 mb-2">🎈</div>
                <p class="text-secondary">Este usuario no tiene regalos en su lista.</p>
              </div>
            <?php else: ?>
              <div class="row g-3">
                <?php foreach ($view_gifts as $g): ?>
                <div class="col-12 col-sm-6 col-xl-4">
                  <div class="card border-secondary gl-gift-card gift-<?= gl_h($g['priority']) ?> h-100">
                    <div class="card-body">
                      <div class="d-flex justify-content-between align-items-start mb-1">
                        <h6 class="card-title mb-0 me-2"><?= gl_h($g['name']) ?></h6>
                        <?php if ($g['reserved_by']): ?>
                          <span class="badge bg-success-subtle text-success border border-success-subtle" style="font-size:.7rem;flex-shrink:0">✓</span>
                        <?php endif; ?>
                      </div>
                      <?php if ($g['description']): ?>
                        <p class="text-secondary small mb-2"><?= gl_h($g['description']) ?></p>
                      <?php endif; ?>
                      <div class="d-flex justify-content-between align-items-center flex-wrap gap-1">
                        <span class="fw-bold text-warning-emphasis"><?= gl_price($g['price'] ? (float)$g['price'] : null) ?></span>
                        <span class="badge bg-<?= $prio_colors[$g['priority']] ?>-subtle text-<?= $prio_colors[$g['priority']] ?> border border-<?= $prio_colors[$g['priority']] ?>-subtle" style="font-size:.7rem">
                          <?= $prio_labels[$g['priority']] ?>
                        </span>
                      </div>
                      <?php if ($g['url']): ?>
                        <a href="<?= gl_h($g['url']) ?>" target="_blank" rel="noopener"
                           class="btn btn-outline-secondary btn-sm w-100 mt-2" style="font-size:.78rem">
                          <i class="fa fa-link me-1"></i> Ver enlace
                        </a>
                      <?php endif; ?>
                      <?php if ($g['reserved_by']): ?>
                        <div class="mt-2 small text-success">
                          <i class="fa fa-check-circle me-1"></i> Reservado por <?= gl_h($g['reserved_by_name'] ?? 'alguien') ?>
                        </div>
                      <?php endif; ?>
                    </div>
                  </div>
                </div>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>

          <?php else: ?>
            <!-- Lista de usuarios para seleccionar -->
            <div class="mb-4">
              <h4 class="mb-0">🎁 Listas de regalos</h4>
              <p class="text-secondary small">Selecciona un usuario para ver su lista</p>
            </div>

            <div class="card border-secondary">
              <div class="table-responsive">
                <table class="table table-dark table-hover mb-0">
                  <thead class="text-secondary" style="font-size:.78rem;text-transform:uppercase">
                    <tr><th>Usuario</th><th>Email</th><th>Regalos</th><th>Ver lista</th></tr>
                  </thead>
                  <tbody>
                    <?php foreach ($users as $u): ?>
                    <tr>
                      <td class="fw-semibold"><?= gl_h($u['name']) ?></td>
                      <td class="text-secondary small"><?= gl_h($u['email']) ?></td>
                      <td><span class="badge bg-secondary"><?= (int)$u['gift_count'] ?></span></td>
                      <td>
                        <a href="?tab=gifts&uid=<?= $u['id'] ?>" class="btn btn-outline-warning btn-sm">
                          <i class="fa fa-gift me-1"></i> Ver lista
                        </a>
                      </td>
                    </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            </div>
          <?php endif; ?>

        <?php endif; /* end tabs */ ?>

      </main>
    </div><!-- /d-flex -->
  </div><!-- /container -->

  <!-- ── Modal: Crear usuario ── -->
  <div class="modal fade" id="modalCreate" tabindex="-1">
    <div class="modal-dialog">
      <div class="modal-content border-secondary">
        <div class="modal-header border-secondary">
          <h5 class="modal-title">👤 Nuevo usuario</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <form method="post">
          <?= gl_csrf_input() ?>
          <input type="hidden" name="action" value="create_user">
          <input type="hidden" name="tab" value="users">
          <div class="modal-body">
            <div class="row g-3">
              <div class="col-8">
                <label class="form-label small fw-bold text-secondary text-uppercase">Nombre *</label>
                <input type="text" name="name" class="form-control" required>
              </div>
              <div class="col-4">
                <label class="form-label small fw-bold text-secondary text-uppercase">Rol</label>
                <select name="role" class="form-select">
                  <option value="user">Usuario</option>
                  <option value="admin">Admin</option>
                </select>
              </div>
            </div>
            <div class="mt-3">
              <label class="form-label small fw-bold text-secondary text-uppercase">Email *</label>
              <input type="email" name="email" class="form-control" required>
            </div>
            <div class="mt-3">
              <label class="form-label small fw-bold text-secondary text-uppercase">Contraseña *</label>
              <input type="password" name="pass" class="form-control" required minlength="8">
              <div class="form-text">Mínimo 8 caracteres</div>
            </div>
          </div>
          <div class="modal-footer border-secondary">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
            <button type="submit" class="btn btn-primary"><i class="fa fa-plus me-1"></i> Crear usuario</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- ── Modal: Editar usuario ── -->
  <div class="modal fade" id="modalEdit" tabindex="-1">
    <div class="modal-dialog">
      <div class="modal-content border-secondary">
        <div class="modal-header border-secondary">
          <h5 class="modal-title">✏️ Editar usuario</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <form method="post">
          <?= gl_csrf_input() ?>
          <input type="hidden" name="action" value="update_user">
          <input type="hidden" name="tab" value="users">
          <input type="hidden" name="uid" id="e-uid">
          <div class="modal-body">
            <div class="row g-3">
              <div class="col-8">
                <label class="form-label small fw-bold text-secondary text-uppercase">Nombre *</label>
                <input type="text" name="name" id="e-name" class="form-control" required>
              </div>
              <div class="col-4">
                <label class="form-label small fw-bold text-secondary text-uppercase">Rol</label>
                <select name="role" id="e-role" class="form-select">
                  <option value="user">Usuario</option>
                  <option value="admin">Admin</option>
                </select>
              </div>
            </div>
            <div class="mt-3">
              <label class="form-label small fw-bold text-secondary text-uppercase">Email *</label>
              <input type="email" name="email" id="e-email" class="form-control" required>
            </div>
            <div class="mt-3">
              <label class="form-label small fw-bold text-secondary text-uppercase">Nueva contraseña</label>
              <input type="password" name="pass" class="form-control" minlength="8" placeholder="Dejar vacío para no cambiar">
            </div>
          </div>
          <div class="modal-footer border-secondary">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
            <button type="submit" class="btn btn-primary"><i class="fa fa-floppy-disk me-1"></i> Guardar cambios</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <?php include "{$src}frontend/footer.php"; ?>

  <script>
  function openEdit(id, name, email, role) {
    document.getElementById('e-uid').value   = id;
    document.getElementById('e-name').value  = name;
    document.getElementById('e-email').value = email;
    document.getElementById('e-role').value  = role;
    new bootstrap.Modal(document.getElementById('modalEdit')).show();
  }
  </script>
</body>
</html>

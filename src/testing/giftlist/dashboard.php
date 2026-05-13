<?php
// ──────────────────────────────────────────────
//  GiftList · Dashboard de usuario
// ──────────────────────────────────────────────
include "../../backend/config/ini.php";

gl_session();
gl_auth('user');
$cu    = gl_user();
$flash = gl_get_flash();

// ── Acciones POST ──────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    gl_csrf_verify();
    $action = $_POST['action'] ?? '';

    switch ($action) {
        case 'add':
            $price = ($_POST['price'] ?? '') !== '' ? (float)$_POST['price'] : null;
            $ok = gl_create_gift(
                $cu['id'],
                trim($_POST['name'] ?? ''),
                trim($_POST['desc']  ?? ''),
                trim($_POST['url']   ?? ''),
                $price,
                $_POST['priority'] ?? 'medium'
            );
            gl_flash($ok ? '🎁 Regalo añadido.' : 'Error al guardar.', $ok ? 'success' : 'danger');
            break;

        case 'edit':
            $gid   = (int)($_POST['gid'] ?? 0);
            $gift  = gl_get_gift($gid);
            if ($gift && (int)$gift['user_id'] === $cu['id']) {
                $price = ($_POST['price'] ?? '') !== '' ? (float)$_POST['price'] : null;
                $ok = gl_update_gift($gid,
                    trim($_POST['name'] ?? ''),
                    trim($_POST['desc']  ?? ''),
                    trim($_POST['url']   ?? ''),
                    $price,
                    $_POST['priority'] ?? 'medium'
                );
                gl_flash($ok ? 'Regalo actualizado.' : 'Error al actualizar.', $ok ? 'success' : 'danger');
            }
            break;

        case 'delete':
            $gid  = (int)($_POST['gid'] ?? 0);
            $gift = gl_get_gift($gid);
            if ($gift && (int)$gift['user_id'] === $cu['id']) {
                gl_delete_gift($gid);
                gl_flash('Regalo eliminado.');
            }
            break;

        case 'reserve':
            $gid  = (int)($_POST['gid'] ?? 0);
            $gift = gl_get_gift($gid);
            // No puede reservar sus propios regalos
            if ($gift && (int)$gift['user_id'] !== $cu['id']) {
                gl_toggle_reserve($gid, $cu['id']);
                gl_flash('Reserva actualizada.');
            }
            break;
    }
    header('Location: dashboard.php'); exit;
}

$gifts = gl_get_gifts($cu['id']);

// Agrupar por prioridad
$by_prio = ['high' => [], 'medium' => [], 'low' => []];
foreach ($gifts as $g) $by_prio[$g['priority']][] = $g;

$prio_labels = ['high' => 'Alta prioridad', 'medium' => 'Media prioridad', 'low' => 'Baja prioridad'];
$prio_colors = ['high' => 'danger', 'medium' => 'warning', 'low' => 'success'];
$prio_icons  = ['high' => '🔴', 'medium' => '🟡', 'low' => '🟢'];
?>
<!DOCTYPE html>
<html lang="es" data-bs-theme="dark">
<style>
  .gl-card-gift { transition: transform .15s; border-left: 3px solid transparent; }
  .gl-card-gift:hover { transform: translateY(-2px); }
  .gl-card-gift.prio-high   { border-left-color: var(--bs-danger); }
  .gl-card-gift.prio-medium { border-left-color: var(--bs-warning); }
  .gl-card-gift.prio-low    { border-left-color: var(--bs-success); }
  .gl-section-title { font-size: .75rem; text-transform: uppercase; letter-spacing: .09em; font-weight: 700; }
  .gl-stat .n { font-size: 2rem; font-weight: 700; line-height: 1; }
  .gl-stat .l { font-size: .75rem; color: var(--bs-secondary-color); }
  .priority-radio-group .btn-check:checked + .btn { opacity: 1; }
  .priority-radio-group .btn { opacity: .55; transition: opacity .15s; }
</style>
<body>
  <?php include "{$src}frontend/menu.php"; ?>

  <div class="container py-4" style="max-width:960px">

    <!-- Top bar -->
    <div class="d-flex align-items-start justify-content-between flex-wrap gap-3 mb-4">
      <div>
        <h3 class="mb-1">🎀 Mi lista de regalos</h3>
        <p class="text-secondary small mb-0">Hola, <strong><?= gl_h($cu['name']) ?></strong>
          <?php if (gl_is_admin()): ?>
            · <a href="admin.php" class="text-warning small">👑 Panel admin</a>
          <?php endif; ?>
        </p>
      </div>
      <div class="d-flex gap-2 align-items-center">
        <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modalAdd">
          <i class="fa fa-plus me-1"></i> Añadir regalo
        </button>
        <a href="logout.php" class="btn btn-outline-secondary btn-sm">Salir</a>
      </div>
    </div>

    <!-- Flash -->
    <?php if ($flash): ?>
      <div class="alert alert-<?= gl_h($flash['type']) ?> alert-dismissible fade show py-2">
        <?= gl_h($flash['msg']) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
      </div>
    <?php endif; ?>

    <!-- Stats -->
    <div class="row g-3 mb-4">
      <div class="col-6 col-md-3">
        <div class="card border-secondary text-center py-3">
          <div class="gl-stat"><div class="n text-primary"><?= count($gifts) ?></div><div class="l mt-1">🎁 Total</div></div>
        </div>
      </div>
      <div class="col-6 col-md-3">
        <div class="card border-secondary text-center py-3">
          <div class="gl-stat"><div class="n text-danger"><?= count($by_prio['high']) ?></div><div class="l mt-1">🔴 Alta prior.</div></div>
        </div>
      </div>
      <div class="col-6 col-md-3">
        <div class="card border-secondary text-center py-3">
          <div class="gl-stat"><div class="n text-warning"><?= count($by_prio['medium']) ?></div><div class="l mt-1">🟡 Media prior.</div></div>
        </div>
      </div>
      <div class="col-6 col-md-3">
        <div class="card border-secondary text-center py-3">
          <?php $reserved = count(array_filter($gifts, fn($g) => $g['reserved_by'])); ?>
          <div class="gl-stat"><div class="n text-success"><?= $reserved ?></div><div class="l mt-1">✅ Reservados</div></div>
        </div>
      </div>
    </div>

    <!-- Lista vacía -->
    <?php if (empty($gifts)): ?>
      <div class="card border-secondary border-dashed text-center py-5">
        <div class="display-4 mb-3">🎈</div>
        <h5>Tu lista está vacía</h5>
        <p class="text-secondary">Empieza añadiendo tu primer deseo</p>
        <div>
          <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalAdd">
            <i class="fa fa-plus me-1"></i> Añadir regalo
          </button>
        </div>
      </div>

    <?php else: ?>
      <?php foreach (['high','medium','low'] as $prio): ?>
        <?php if (!empty($by_prio[$prio])): ?>
          <div class="mb-4">
            <div class="d-flex align-items-center gap-2 mb-3">
              <span class="gl-section-title text-<?= $prio_colors[$prio] ?>">
                <?= $prio_icons[$prio] ?> <?= $prio_labels[$prio] ?>
              </span>
              <span class="badge bg-secondary"><?= count($by_prio[$prio]) ?></span>
              <hr class="flex-grow-1 m-0">
            </div>

            <div class="row g-3">
              <?php foreach ($by_prio[$prio] as $g): ?>
              <div class="col-12 col-sm-6 col-lg-4">
                <div class="card border-secondary gl-card-gift prio-<?= gl_h($g['priority']) ?> h-100">
                  <div class="card-body d-flex flex-column">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                      <h6 class="card-title mb-0 me-2"><?= gl_h($g['name']) ?></h6>
                      <?php if ($g['reserved_by']): ?>
                        <span class="badge bg-success-subtle text-success border border-success-subtle flex-shrink-0" style="font-size:.7rem">✓ Reservado</span>
                      <?php endif; ?>
                    </div>

                    <?php if ($g['description']): ?>
                      <p class="text-secondary small mb-2" style="overflow:hidden;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical">
                        <?= gl_h($g['description']) ?>
                      </p>
                    <?php endif; ?>

                    <div class="mt-auto">
                      <?php if ($g['price']): ?>
                        <div class="fw-bold text-warning-emphasis mb-2"><?= gl_price((float)$g['price']) ?></div>
                      <?php endif; ?>
                      <?php if ($g['url']): ?>
                        <a href="<?= gl_h($g['url']) ?>" target="_blank" rel="noopener"
                           class="btn btn-outline-secondary btn-sm w-100 mb-2" style="font-size:.78rem">
                          <i class="fa fa-link me-1"></i> Ver enlace
                        </a>
                      <?php endif; ?>
                      <div class="d-flex gap-1">
                        <button class="btn btn-outline-light btn-sm flex-grow-1"
                          onclick="openEdit(<?= $g['id'] ?>,'<?= addslashes(gl_h($g['name'])) ?>','<?= addslashes(gl_h($g['description'] ?? '')) ?>','<?= addslashes(gl_h($g['url'] ?? '')) ?>','<?= $g['price'] ?? '' ?>','<?= $g['priority'] ?>')">
                          <i class="fa fa-pen"></i>
                        </button>
                        <form method="post" onsubmit="return confirm('¿Eliminar este regalo?')">
                          <?= gl_csrf_input() ?>
                          <input type="hidden" name="action" value="delete">
                          <input type="hidden" name="gid" value="<?= $g['id'] ?>">
                          <button type="submit" class="btn btn-outline-danger btn-sm">
                            <i class="fa fa-trash"></i>
                          </button>
                        </form>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
              <?php endforeach; ?>
            </div>
          </div>
        <?php endif; ?>
      <?php endforeach; ?>
    <?php endif; ?>

  </div><!-- /container -->

  <!-- ── Modal: Añadir regalo ── -->
  <div class="modal fade" id="modalAdd" tabindex="-1">
    <div class="modal-dialog">
      <div class="modal-content border-secondary">
        <div class="modal-header border-secondary">
          <h5 class="modal-title">🎁 Añadir regalo</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <form method="post">
          <?= gl_csrf_input() ?>
          <input type="hidden" name="action" value="add">
          <div class="modal-body">
            <div class="mb-3">
              <label class="form-label small fw-bold text-secondary text-uppercase">Nombre *</label>
              <input type="text" name="name" class="form-control" required placeholder="Ej: Auriculares Sony WH-1000XM5">
            </div>
            <div class="mb-3">
              <label class="form-label small fw-bold text-secondary text-uppercase">Descripción</label>
              <textarea name="desc" class="form-control" rows="2" placeholder="Talla, color, detalles…"></textarea>
            </div>
            <div class="row g-3 mb-3">
              <div class="col-6">
                <label class="form-label small fw-bold text-secondary text-uppercase">Precio (€)</label>
                <input type="number" name="price" class="form-control" min="0" step="0.01" placeholder="0.00">
              </div>
              <div class="col-6">
                <label class="form-label small fw-bold text-secondary text-uppercase">URL / Enlace</label>
                <input type="url" name="url" class="form-control" placeholder="https://…">
              </div>
            </div>
            <div class="mb-1">
              <label class="form-label small fw-bold text-secondary text-uppercase">Prioridad</label>
              <div class="btn-group w-100 priority-radio-group">
                <input type="radio" class="btn-check" name="priority" id="pa-high" value="high" autocomplete="off">
                <label class="btn btn-outline-danger" for="pa-high">🔴 Alta</label>
                <input type="radio" class="btn-check" name="priority" id="pa-med" value="medium" autocomplete="off" checked>
                <label class="btn btn-outline-warning" for="pa-med">🟡 Media</label>
                <input type="radio" class="btn-check" name="priority" id="pa-low" value="low" autocomplete="off">
                <label class="btn btn-outline-success" for="pa-low">🟢 Baja</label>
              </div>
            </div>
          </div>
          <div class="modal-footer border-secondary">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
            <button type="submit" class="btn btn-primary"><i class="fa fa-plus me-1"></i> Añadir</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- ── Modal: Editar regalo ── -->
  <div class="modal fade" id="modalEdit" tabindex="-1">
    <div class="modal-dialog">
      <div class="modal-content border-secondary">
        <div class="modal-header border-secondary">
          <h5 class="modal-title">✏️ Editar regalo</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <form method="post">
          <?= gl_csrf_input() ?>
          <input type="hidden" name="action" value="edit">
          <input type="hidden" name="gid" id="e-gid">
          <div class="modal-body">
            <div class="mb-3">
              <label class="form-label small fw-bold text-secondary text-uppercase">Nombre *</label>
              <input type="text" name="name" id="e-name" class="form-control" required>
            </div>
            <div class="mb-3">
              <label class="form-label small fw-bold text-secondary text-uppercase">Descripción</label>
              <textarea name="desc" id="e-desc" class="form-control" rows="2"></textarea>
            </div>
            <div class="row g-3 mb-3">
              <div class="col-6">
                <label class="form-label small fw-bold text-secondary text-uppercase">Precio (€)</label>
                <input type="number" name="price" id="e-price" class="form-control" min="0" step="0.01">
              </div>
              <div class="col-6">
                <label class="form-label small fw-bold text-secondary text-uppercase">URL / Enlace</label>
                <input type="url" name="url" id="e-url" class="form-control">
              </div>
            </div>
            <div class="mb-1">
              <label class="form-label small fw-bold text-secondary text-uppercase">Prioridad</label>
              <div class="btn-group w-100 priority-radio-group">
                <input type="radio" class="btn-check" name="priority" id="pe-high" value="high" autocomplete="off">
                <label class="btn btn-outline-danger" for="pe-high">🔴 Alta</label>
                <input type="radio" class="btn-check" name="priority" id="pe-med" value="medium" autocomplete="off">
                <label class="btn btn-outline-warning" for="pe-med">🟡 Media</label>
                <input type="radio" class="btn-check" name="priority" id="pe-low" value="low" autocomplete="off">
                <label class="btn btn-outline-success" for="pe-low">🟢 Baja</label>
              </div>
            </div>
          </div>
          <div class="modal-footer border-secondary">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
            <button type="submit" class="btn btn-primary"><i class="fa fa-floppy-disk me-1"></i> Guardar</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <?php include "{$src}frontend/footer.php"; ?>

  <script>
  function openEdit(id, name, desc, url, price, priority) {
    document.getElementById('e-gid').value   = id;
    document.getElementById('e-name').value  = name;
    document.getElementById('e-desc').value  = desc;
    document.getElementById('e-url').value   = url;
    document.getElementById('e-price').value = price;
    const radioId = { high: 'pe-high', medium: 'pe-med', low: 'pe-low' }[priority];
    if (radioId) document.getElementById(radioId).checked = true;
    new bootstrap.Modal(document.getElementById('modalEdit')).show();
  }
  </script>
</body>
</html>

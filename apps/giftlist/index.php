<?php
require_once __DIR__ . '/../../backend/config/bootstrap.php';
require_app('giftlist');
require_once __DIR__ . '/../../backend/config/db.php';
require_once __DIR__ . '/lib.php';
global $conn;

$my_id   = (string) $_SESSION['user_id'];
$view_id = (isset($_GET['view']) && $_GET['view'] !== '') ? (string) $_GET['view'] : $my_id;
$is_mine = ($view_id === $my_id);

gift_handle_actions($my_id);   // POST: añade, edita o borra y redirige

$owner = gift_user($view_id);
if (!$owner) {                 // lista de un usuario que no existe
  flash_add('No existe esa lista.', 'warning');
  header('Location: index.php');
  exit();
}

$people = gift_people($my_id);
$gifts  = gift_list($view_id);

$page_title   = $is_mine ? 'Mi lista de regalos' : 'Lista de ' . $owner['user'];
$page_scripts = ['/apps/giftlist/giftlist.js'];
// Datos para rellenar el formulario de edición
$gift_data = [];
foreach ($gifts as $g) $gift_data[$g['id']] = ['name' => $g['item_name'], 'desc' => $g['item_description'], 'url' => $g['item_url']];
?>
<!DOCTYPE html>
<html lang="es" data-bs-theme="dark">
<?php include __DIR__ . '/../../backend/config/ini.php'; ?>
<body>
  <?php include __DIR__ . '/../../frontend/menu.php'; ?>

  <main class="page-container">
    <?php flash_render(); ?>

    <div class="page-header">
      <h1 class="h3">🎁 Gift list</h1>
    </div>

    <!-- Personas: pastillas con scroll horizontal en móvil -->
    <nav class="people-nav mb-3" aria-label="Listas de regalos">
      <a href="index.php" class="people-pill <?= $is_mine ? 'active' : '' ?>">
        Mi lista <span class="badge"><?= (int) $people['mine'] ?></span>
      </a>
      <?php foreach ($people['people'] as $p): ?>
        <a href="index.php?view=<?= urlencode($p['id']) ?>" class="people-pill <?= $view_id === $p['id'] ? 'active' : '' ?>">
          <?= e($p['user']) ?> <span class="badge"><?= (int) $p['n'] ?></span>
        </a>
      <?php endforeach; ?>
    </nav>

    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
      <h2 class="h4 mb-0">
        <?= $is_mine ? 'Mis regalos' : 'Regalos de <span class="text-gradient">' . e($owner['user']) . '</span>' ?>
        <span class="count-pill" id="giftCount"><?= count($gifts) ?></span>
      </h2>
      <?php if ($is_mine): ?>
        <button class="btn btn-accent" type="button" data-bs-toggle="collapse" data-bs-target="#addGift" aria-expanded="false" aria-controls="addGift">
          <i class="fa-solid fa-plus"></i> Añadir regalo
        </button>
      <?php endif; ?>
    </div>

    <?php if ($is_mine): ?>
      <div class="collapse mb-4" id="addGift">
        <div class="card"><div class="card-body">
          <form action="index.php" method="POST">
            <?= csrf_input() ?>
            <input type="hidden" name="action" value="add_item">
            <div class="row g-3">
              <div class="col-md-6">
                <label class="form-label text-muted small mb-1" for="g_name">Nombre del regalo *</label>
                <input type="text" id="g_name" name="item_name" class="form-control" maxlength="255" required placeholder="Qué te gustaría recibir">
              </div>
              <div class="col-md-6">
                <label class="form-label text-muted small mb-1" for="g_url">Enlace (opcional)</label>
                <input type="url" id="g_url" name="item_url" class="form-control" maxlength="2048" placeholder="https://…">
              </div>
              <div class="col-12">
                <label class="form-label text-muted small mb-1" for="g_desc">Notas (opcional)</label>
                <textarea id="g_desc" name="item_description" class="form-control" rows="2" maxlength="2000" placeholder="Talla, color, tienda…"></textarea>
              </div>
              <div class="col-12 text-end">
                <button type="button" class="btn btn-outline-secondary" data-bs-toggle="collapse" data-bs-target="#addGift">Cancelar</button>
                <button type="submit" class="btn btn-accent">Guardar en mi lista</button>
              </div>
            </div>
          </form>
        </div></div>
      </div>
    <?php endif; ?>

    <?php if ($gifts): ?>
      <div class="search-container mb-3">
        <input type="search" id="giftSearch" class="form-control" placeholder="Buscar en esta lista…" autocomplete="off" aria-label="Buscar en esta lista">
      </div>

      <div class="row g-3" id="giftGrid">
        <?php foreach ($gifts as $g): $ts = strtotime($g['created_at']); ?>
          <div class="col-12 col-lg-6 gift-col" data-search="<?= e(mb_strtolower($g['item_name'] . ' ' . $g['item_description'])) ?>">
            <article class="card gift-card h-100">
              <div class="card-body d-flex flex-column">
                <div class="d-flex justify-content-between align-items-start gap-2">
                  <h3 class="h5 mb-1"><?= e($g['item_name']) ?></h3>
                  <small class="text-muted text-nowrap" title="<?= e(date('d/m/Y H:i', $ts)) ?>"><?= e(date('d/m/Y', $ts)) ?></small>
                </div>
                <?php if (trim((string) $g['item_description']) !== ''): ?>
                  <p class="gift-desc mb-2"><?= nl2br(e($g['item_description'])) ?></p>
                <?php endif; ?>
                <div class="mt-auto d-flex flex-wrap gap-2 pt-2">
                  <?php if ($g['item_url'] && preg_match('#^https?://#i', $g['item_url'])): ?>
                    <a href="<?= e($g['item_url']) ?>" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-primary">
                      <i class="fa-solid fa-arrow-up-right-from-square"></i> Ver regalo
                      <small class="opacity-75">· <?= e(preg_replace('/^www\./', '', (string) parse_url($g['item_url'], PHP_URL_HOST))) ?></small>
                    </a>
                  <?php endif; ?>
                  <?php if ($is_mine): ?>
                    <button type="button" class="btn btn-sm btn-outline-secondary ms-auto" data-gift-edit="<?= (int) $g['id'] ?>" title="Editar"><i class="fa-solid fa-pen"></i> Editar</button>
                    <button type="button" class="btn btn-sm btn-outline-danger" data-gift-delete="<?= (int) $g['id'] ?>" data-name="<?= e($g['item_name']) ?>" title="Eliminar"><i class="fa-solid fa-trash"></i></button>
                  <?php endif; ?>
                </div>
              </div>
            </article>
          </div>
        <?php endforeach; ?>
      </div>
      <p class="text-center text-muted py-4 d-none" id="giftNoResults">Ningún regalo coincide con la búsqueda.</p>
    <?php else: ?>
      <div class="empty-state">
        <p><?= $is_mine ? 'Todavía no has añadido ningún regalo.' : e($owner['user']) . ' todavía no ha añadido regalos.' ?></p>
        <?php if ($is_mine): ?><button class="btn btn-accent" type="button" data-bs-toggle="collapse" data-bs-target="#addGift">Añadir el primero</button><?php endif; ?>
      </div>
    <?php endif; ?>
  </main>

  <?php if ($is_mine): ?>
    <!-- Editar regalo -->
    <div class="modal fade" id="modalEditGift" tabindex="-1" aria-labelledby="modalEditGiftTitle" aria-hidden="true">
      <div class="modal-dialog">
        <form class="modal-content" method="POST" action="index.php">
          <?= csrf_input() ?>
          <input type="hidden" name="action" value="edit_item">
          <input type="hidden" name="id" id="e_id">
          <div class="modal-header"><h5 class="modal-title fs-6" id="modalEditGiftTitle">Editar regalo</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button></div>
          <div class="modal-body">
            <div class="mb-3"><label class="form-label small text-muted mb-1" for="e_name">Nombre *</label>
              <input type="text" name="item_name" id="e_name" class="form-control" maxlength="255" required></div>
            <div class="mb-3"><label class="form-label small text-muted mb-1" for="e_url">Enlace</label>
              <input type="url" name="item_url" id="e_url" class="form-control" maxlength="2048" placeholder="https://…"></div>
            <div class="mb-1"><label class="form-label small text-muted mb-1" for="e_desc">Notas</label>
              <textarea name="item_description" id="e_desc" class="form-control" rows="3" maxlength="2000"></textarea></div>
          </div>
          <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
            <button type="submit" class="btn btn-accent">Guardar cambios</button></div>
        </form>
      </div>
    </div>

    <!-- Confirmar borrado -->
    <div class="modal fade" id="modalDeleteGift" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered modal-sm">
        <form class="modal-content" method="POST" action="index.php">
          <?= csrf_input() ?>
          <input type="hidden" name="action" value="delete_item">
          <input type="hidden" name="id" id="d_id">
          <div class="modal-body text-center pt-4">
            <p class="mb-0" id="d_text">¿Eliminar este regalo?</p>
          </div>
          <div class="modal-footer justify-content-center border-0 pt-0">
            <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
            <button type="submit" class="btn btn-danger btn-sm">Eliminar</button>
          </div>
        </form>
      </div>
    </div>
    <script type="application/json" id="gift-data"><?= json_encode($gift_data, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE | JSON_FORCE_OBJECT) ?></script>
  <?php endif; ?>

  <?php include __DIR__ . '/../../frontend/footer.php'; ?>
</body>
</html>

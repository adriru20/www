<?php
session_start();
$src = '../../';

// Protección: Si no hay sesión, al login
if (!isset($_SESSION['user_id'])) {
  header("Location: {$src}src/login/");
  exit();
}

require $src . 'backend/config/db.php';
global $conn;

$my_id = $_SESSION['user_id'];
// Determinamos qué lista estamos viendo (la mía o la de otro)
$view_user_id = isset($_GET['view']) ? $_GET['view'] : $my_id;
$is_my_list = ($view_user_id === $my_id);

// --- LÓGICA: Añadir Regalo ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_item']) && $is_my_list) {
  $item_name = trim($_POST['item_name']);
  $item_desc = trim($_POST['item_description']);
  $item_url = trim($_POST['item_url']);

  if (!empty($item_name)) {
    $stmt = $conn->prepare("INSERT INTO gift_items (user_id, item_name, item_description, item_url) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("ssss", $my_id, $item_name, $item_desc, $item_url);
    $stmt->execute();
    $stmt->close();

    // Evitar reenvío de formulario al recargar
    header("Location: index.php");
    exit();
  }
}

// --- LÓGICA: Borrar Regalo ---
if (isset($_GET['delete']) && $is_my_list) {
  $item_id = $_GET['delete'];
  $stmt = $conn->prepare("DELETE FROM gift_items WHERE id = ? AND user_id = ?");
  $stmt->bind_param("is", $item_id, $my_id);
  $stmt->execute();
  $stmt->close();
  header("Location: index.php");
  exit();
}

// --- DATOS: Obtener info del usuario que estamos viendo ---
$u_stmt = $conn->prepare("SELECT user FROM login_user WHERE id = ?");
$u_stmt->bind_param("s", $view_user_id);
$u_stmt->execute();
$user_info = $u_stmt->get_result()->fetch_assoc();
$display_name = $user_info ? $user_info['user'] : 'Desconocido';

// --- DATOS: Obtener lista de regalos (Ordenado descendente por fecha) ---
$g_stmt = $conn->prepare("SELECT id, item_name, item_description, item_url, created_at FROM gift_items WHERE user_id = ? ORDER BY created_at DESC");
$g_stmt->bind_param("s", $view_user_id);
$g_stmt->execute();
$gifts = $g_stmt->get_result();

// --- DATOS: Obtener otros usuarios para el selector ---
$others = $conn->query("SELECT id, user FROM login_user WHERE id != '$my_id'");
?>

<!DOCTYPE html>
<html lang="es" data-bs-theme="dark">
<?php include "{$src}backend/config/ini.php"; ?>

<body>
  <?php include "{$src}frontend/menu.php"; ?>

  <main class="container my-6">
    <div class="row col-md-12">
      <div class="col-md-4 mb-4">
        <div class="card border-secondary">
          <div class="card-header">Ver otras listas</div>
          <div class="list-group list-group-flush">
            <a href="index.php" class="list-group-item list-group-item-action <?php echo $is_my_list ? 'active' : ''; ?>">
              Mi propia lista (<?php echo htmlspecialchars($_SESSION['username']); ?>)
            </a>
            <?php while ($row = $others->fetch_assoc()): ?>
              <a href="index.php?view=<?php echo urlencode($row['id']); ?>"
                class="list-group-item list-group-item-action <?php echo ($view_user_id == $row['id']) ? 'active' : ''; ?>">
                Lista de <?php echo htmlspecialchars($row['user']); ?>
              </a>
            <?php endwhile; ?>
          </div>
        </div>
      </div>

      <div class="col-md-8">
        <h2 class="mb-4">Regalos de: <span class="text-primary"><?php echo htmlspecialchars($display_name); ?></span></h2>

        <?php if ($is_my_list): ?>
          <div class="card mb-4 border-primary">
            <div class="card-body">
              <form action="index.php" method="POST">
                <div class="row g-3">
                  <div class="col-md-6">
                    <label class="form-label text-muted small mb-1">Nombre del regalo *</label>
                    <input type="text" name="item_name" class="form-control" placeholder="Item..." required>
                  </div>
                  <div class="col-md-6">
                    <label class="form-label text-muted small mb-1">Enlace (Opcional)</label>
                    <input type="url" name="item_url" class="form-control" placeholder="Enlace...">
                  </div>
                  <div class="col-12">
                    <label class="form-label text-muted small mb-1">Descripción / Notas (Opcional)</label>
                    <textarea name="item_description" class="form-control" rows="2" placeholder="Una descripción del item..."></textarea>
                  </div>
                  <div class="col-12 text-end mt-3">
                    <button type="submit" name="add_item" class="btn btn-primary">Añadir a mi lista</button>
                  </div>
                </div>
              </form>
            </div>
          </div>
        <?php endif; ?>

        <div class="card border-secondary shadow-sm">
          <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
              <thead class="table-dark">
                <tr>
                  <th>Fecha</th>
                  <th>Regalo</th>
                  <th>Descripción</th>
                  <th>Enlace</th>
                  <?php if ($is_my_list): ?><th class="text-center">Acción</th><?php endif; ?>
                </tr>
              </thead>
              <tbody>
                <?php if ($gifts->num_rows > 0): ?>
                  <?php while ($gift = $gifts->fetch_assoc()): ?>
                    <tr>
                      <td>
                        <small class="text-muted"><?php echo date('d-m-Y', strtotime($gift['created_at'])); ?><br><?php echo date('H:i', strtotime($gift['created_at'])); ?></small>
                      </td>

                      <td class="fw-bold"><?php echo htmlspecialchars($gift['item_name']); ?></td>

                      <td>
                        <small class="text-light">
                          <?php echo !empty($gift['item_description']) ? nl2br(htmlspecialchars($gift['item_description'])) : '<span class="text-muted">Sin detalles</span>'; ?>
                        </small>
                      </td>

                      <td>
                        <?php if (!empty($gift['item_url'])): ?>
                          <a href="<?php echo htmlspecialchars($gift['item_url']); ?>" target="_blank" class="btn btn-sm btn-info text-white fw-semibold">Ver más</a>
                        <?php else: ?>
                          <span class="text-muted small">-</span>
                        <?php endif; ?>
                      </td>

                      <?php if ($is_my_list): ?>
                        <td class="text-center">
                          <a href="index.php?delete=<?php echo $gift['id']; ?>"
                            class="btn btn-sm btn-outline-danger"
                            title="Eliminar"
                            onclick="return confirm('¿Seguro que quieres quitar esto de tu lista?')">❌</a>
                        </td>
                      <?php endif; ?>
                    </tr>
                  <?php endwhile; ?>
                <?php else: ?>
                  <tr>
                    <td colspan="<?php echo $is_my_list ? 5 : 4; ?>" class="text-center py-5 text-muted">
                      <em>No hay regalos en esta lista todavía.</em>
                    </td>
                  </tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </main>

  <?php include "{$src}frontend/footer.php"; ?>
</body>

</html>
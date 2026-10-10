<?php
// Administración de usuarios: datos y acciones. (Las vistas están en index.php.)

const ADMIN_USER_RE = '/^[\p{L}\p{N}._-]{3,50}$/u';

// ¿Existe ya la columna login_user.oculto? (la crea la migración 004; hasta entonces no se puede ocultar)
function admin_hidden_ready(): bool {
  static $ready = null;
  return $ready ??= (db_query('SELECT oculto FROM login_user LIMIT 1') !== false);
}

// ¿Existen ya las columnas es_amigo/es_familiar? (las crea la migración 005)
function admin_groups_ready(): bool {
  static $ready = null;
  return $ready ??= (db_query('SELECT es_amigo, es_familiar FROM login_user LIMIT 1') !== false);
}

// Guarda los grupos marcados en el formulario (solo si ya existen las columnas)
function admin_save_groups(string $userId): void {
  if (!admin_groups_ready()) return;
  db_exec('UPDATE login_user SET es_amigo = ?, es_familiar = ? WHERE id = ?', 'iis', [!empty($_POST['amigo']) ? 1 : 0, !empty($_POST['familiar']) ? 1 : 0, $userId]);
}

function admin_users(): array {
  $hid = admin_hidden_ready() ? 'oculto' : '0 AS oculto';
  $grp = admin_groups_ready() ? 'es_amigo, es_familiar' : '0 AS es_amigo, 0 AS es_familiar';
  $users = db_rows(db_query("SELECT id, user, permission, active, $hid, $grp, created_at, last_login FROM login_user ORDER BY user ASC"));
  $perms = [];
  foreach (db_rows(db_query('SELECT user_id, perm FROM user_permissions')) as $r) $perms[$r['user_id']][] = $r['perm'];
  foreach ($users as &$u) $u['perms'] = $perms[$u['id']] ?? [];
  return $users;
}

function admin_get(string $id): ?array {
  $r = db_query('SELECT id, user, permission, active FROM login_user WHERE id = ?', 's', [$id]);
  return $r ? ($r->fetch_assoc() ?: null) : null;
}

// Cuántos administradores activos hay sin contar a $exceptId
function admin_count_admins(string $exceptId): int {
  $r = db_query("SELECT COUNT(*) AS n FROM login_user WHERE permission = 'admin' AND active = 1 AND id != ?", 's', [$exceptId]);
  return (int) ($r ? ($r->fetch_assoc()['n'] ?? 0) : 0);
}

// Permisos marcados en el formulario, limpiados contra el registro y coherentes (sub sin sección = fuera)
function admin_clean_perms($posted): array {
  $valid = all_perm_keys();
  $out = [];
  foreach ((array) $posted as $k) if (is_string($k) && in_array($k, $valid, true)) $out[$k] = true;
  foreach (array_keys($out) as $k) if (str_contains($k, '.') && !isset($out[explode('.', $k)[0]])) unset($out[$k]);
  return array_keys($out);
}

function admin_save_perms(string $userId, array $perms): void {
  db_exec('DELETE FROM user_permissions WHERE user_id = ?', 's', [$userId]);
  foreach ($perms as $p) db_exec('INSERT INTO user_permissions (user_id, perm) VALUES (?, ?)', 'ss', [$userId, $p]);
}

function admin_valid_role(string $r): bool { return isset(ROLES[$r]); }

function admin_handle_actions(string $me): void {
  if ($_SERVER['REQUEST_METHOD'] !== 'POST') return;
  csrf_verify_post();
  $id = (string) ($_POST['id'] ?? '');

  switch ($_POST['action'] ?? '') {

    case 'create_user':
      $name = trim((string) ($_POST['user'] ?? ''));
      $pass = (string) ($_POST['pass'] ?? '');
      $role = (string) ($_POST['role'] ?? 'user');
      if (!preg_match(ADMIN_USER_RE, $name)) { flash_add('El nombre de usuario debe tener entre 3 y 50 caracteres (letras, números, punto, guion o guion bajo).', 'danger'); break; }
      if (mb_strlen($pass) < 8) { flash_add('La contraseña debe tener al menos 8 caracteres.', 'danger'); break; }
      if (!admin_valid_role($role)) { flash_add('Rol no válido.', 'danger'); break; }
      $dup = db_query('SELECT id FROM login_user WHERE LOWER(user) = LOWER(?)', 's', [$name]);
      if ($dup && $dup->fetch_assoc()) { flash_add("Ya existe un usuario llamado «{$name}».", 'danger'); break; }
      $newId = uniqid('usr_', true);
      $r = db_exec('INSERT INTO login_user (id, user, pass, permission) VALUES (?, ?, ?, ?)', 'ssss', [$newId, $name, password_hash($pass, PASSWORD_DEFAULT), $role]);
      if (!$r['ok']) { flash_add('No se pudo crear el usuario.', 'danger'); break; }
      if ($role !== 'admin') admin_save_perms($newId, admin_clean_perms($_POST['perms'] ?? []));
      admin_save_groups($newId);
      flash_add("Usuario «{$name}» creado.");
      break;

    case 'update_user':
      $u = admin_get($id);
      if (!$u) { flash_add('Ese usuario no existe.', 'warning'); break; }
      $role = (string) ($_POST['role'] ?? $u['permission']);
      if (!admin_valid_role($role)) { flash_add('Rol no válido.', 'danger'); break; }
      if ($id === $me && $role !== 'admin') { flash_add('No puedes quitarte a ti mismo el rol de administrador.', 'warning'); break; }
      if ($u['permission'] === 'admin' && $role !== 'admin' && admin_count_admins($id) < 1) { flash_add('Tiene que quedar al menos un administrador.', 'warning'); break; }
      db_exec('UPDATE login_user SET permission = ? WHERE id = ?', 'ss', [$role, $id]);
      admin_save_perms($id, $role === 'admin' ? [] : admin_clean_perms($_POST['perms'] ?? []));
      admin_save_groups($id);
      flash_add("Permisos de «{$u['user']}» actualizados.");
      break;

    case 'reset_password':
      $u = admin_get($id);
      $pass = (string) ($_POST['pass'] ?? '');
      if (!$u) { flash_add('Ese usuario no existe.', 'warning'); break; }
      if (mb_strlen($pass) < 8) { flash_add('La contraseña debe tener al menos 8 caracteres.', 'danger'); break; }
      $newHash = password_hash($pass, PASSWORD_DEFAULT);
      db_exec('UPDATE login_user SET pass = ? WHERE id = ?', 'ss', [$newHash, $id]);
      if ($id === $me) $_SESSION['pv'] = pass_stamp($newHash);   // si es la propia, esta sesión sigue abierta
      flash_add("Contraseña de «{$u['user']}» cambiada" . ($id === $me ? '.' : ': sus sesiones abiertas se cerrarán. Díselo por un canal seguro.'));
      break;

    case 'set_active':
      $u = admin_get($id);
      $active = !empty($_POST['active']) ? 1 : 0;
      if (!$u) { flash_add('Ese usuario no existe.', 'warning'); break; }
      if ($id === $me) { flash_add('No puedes desactivar tu propia cuenta.', 'warning'); break; }
      if (!$active && $u['permission'] === 'admin' && admin_count_admins($id) < 1) { flash_add('Tiene que quedar al menos un administrador activo.', 'warning'); break; }
      db_exec('UPDATE login_user SET active = ? WHERE id = ?', 'is', [$active, $id]);
      flash_add("Cuenta de «{$u['user']}» " . ($active ? 'activada.' : 'desactivada: ya no puede entrar.'), $active ? 'success' : 'info');
      break;

    // Ocultar / mostrar un usuario en las listas de la web (Gift list). Sigue pudiendo entrar y se gestiona desde la pestaña Ocultos.
    case 'set_hidden':
      $u = admin_get($id);
      $hide = !empty($_POST['hidden']) ? 1 : 0;
      if (!admin_hidden_ready()) { flash_add('Falta ejecutar la migración 004 en la base de datos para poder ocultar usuarios.', 'warning'); break; }
      if (!$u) { flash_add('Ese usuario no existe.', 'warning'); break; }
      if ($id === $me) { flash_add('No puedes ocultar tu propia cuenta.', 'warning'); break; }
      db_exec('UPDATE login_user SET oculto = ? WHERE id = ?', 'is', [$hide, $id]);
      flash_add($hide ? "«{$u['user']}» ahora está oculto: no sale en las listas, pero puede entrar. Lo encontrarás en la pestaña Ocultos." : "«{$u['user']}» vuelve a mostrarse.", 'info');
      break;

    case 'delete_user':
      $u = admin_get($id);
      if (!$u) { flash_add('Ese usuario ya no existe.', 'warning'); break; }
      if ($id === $me) { flash_add('No puedes borrar tu propia cuenta.', 'warning'); break; }
      if ($u['permission'] === 'admin' && admin_count_admins($id) < 1) { flash_add('Tiene que quedar al menos un administrador.', 'warning'); break; }
      // Sus datos propios (permisos, regalos, sitios de parking) se borran con él
      db_exec('DELETE FROM user_permissions WHERE user_id = ?', 's', [$id]);
      db_exec('UPDATE gift_items SET purchased_by = NULL, purchased_at = NULL WHERE purchased_by = ?', 's', [$id]); // (si aún no existe la columna, no pasa nada)
      db_exec('DELETE FROM gift_items WHERE user_id = ?', 's', [$id]);
      db_exec('DELETE FROM parking_spots WHERE user_id = ?', 's', [$id]);
      db_exec('DELETE FROM login_user WHERE id = ?', 's', [$id]);
      flash_add("Usuario «{$u['user']}» eliminado con sus datos.", 'info');
      break;

    default:
      flash_add('Acción no reconocida.', 'danger');
  }
  // Se vuelve a la pestaña en la que estaba el administrador (los formularios envían a la URL actual)
  $back = (string) ($_GET['ver'] ?? '');
  header('Location: index.php' . (in_array($back, ['inactivos', 'ocultos'], true) ? '?ver=' . $back : ''));
  exit();
}

<?php
// Gift list: datos y acciones. (Las vistas están en index.php.)

const GIFT_NAME_MAX = 255;
const GIFT_DESC_MAX = 2000;
const GIFT_URL_MAX  = 2048;

function gift_user(string $id): ?array {
  $r = db_query('SELECT id, user FROM login_user WHERE id = ?', 's', [$id]);
  return $r ? ($r->fetch_assoc() ?: null) : null;
}

// Resto de usuarios con su número de regalos
function gift_people(string $myId): array {
  $counts = [];
  foreach (db_rows(db_query('SELECT user_id, COUNT(*) AS n FROM gift_items GROUP BY user_id')) as $r) $counts[$r['user_id']] = (int) $r['n'];
  $people = db_rows(db_query('SELECT id, user FROM login_user WHERE id != ? ORDER BY user ASC', 's', [$myId]));
  foreach ($people as &$p) $p['n'] = $counts[$p['id']] ?? 0;
  return ['people' => $people, 'mine' => $counts[$myId] ?? 0];
}

// ¿Existen ya las columnas de "comprado"? (las crea el SQL 003). Si no, la función queda oculta.
function gift_purchase_ready(): bool {
  static $ready = null;
  return $ready ??= (db_query('SELECT purchased_by FROM gift_items LIMIT 1') !== false);
}

// Regalos de una persona. Con $withPurchases (solo al ver la lista de OTRA persona) incluye quién lo compró y cuándo.
// La lista propia nunca se pide con compras: el dueño no debe saber qué le han comprado.
function gift_list(string $userId, bool $withPurchases = false): array {
  if ($withPurchases && gift_purchase_ready()) {
    return db_rows(db_query('SELECT g.id, g.item_name, g.item_description, g.item_url, g.created_at, g.purchased_by, g.purchased_at, u.user AS buyer
                             FROM gift_items g LEFT JOIN login_user u ON u.id = g.purchased_by
                             WHERE g.user_id = ?
                             ORDER BY (g.purchased_by IS NOT NULL) ASC, g.created_at DESC, g.id DESC', 's', [$userId]));
  }
  return db_rows(db_query('SELECT id, item_name, item_description, item_url, created_at FROM gift_items WHERE user_id = ? ORDER BY created_at DESC, id DESC', 's', [$userId]));
}

// Solo enlaces http(s): evita esquemas como javascript:
function gift_clean_url(string $url): ?string {
  $url = trim($url);
  if ($url === '') return '';
  if (strlen($url) > GIFT_URL_MAX || !preg_match('#^https?://[^\s]+$#i', $url)) return null;
  return $url;
}

// Valida el formulario. Devuelve [datos, error]
function gift_validate(array $in): array {
  $name = trim((string) ($in['item_name'] ?? ''));
  $desc = trim((string) ($in['item_description'] ?? ''));
  if ($name === '') return [null, 'El nombre del regalo es obligatorio.'];
  if (mb_strlen($name) > GIFT_NAME_MAX) return [null, 'El nombre es demasiado largo (máx. ' . GIFT_NAME_MAX . ' caracteres).'];
  if (mb_strlen($desc) > GIFT_DESC_MAX) return [null, 'Las notas son demasiado largas (máx. ' . GIFT_DESC_MAX . ' caracteres).'];
  $url = gift_clean_url((string) ($in['item_url'] ?? ''));
  if ($url === null) return [null, 'El enlace debe empezar por http:// o https://.'];
  return [['name' => $name, 'desc' => $desc, 'url' => $url], null];
}

// Acciones de escritura (solo sobre MI lista). Termina siempre en redirección.
function gift_handle_actions(string $myId): void {
  if ($_SERVER['REQUEST_METHOD'] !== 'POST') return;
  csrf_verify_post();
  $id = (int) ($_POST['id'] ?? 0);
  $redirect = 'index.php';

  switch ($_POST['action'] ?? '') {
    case 'add_item':
      [$d, $err] = gift_validate($_POST);
      if ($err) { flash_add($err, 'danger'); break; }
      $r = db_exec('INSERT INTO gift_items (user_id, item_name, item_description, item_url) VALUES (?, ?, ?, ?)', 'ssss', [$myId, $d['name'], $d['desc'], $d['url']]);
      $r['ok'] ? flash_add('«' . $d['name'] . '» añadido a tu lista.') : flash_add('No se pudo guardar el regalo.', 'danger');
      break;

    case 'edit_item':
      [$d, $err] = gift_validate($_POST);
      if ($err) { flash_add($err, 'danger'); break; }
      $own = db_query('SELECT id FROM gift_items WHERE id = ? AND user_id = ?', 'is', [$id, $myId]);
      if (!$own || !$own->fetch_assoc()) { flash_add('Ese regalo no existe o no es tuyo.', 'warning'); break; }
      $r = db_exec('UPDATE gift_items SET item_name = ?, item_description = ?, item_url = ? WHERE id = ? AND user_id = ?', 'sssis', [$d['name'], $d['desc'], $d['url'], $id, $myId]);
      $r['ok'] ? flash_add('«' . $d['name'] . '» actualizado.') : flash_add('No se pudo actualizar el regalo.', 'danger');
      break;

    case 'delete_item':
      $row = db_query('SELECT item_name FROM gift_items WHERE id = ? AND user_id = ?', 'is', [$id, $myId]);
      $row = $row ? $row->fetch_assoc() : null;
      if (!$row) { flash_add('Ese regalo ya no existe.', 'warning'); break; }
      db_exec('DELETE FROM gift_items WHERE id = ? AND user_id = ?', 'is', [$id, $myId]);
      flash_add('«' . $row['item_name'] . '» eliminado de tu lista.', 'info');
      break;

    // ---- Marcar / desmarcar "lo he comprado" (solo en listas de otras personas)
    case 'mark_bought':
    case 'unmark_bought':
      $g = gift_purchase_ready() ? db_query('SELECT g.item_name, g.user_id, g.purchased_by, o.user AS owner, b.user AS buyer
                                             FROM gift_items g JOIN login_user o ON o.id = g.user_id LEFT JOIN login_user b ON b.id = g.purchased_by
                                             WHERE g.id = ?', 'i', [$id]) : false;
      $g = $g ? $g->fetch_assoc() : null;
      if (!$g) { flash_add('Ese regalo ya no existe o la función aún no está activada.', 'warning'); break; }
      $redirect = 'index.php?view=' . urlencode($g['user_id']);

      if ($_POST['action'] === 'mark_bought') {
        if ($g['user_id'] === $myId) { flash_add('No puedes marcar tus propios regalos: ¡tiene que ser sorpresa!', 'warning'); $redirect = 'index.php'; break; }
        // Una sola persona puede quedarse el regalo (la condición evita que dos lo marquen a la vez)
        $r = db_exec('UPDATE gift_items SET purchased_by = ?, purchased_at = NOW() WHERE id = ? AND purchased_by IS NULL AND user_id != ?', 'sis', [$myId, $id, $myId]);
        if ($r['affected'] > 0) flash_add('Marcado como comprado: «' . $g['item_name'] . '» para ' . $g['owner'] . '.');
        else flash_add('Ese regalo ya lo había marcado ' . ($g['buyer'] ?? 'otra persona') . '.', 'warning');
      } else {
        if ($g['purchased_by'] === null) { flash_add('Ese regalo no estaba marcado como comprado.', 'info'); break; }
        if ($g['purchased_by'] !== $myId && !is_admin()) { flash_add('Solo quien lo marcó (' . ($g['buyer'] ?? 'otra persona') . ') puede desmarcarlo.', 'warning'); break; }
        db_exec('UPDATE gift_items SET purchased_by = NULL, purchased_at = NULL WHERE id = ?', 'i', [$id]);
        flash_add('Desmarcado: «' . $g['item_name'] . '» vuelve a estar pendiente.', 'info');
      }
      break;

    default:
      flash_add('Acción no reconocida.', 'danger');
  }
  header('Location: ' . $redirect);
  exit();
}

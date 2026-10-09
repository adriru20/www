<?php
// Helpers compartidos de vistas y base de datos (las apps nuevas usan estos nombres).
if (!function_exists('e')) {

  // Escapa para HTML
  function e($v): string {
    return htmlspecialchars((string) ($v ?? ''), ENT_QUOTES, 'UTF-8');
  }

  // SELECT con consulta preparada. Devuelve mysqli_result|false
  function db_query(string $sql, string $types = '', array $params = []) {
    global $conn;
    $stmt = $conn->prepare($sql);
    if (!$stmt) return false;
    if ($types !== '') $stmt->bind_param($types, ...$params);
    if (!$stmt->execute()) return false;
    return $stmt->get_result();
  }

  // INSERT/UPDATE/DELETE. Devuelve ['ok','affected','insert_id','error']
  function db_exec(string $sql, string $types = '', array $params = []): array {
    global $conn;
    $stmt = $conn->prepare($sql);
    if (!$stmt) return ['ok' => false, 'affected' => 0, 'insert_id' => 0, 'error' => (string) $conn->error];
    if ($types !== '') $stmt->bind_param($types, ...$params);
    $ok = $stmt->execute();
    $res = ['ok' => (bool) $ok, 'affected' => (int) $stmt->affected_rows, 'insert_id' => (int) $stmt->insert_id, 'error' => (string) $stmt->error];
    $stmt->close();
    return $res;
  }

  function db_rows($result): array {
    $rows = [];
    while ($result && ($r = $result->fetch_assoc())) $rows[] = $r;
    return $rows;
  }

  // Avisos que se muestran una vez tras redirigir (Bootstrap alerts)
  function flash_add(string $msg, string $type = 'success'): void {
    $_SESSION['flash'][] = ['msg' => $msg, 'type' => $type];
  }
  function flash_render(): void {
    $items = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    foreach ($items as $a) {
      echo '<div class="alert alert-' . e($a['type']) . ' alert-dismissible fade show" role="alert">' . e($a['msg'])
         . '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button></div>';
    }
  }
}

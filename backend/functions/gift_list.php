<?php
// ──────────────────────────────────────────────────────────────
//  GiftList · Funciones de aplicación
//  Requiere: $conn (mysqli) ya cargado desde db.php
// ──────────────────────────────────────────────────────────────

// ── Sesión segura ─────────────────────────────────────────────
function gl_session(): void {
    if (session_status() === PHP_SESSION_NONE) {
        ini_set('session.cookie_httponly', 1);
        ini_set('session.use_strict_mode', 1);
        ini_set('session.cookie_samesite', 'Strict');
        session_start();
    }
}

function gl_login(array $user): void {
    session_regenerate_id(true);
    $_SESSION['gl_id']   = $user['id'];
    $_SESSION['gl_name'] = $user['name'];
    $_SESSION['gl_role'] = $user['role'];
}

function gl_logout(): void {
    gl_session();
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        setcookie(session_name(), '', time() - 42000,
            ini_get('session.cookie_path'), ini_get('session.cookie_domain'),
            ini_get('session.cookie_secure'), ini_get('session.cookie_httponly')
        );
    }
    session_destroy();
}

function gl_auth(string $role = 'user'): void {
    gl_session();
    if (empty($_SESSION['gl_id'])) {
        header('Location: index.php'); exit;
    }
    if ($role === 'admin' && ($_SESSION['gl_role'] ?? '') !== 'admin') {
        header('Location: dashboard.php'); exit;
    }
}

function gl_user(): array {
    return [
        'id'   => (int)($_SESSION['gl_id']   ?? 0),
        'name' => $_SESSION['gl_name'] ?? '',
        'role' => $_SESSION['gl_role'] ?? 'user',
    ];
}

function gl_is_admin(): bool {
    return ($_SESSION['gl_role'] ?? '') === 'admin';
}

// ── CSRF ──────────────────────────────────────────────────────
function gl_csrf_token(): string {
    gl_session();
    if (empty($_SESSION['gl_csrf'])) {
        $_SESSION['gl_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['gl_csrf'];
}

function gl_csrf_input(): string {
    return '<input type="hidden" name="gl_csrf" value="' . htmlspecialchars(gl_csrf_token(), ENT_QUOTES, 'UTF-8') . '">';
}

function gl_csrf_verify(): void {
    $token = $_POST['gl_csrf'] ?? '';
    if (!hash_equals(gl_csrf_token(), $token)) {
        http_response_code(403); die('Token CSRF inválido.');
    }
}

// ── Flash messages ────────────────────────────────────────────
function gl_flash(string $msg, string $type = 'success'): void {
    gl_session();
    $_SESSION['gl_flash'] = ['msg' => $msg, 'type' => $type];
}

function gl_get_flash(): ?array {
    gl_session();
    $f = $_SESSION['gl_flash'] ?? null;
    unset($_SESSION['gl_flash']);
    return $f;
}

// ── Helpers ───────────────────────────────────────────────────
function gl_h(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function gl_price(?float $p): string {
    return $p !== null ? number_format($p, 2, ',', '.') . ' €' : '—';
}

// ── Base de datos · Usuarios (gl_users) ───────────────────────
function gl_get_all_users(): array {
    global $conn;
    $result = $conn->query(
        'SELECT u.*, COUNT(g.id) AS gift_count
         FROM gl_users u
         LEFT JOIN gl_gifts g ON g.user_id = u.id
         GROUP BY u.id
         ORDER BY u.created_at DESC'
    );
    return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
}

function gl_get_user(int $id): ?array {
    global $conn;
    $st = $conn->prepare('SELECT * FROM gl_users WHERE id = ?');
    $st->bind_param('i', $id);
    $st->execute();
    $row = $st->get_result()->fetch_assoc();
    $st->close();
    return $row ?: null;
}

function gl_get_user_by_email(string $email): ?array {
    global $conn;
    $st = $conn->prepare('SELECT * FROM gl_users WHERE email = ?');
    $st->bind_param('s', $email);
    $st->execute();
    $row = $st->get_result()->fetch_assoc();
    $st->close();
    return $row ?: null;
}

function gl_authenticate(string $email, string $pass): ?array {
    $user = gl_get_user_by_email($email);
    if ($user && password_verify($pass, $user['password'])) return $user;
    return null;
}

function gl_create_user(string $name, string $email, string $pass, string $role = 'user'): bool|string {
    global $conn;
    if (gl_get_user_by_email($email)) return 'El correo ya está registrado.';
    $hash = password_hash($pass, PASSWORD_BCRYPT, ['cost' => 12]);
    $st = $conn->prepare('INSERT INTO gl_users (name, email, password, role) VALUES (?, ?, ?, ?)');
    $st->bind_param('ssss', $name, $email, $hash, $role);
    $ok = $st->execute();
    $st->close();
    return $ok ?: $conn->error;
}

function gl_update_user(int $id, string $name, string $email, string $role, string $pass = ''): bool|string {
    global $conn;
    $ex = gl_get_user_by_email($email);
    if ($ex && (int)$ex['id'] !== $id) return 'El correo ya está en uso.';

    if ($pass !== '') {
        $hash = password_hash($pass, PASSWORD_BCRYPT, ['cost' => 12]);
        $st = $conn->prepare('UPDATE gl_users SET name=?, email=?, role=?, password=? WHERE id=?');
        $st->bind_param('ssssi', $name, $email, $role, $hash, $id);
    } else {
        $st = $conn->prepare('UPDATE gl_users SET name=?, email=?, role=? WHERE id=?');
        $st->bind_param('sssi', $name, $email, $role, $id);
    }
    $ok = $st->execute(); $st->close();
    return $ok ?: $conn->error;
}

function gl_delete_user(int $id): bool {
    global $conn;
    $st = $conn->prepare('DELETE FROM gl_users WHERE id = ?');
    $st->bind_param('i', $id);
    $ok = $st->execute(); $st->close();
    return $ok;
}

// ── Base de datos · Regalos (gl_gifts) ───────────────────────
function gl_get_gifts(int $user_id): array {
    global $conn;
    $st = $conn->prepare(
        'SELECT g.*, u.name AS reserved_by_name
         FROM gl_gifts g
         LEFT JOIN gl_users u ON u.id = g.reserved_by
         WHERE g.user_id = ?
         ORDER BY g.priority_order ASC, g.created_at ASC'
    );
    $st->bind_param('i', $user_id);
    $st->execute();
    $rows = $st->get_result()->fetch_all(MYSQLI_ASSOC);
    $st->close();
    return $rows;
}

function gl_get_gift(int $id): ?array {
    global $conn;
    $st = $conn->prepare('SELECT * FROM gl_gifts WHERE id = ?');
    $st->bind_param('i', $id);
    $st->execute();
    $row = $st->get_result()->fetch_assoc();
    $st->close();
    return $row ?: null;
}

function gl_create_gift(int $user_id, string $name, string $desc, string $url, ?float $price, string $priority): bool {
    global $conn;
    $order = ['high' => 1, 'medium' => 2, 'low' => 3][$priority] ?? 2;
    $url   = $url ?: null;
    $st = $conn->prepare(
        'INSERT INTO gl_gifts (user_id, name, description, url, price, priority, priority_order)
         VALUES (?, ?, ?, ?, ?, ?, ?)'
    );
    $st->bind_param('isssdsi', $user_id, $name, $desc, $url, $price, $priority, $order);
    $ok = $st->execute(); $st->close();
    return $ok;
}

function gl_update_gift(int $id, string $name, string $desc, string $url, ?float $price, string $priority): bool {
    global $conn;
    $order = ['high' => 1, 'medium' => 2, 'low' => 3][$priority] ?? 2;
    $url   = $url ?: null;
    $st = $conn->prepare(
        'UPDATE gl_gifts SET name=?, description=?, url=?, price=?, priority=?, priority_order=? WHERE id=?'
    );
    $st->bind_param('sssdsii', $name, $desc, $url, $price, $priority, $order, $id);
    $ok = $st->execute(); $st->close();
    return $ok;
}

function gl_delete_gift(int $id): bool {
    global $conn;
    $st = $conn->prepare('DELETE FROM gl_gifts WHERE id = ?');
    $st->bind_param('i', $id);
    $ok = $st->execute(); $st->close();
    return $ok;
}

function gl_toggle_reserve(int $gift_id, int $user_id): void {
    global $conn;
    $gift = gl_get_gift($gift_id);
    if (!$gift) return;
    if ($gift['reserved_by'] === null) {
        $st = $conn->prepare('UPDATE gl_gifts SET reserved_by=? WHERE id=?');
        $st->bind_param('ii', $user_id, $gift_id);
    } else {
        $st = $conn->prepare('UPDATE gl_gifts SET reserved_by=NULL WHERE id=?');
        $st->bind_param('i', $gift_id);
    }
    $st->execute(); $st->close();
}

function gl_get_stats(): array {
    global $conn;
    $r = $conn->query(
        'SELECT
          (SELECT COUNT(*) FROM gl_users WHERE role="user")  AS total_users,
          (SELECT COUNT(*) FROM gl_users WHERE role="admin") AS total_admins,
          (SELECT COUNT(*) FROM gl_gifts)                    AS total_gifts,
          (SELECT COUNT(*) FROM gl_gifts WHERE reserved_by IS NOT NULL) AS reserved'
    );
    return $r ? $r->fetch_assoc() : [];
}

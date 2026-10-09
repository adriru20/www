<?php
// Sincronización de la wiki con OneDrive PERSONAL (Microsoft Graph, solo lectura).
//
// Cómo funciona:
//  - Una vez, el administrador conecta su cuenta (src/onedrive/): Microsoft devuelve un «refresh token» que se guarda en
//    backend/onedrive/token.json (carpeta protegida, fuera de Git). Con él el servidor pide permisos de corta duración.
//  - Se descargan SOLO las notas .md de la carpeta indicada en la configuración a apps/wiki/vault/ (la wiki sigue leyendo de ahí).
//  - La sincronización es incremental (API «delta»): solo se piden los cambios desde la última vez.
//  - Cada ejecución tiene un tiempo máximo; si no da tiempo a bajarlo todo, continúa en la siguiente llamada.
//  - Nunca se escribe ni se borra nada fuera de apps/wiki/vault/.
//
// Configuración: backend/config/onedrive.local.php (ver onedrive.example.php). Sin ella, la wiki funciona como siempre.

const OD_DIR   = __DIR__ . '/../onedrive';
const OD_VAULT = __DIR__ . '/../../apps/wiki/vault';
const OD_MAX_NOTE_BYTES = 5 * 1024 * 1024;

// ---------------------------------------------------------------- Configuración y ficheros

function od_config(): ?array {
  static $cfg = false;
  if ($cfg !== false) return $cfg;
  $file = __DIR__ . '/../config/onedrive.local.php';
  $c = is_file($file) ? (include $file) : null;
  foreach (['client_id', 'client_secret', 'redirect_uri', 'folder'] as $k) {
    if (!is_array($c) || trim((string) ($c[$k] ?? '')) === '') return $cfg = null;
  }
  $c['folder'] = trim((string) $c['folder'], "/ \t");
  $c['tenant'] = (string) ($c['tenant'] ?? 'consumers');            // «consumers» = cuentas personales de Microsoft
  $c['refresh_minutes'] = max(1, (int) ($c['refresh_minutes'] ?? 10));
  $c['graph_base'] = rtrim((string) ($c['graph_base'] ?? 'https://graph.microsoft.com/v1.0'), '/');   // (solo para pruebas)
  $c['login_base'] = rtrim((string) ($c['login_base'] ?? 'https://login.microsoftonline.com'), '/');  // (solo para pruebas)
  return $cfg = $c;
}

function od_dir(): string {
  if (!is_dir(OD_DIR)) @mkdir(OD_DIR, 0700, true);
  return OD_DIR;
}

function od_read_json(string $name): array {
  $f = od_dir() . '/' . $name;
  $d = is_file($f) ? json_decode((string) @file_get_contents($f), true) : null;
  return is_array($d) ? $d : [];
}

// Escritura atómica (fichero temporal + rename) y solo legible por el servidor
function od_write_json(string $name, array $data): bool {
  $f = od_dir() . '/' . $name;
  $tmp = $f . '.' . bin2hex(random_bytes(4)) . '.tmp';
  if (@file_put_contents($tmp, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) === false) return false;
  @chmod($tmp, 0600);
  return @rename($tmp, $f);
}

// ---------------------------------------------------------------- HTTP

// Devuelve ['code','body','json','headers'(en minúsculas),'error']
function od_http(string $method, string $url, array $opt = []): array {
  $out = ['code' => 0, 'body' => '', 'json' => null, 'headers' => [], 'error' => ''];
  if (!function_exists('curl_init')) { $out['error'] = 'Este servidor no tiene la extensión cURL de PHP.'; return $out; }
  $h = curl_init($url);
  $headers = $opt['headers'] ?? [];
  $body = null;
  if (isset($opt['form'])) { $body = http_build_query($opt['form']); $headers[] = 'Content-Type: application/x-www-form-urlencoded'; }
  curl_setopt_array($h, [
    CURLOPT_CUSTOMREQUEST  => $method,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => false,               // las redirecciones se siguen a mano (para no enviar el token a otro sitio)
    CURLOPT_CONNECTTIMEOUT => 8,
    CURLOPT_TIMEOUT        => (int) ($opt['timeout'] ?? 15),
    CURLOPT_HTTPHEADER     => $headers,
    CURLOPT_HEADERFUNCTION => function ($ch, $line) use (&$out) {
      $p = explode(':', $line, 2);
      if (count($p) === 2) $out['headers'][strtolower(trim($p[0]))] = trim($p[1]);
      return strlen($line);
    },
  ]);
  if ($body !== null) curl_setopt($h, CURLOPT_POSTFIELDS, $body);
  $res = curl_exec($h);
  if ($res === false) $out['error'] = curl_error($h);
  $out['code'] = (int) curl_getinfo($h, CURLINFO_RESPONSE_CODE);
  curl_close($h);
  $out['body'] = is_string($res) ? $res : '';
  $j = json_decode($out['body'], true);
  $out['json'] = is_array($j) ? $j : null;
  return $out;
}

// ---------------------------------------------------------------- OAuth (código de autorización + PKCE)

function od_b64url(string $bin): string { return rtrim(strtr(base64_encode($bin), '+/', '-_'), '='); }

function od_scopes(): string { return 'offline_access Files.Read'; }

// Prepara el inicio de sesión: guarda state y verifier en la sesión y devuelve la URL de Microsoft
function od_authorize_url(): ?string {
  $c = od_config(); if (!$c) return null;
  $verifier = od_b64url(random_bytes(48));
  $state = od_b64url(random_bytes(24));
  $_SESSION['od_oauth'] = ['state' => $state, 'verifier' => $verifier, 't' => time()];
  return $c['login_base'] . '/' . rawurlencode($c['tenant']) . '/oauth2/v2.0/authorize?' . http_build_query([
    'client_id' => $c['client_id'], 'response_type' => 'code', 'redirect_uri' => $c['redirect_uri'],
    'response_mode' => 'query', 'scope' => od_scopes(), 'state' => $state,
    'code_challenge' => od_b64url(hash('sha256', $verifier, true)), 'code_challenge_method' => 'S256',
    'prompt' => 'select_account',
  ]);
}

// Pide tokens al endpoint de Microsoft. Devuelve ['ok'=>bool,'data'=>array,'error'=>string,'reconnect'=>bool]
function od_token_request(array $form): array {
  $c = od_config();
  $r = od_http('POST', $c['login_base'] . '/' . rawurlencode($c['tenant']) . '/oauth2/v2.0/token', ['form' => $form + [
    'client_id' => $c['client_id'], 'client_secret' => $c['client_secret'], 'scope' => od_scopes(),
  ]]);
  if ($r['error'] !== '') return ['ok' => false, 'data' => [], 'error' => 'No se pudo contactar con Microsoft: ' . $r['error'], 'reconnect' => false];
  $j = $r['json'] ?? [];
  if ($r['code'] === 200 && !empty($j['access_token'])) return ['ok' => true, 'data' => $j, 'error' => '', 'reconnect' => false];
  $err = (string) ($j['error'] ?? ('HTTP ' . $r['code']));
  $desc = trim(explode("\r", (string) ($j['error_description'] ?? ''))[0]);
  return ['ok' => false, 'data' => [], 'error' => 'Microsoft rechazó la petición (' . $err . ($desc !== '' ? ': ' . $desc : '') . ').',
          'reconnect' => in_array($err, ['invalid_grant', 'interaction_required', 'invalid_client', 'unauthorized_client'], true)];
}

function od_save_tokens(array $t, array $prev = []): void {
  od_write_json('token.json', [
    'refresh_token' => (string) ($t['refresh_token'] ?? $prev['refresh_token'] ?? ''),   // Microsoft puede rotarlo: se guarda el nuevo
    'access_token'  => (string) $t['access_token'],
    'expires_at'    => time() + (int) ($t['expires_in'] ?? 3600),
    'connected_at'  => (int) ($prev['connected_at'] ?? time()),
  ]);
}

// Callback de Microsoft: intercambia el código por los tokens
function od_finish_connect(string $code, string $state): array {
  $o = $_SESSION['od_oauth'] ?? null;
  unset($_SESSION['od_oauth']);
  if (!$o || !hash_equals((string) $o['state'], $state) || time() - (int) $o['t'] > 900) return ['ok' => false, 'error' => 'La petición de conexión no es válida o ha caducado. Vuelve a pulsar «Conectar».'];
  $r = od_token_request(['grant_type' => 'authorization_code', 'code' => $code, 'redirect_uri' => od_config()['redirect_uri'], 'code_verifier' => $o['verifier']]);
  if (!$r['ok']) return ['ok' => false, 'error' => $r['error']];
  if (empty($r['data']['refresh_token'])) return ['ok' => false, 'error' => 'Microsoft no devolvió permiso de larga duración (falta «offline_access»).'];
  od_save_tokens($r['data']);
  return ['ok' => true, 'error' => ''];
}

function od_is_connected(): bool { return (string) (od_read_json('token.json')['refresh_token'] ?? '') !== ''; }

function od_disconnect(): void {
  @unlink(od_dir() . '/token.json');
  $s = od_read_json('state.json');
  unset($s['cursor']); $s['phase'] = 'meta'; $s['items'] = [];
  od_write_json('state.json', $s);
}

// Token de acceso vigente (renovándolo si hace falta). ['ok'=>bool,'token'=>string,'error'=>string,'reconnect'=>bool]
function od_access_token(): array {
  $lock = fopen(od_dir() . '/token.lock', 'c');
  if ($lock) flock($lock, LOCK_EX);
  try {
    $t = od_read_json('token.json');
    if (empty($t['refresh_token'])) return ['ok' => false, 'token' => '', 'error' => 'OneDrive no está conectado.', 'reconnect' => true];
    if (!empty($t['access_token']) && (int) ($t['expires_at'] ?? 0) > time() + 90) return ['ok' => true, 'token' => $t['access_token'], 'error' => '', 'reconnect' => false];
    $r = od_token_request(['grant_type' => 'refresh_token', 'refresh_token' => $t['refresh_token']]);
    if (!$r['ok']) return ['ok' => false, 'token' => '', 'error' => $r['error'], 'reconnect' => $r['reconnect']];
    od_save_tokens($r['data'], $t);
    return ['ok' => true, 'token' => $r['data']['access_token'], 'error' => '', 'reconnect' => false];
  } finally {
    if ($lock) { flock($lock, LOCK_UN); fclose($lock); }
  }
}

// GET a Graph (ruta relativa o URL completa de nextLink/deltaLink). Devuelve el resultado de od_http() + 'auth_error'
function od_graph(string $pathOrUrl, array $extraHeaders = [], bool $retry = false): array {
  $c = od_config();
  $tok = od_access_token();
  if (!$tok['ok']) return ['code' => 0, 'body' => '', 'json' => null, 'headers' => [], 'error' => $tok['error'], 'reconnect' => $tok['reconnect']];
  $url = preg_match('#^https?://#i', $pathOrUrl) ? $pathOrUrl : $c['graph_base'] . $pathOrUrl;
  // Seguridad: el token solo se envía a Graph, nunca a otro servidor que aparezca en una respuesta
  if (strpos($url, $c['graph_base'] . '/') !== 0) return ['code' => 0, 'body' => '', 'json' => null, 'headers' => [], 'error' => 'URL inesperada en la respuesta de OneDrive.', 'reconnect' => false];
  $r = od_http('GET', $url, ['headers' => array_merge(['Authorization: Bearer ' . $tok['token'], 'Accept: application/json'], $extraHeaders)]);
  if ($r['code'] === 401 && !$retry) {            // el permiso de acceso pudo caducar o anularse: se pide uno nuevo y se reintenta una vez
    $t = od_read_json('token.json'); $t['expires_at'] = 0; od_write_json('token.json', $t);
    return od_graph($pathOrUrl, $extraHeaders, true);
  }
  if ($r['code'] === 401) $r['reconnect'] = true;
  return $r;
}

// ---------------------------------------------------------------- Rutas locales (con comprobaciones de seguridad)

// ¿Es un nombre de fichero/carpeta aceptable? (sin separadores, sin «..», sin ocultos tipo .obsidian)
function od_safe_name(string $n): bool {
  return $n !== '' && $n[0] !== '.' && strlen($n) <= 200 && !preg_match('#[/\\\\\x00-\x1f]#', $n) && trim($n) === $n;
}

// Ruta relativa dentro del vault de una nota ('Carpeta/Nota.md') o null si no procede sincronizarla
function od_item_path(array $items, string $id, string $rootId): ?string {
  $parts = [];
  $cur = $id;
  for ($i = 0; $i < 40; $i++) {
    if ($cur === $rootId) return $parts ? implode('/', array_reverse($parts)) : null;
    $it = $items[$cur] ?? null;
    if (!$it || !od_safe_name((string) $it['n'])) return null;
    $parts[] = $it['n'];
    $cur = (string) ($it['p'] ?? '');
    if ($cur === '') return null;
  }
  return null;
}

function od_local(string $rel): ?string {
  foreach (explode('/', $rel) as $part) if (!od_safe_name($part)) return null;
  return OD_VAULT . '/' . $rel;
}

function od_write_note(string $rel, string $content): bool {
  $dst = od_local($rel);
  if ($dst === null) return false;
  $dir = dirname($dst);
  if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) return false;
  $tmp = $dir . '/.od_' . bin2hex(random_bytes(4)) . '.tmp';
  if (@file_put_contents($tmp, $content) === false) return false;
  @chmod($tmp, 0644);
  if (!@rename($tmp, $dst)) { @unlink($tmp); return false; }
  return true;
}

function od_remove_note(string $rel): void {
  $f = od_local($rel);
  if ($f === null) return;
  if (is_file($f)) @unlink($f);
  od_remove_note_dirs(dirname($f));       // quita las carpetas que se queden vacías
}

function od_move_note(string $from, string $to): bool {
  $a = od_local($from); $b = od_local($to);
  if ($a === null || $b === null || !is_file($a)) return false;
  $dir = dirname($b);
  if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) return false;
  if (!@rename($a, $b)) return false;
  od_remove_note_dirs(dirname($a));
  return true;
}
// Quita (hacia arriba) las carpetas vacías dentro del vault; rmdir no borra las que tienen contenido
function od_remove_note_dirs(string $dir): void {
  $base = realpath(OD_VAULT);
  while ($dir && $base && ($real = realpath($dir)) && $real !== $base && strpos($real, $base . DIRECTORY_SEPARATOR) === 0) {
    if (!@rmdir($dir)) break;
    $dir = dirname($dir);
  }
}

// ---------------------------------------------------------------- Sincronización

// Modo de respaldo (sin «delta»): recorre la carpeta entera y devuelve [items, null] o [null, resultado_con_error].
// Mismo formato de items que el modo delta. Son pocas peticiones (una por carpeta).
function od_list_tree(string $rootId, callable $left): array {
  $items = [];
  $queue = [$rootId];
  $sel = 'id,name,size,cTag,eTag,parentReference,folder,file';
  for ($guard = 0; $queue && $guard < 400; $guard++) {
    if ($left() < 1.5) return [null, ['code' => 0, 'error' => 'La carpeta es muy grande para listarla de una vez; se continuará en la próxima visita.', 'reconnect' => false, 'partial' => true]];
    $folder = array_shift($queue);
    $url = '/me/drive/items/' . rawurlencode($folder) . '/children?%24top=200&%24select=' . rawurlencode($sel);
    while ($url !== '') {
      $r = od_graph($url);
      if ($r['code'] !== 200 || !is_array($r['json'])) return [null, $r];
      foreach (($r['json']['value'] ?? []) as $it) {
        $id = (string) ($it['id'] ?? ''); if ($id === '') continue;
        $isFolder = isset($it['folder']);
        if (!$isFolder && !isset($it['file'])) continue;
        $items[$id] = ['n' => (string) ($it['name'] ?? ''), 'p' => (string) ($it['parentReference']['id'] ?? $folder), 'd' => $isFolder ? 1 : 0,
                       'c' => (string) ($it['cTag'] ?? $it['eTag'] ?? ''), 'sz' => (int) ($it['size'] ?? 0)];
        if ($isFolder && !preg_match('/^\./', (string) ($it['name'] ?? ''))) $queue[] = $id;      // no se entra en carpetas ocultas (.obsidian…)
      }
      $url = (string) ($r['json']['@odata.nextLink'] ?? '');
    }
  }
  return [$items, null];
}

function od_new_state(): array {
  return ['root_id' => '', 'folder' => '', 'cursor' => '', 'phase' => 'meta', 'mode' => 'delta', 'items' => [], 'files' => [], 'todo' => [],
          'last_sync' => 0, 'last_run' => 0, 'last_change' => 0, 'last_error' => '', 'last_changed' => 0];
}

// Estado para mostrar en pantalla
function od_status(): array {
  $c = od_config();
  $s = od_read_json('state.json') + od_new_state();
  return [
    'configured' => $c !== null,
    'connected'  => $c !== null && od_is_connected(),
    'folder'     => $c['folder'] ?? '',
    'notes'      => count($s['files']),
    'pending'    => count($s['todo']) + ($s['phase'] === 'meta' && $s['cursor'] !== '' ? 1 : 0),
    'last_sync'  => (int) $s['last_sync'],
    'last_run'   => (int) $s['last_run'],
    'last_change'=> (int) $s['last_change'],
    'last_error' => (string) $s['last_error'],
    'refresh_minutes' => $c['refresh_minutes'] ?? 10,
  ];
}

// ¿Toca sincronizar automáticamente?
function od_is_stale(): bool {
  $c = od_config(); if (!$c) return false;
  $s = od_read_json('state.json') + od_new_state();
  return count($s['todo']) > 0 || $s['phase'] === 'meta' || time() - (int) $s['last_sync'] > $c['refresh_minutes'] * 60;
}

// Ejecuta (o continúa) la sincronización con un tiempo máximo. Devuelve
// ['state' => synced|busy|not_configured|not_connected|error, 'changed' => n, 'pending' => n, 'message' => texto]
function od_sync(int $budget = 15, bool $reset = false): array {
  $t0 = microtime(true);
  $left = fn() => $budget - (microtime(true) - $t0);
  if (!od_config()) return ['state' => 'not_configured', 'changed' => 0, 'pending' => 0, 'message' => 'La conexión con OneDrive no está configurada.'];
  if (!od_is_connected()) return ['state' => 'not_connected', 'changed' => 0, 'pending' => 0, 'message' => 'OneDrive no está conectado.'];
  @set_time_limit($budget + 25);
  ignore_user_abort(true);

  $lock = fopen(od_dir() . '/sync.lock', 'c');
  if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) return ['state' => 'busy', 'changed' => 0, 'pending' => count((od_read_json('state.json')['todo'] ?? [])), 'message' => 'Ya hay una sincronización en curso.'];

  $changed = 0;
  $st = $reset ? od_new_state() : (od_read_json('state.json') + od_new_state());
  if ($reset) $st['files'] = (od_read_json('state.json')['files'] ?? []);   // se conserva qué hay en disco para poder limpiar lo que ya no exista
  $finish = function (string $state, string $msg) use (&$st, &$changed, $lock) {
    $st['last_run'] = time();
    if ($state === 'synced') { $st['last_error'] = ''; if (!$st['todo'] && $st['phase'] === 'idle') $st['last_sync'] = time(); }
    else $st['last_error'] = $msg;
    if ($changed > 0) { $st['last_change'] = time(); $st['last_changed'] = $changed; }
    od_write_json('state.json', $st);
    flock($lock, LOCK_UN); fclose($lock);
    return ['state' => $state, 'changed' => $changed, 'pending' => count($st['todo']) + ($st['phase'] === 'meta' && $st['cursor'] !== '' ? 1 : 0), 'message' => $msg];
  };
  $fail = function (array $r) use ($finish) {
    $msg = $r['error'] !== '' ? $r['error'] : 'OneDrive respondió con el error HTTP ' . $r['code'] . '.';
    if (!empty($r['reconnect'])) $msg .= ' Vuelve a conectar OneDrive en Administración → OneDrive.';
    return $finish('error', $msg);
  };

  try {
    // 1) Carpeta raíz del vault
    $c = od_config();
    if ($st['root_id'] === '' || $st['folder'] !== $c['folder']) {
      $enc = implode('/', array_map('rawurlencode', explode('/', $c['folder'])));
      $r = od_graph('/me/drive/root:/' . $enc);
      if ($r['code'] === 404) return $finish('error', 'No se encuentra la carpeta «' . $c['folder'] . '» en tu OneDrive. Revisa la ruta en la configuración.');
      if ($r['code'] === 200 && (empty($r['json']['id']) || !isset($r['json']['folder']))) return $finish('error', '«' . $c['folder'] . '» no es una carpeta.');
      if ($r['code'] !== 200) return $fail($r);
      $st = ['root_id' => (string) $r['json']['id'], 'folder' => $c['folder'], 'items' => [], 'cursor' => '', 'phase' => 'meta'] + $st;
    }
    $root = $st['root_id'];

    // 2) Metadatos: qué carpetas y notas hay. Por defecto con «delta» (solo cambios); si Microsoft no lo admite para esta carpeta,
    //    se pasa solo al modo de listado completo.
    $metaDoneNow = false;
    if ($st['mode'] === 'list') {
      [$items, $err] = od_list_tree($root, $left);
      if ($items === null) {
        if (($err['code'] ?? 0) === 404) { $st['root_id'] = ''; return $finish('error', 'La carpeta de OneDrive ya no existe o ha cambiado de sitio. Se volverá a buscar en la próxima sincronización.'); }
        if (($err['code'] ?? 0) === 429 || ($err['code'] ?? 0) === 503) return $finish('error', 'OneDrive pide esperar un poco. Se reintentará en la próxima visita.');
        return $fail($err);
      }
      $st['items'] = $items; $st['phase'] = 'idle'; $metaDoneNow = true;
    } else {
      if ($st['phase'] === 'idle' || $st['cursor'] === '') {
        // Empieza un ciclo nuevo: desde el último deltaLink (o desde cero si no hay)
        $url = $st['cursor'] !== '' ? $st['cursor'] : '/me/drive/items/' . rawurlencode($root) . '/delta';
        $st['cursor'] = $url; $st['phase'] = 'meta';
        if (strpos($url, 'http') !== 0) $st['items'] = [];
      }
      while ($st['phase'] === 'meta' && $left() > 1) {
        $r = od_graph($st['cursor']);
        if ($r['code'] === 410) {                    // el servidor pide empezar de cero
          $st['items'] = []; $st['cursor'] = '/me/drive/items/' . rawurlencode($root) . '/delta'; continue;
        }
        if (!empty($r['reconnect'])) return $fail($r);
        if (in_array($r['code'], [400, 405, 501], true) || ($r['code'] === 404 && strpos($st['cursor'], 'http') !== 0)) {
          // «delta» no disponible para esta carpeta: modo de listado completo (se guarda para las próximas veces)
          $st['mode'] = 'list'; $st['cursor'] = ''; $st['items'] = [];
          od_write_json('state.json', $st);
          [$items, $err] = od_list_tree($root, $left);
          if ($items === null) { if (($err['code'] ?? 0) === 404) { $st['root_id'] = ''; return $finish('error', 'La carpeta de OneDrive ya no existe o ha cambiado de sitio. Se volverá a buscar en la próxima sincronización.'); } return $fail($err); }
          $st['items'] = $items; $st['phase'] = 'idle'; $metaDoneNow = true;
          break;
        }
        if ($r['code'] === 404) { $st['root_id'] = ''; return $finish('error', 'La carpeta de OneDrive ya no existe o ha cambiado de sitio. Se volverá a buscar en la próxima sincronización.'); }
        if ($r['code'] === 429 || $r['code'] === 503) return $finish('error', 'OneDrive pide esperar un poco. Se reintentará en la próxima visita.');
        if ($r['code'] !== 200 || !is_array($r['json'])) return $fail($r);
        foreach (($r['json']['value'] ?? []) as $it) {
          $id = (string) ($it['id'] ?? ''); if ($id === '' || $id === $root) continue;
          if (isset($it['deleted'])) { unset($st['items'][$id]); continue; }
          $isFolder = isset($it['folder']); $isFile = isset($it['file']);
          if (!$isFolder && !$isFile) { unset($st['items'][$id]); continue; }
          $st['items'][$id] = ['n' => (string) ($it['name'] ?? ''), 'p' => (string) ($it['parentReference']['id'] ?? ''), 'd' => $isFolder ? 1 : 0,
                               'c' => (string) ($it['cTag'] ?? $it['eTag'] ?? ''), 'sz' => (int) ($it['size'] ?? 0)];
        }
        if (!empty($r['json']['@odata.nextLink'])) { $st['cursor'] = (string) $r['json']['@odata.nextLink']; od_write_json('state.json', $st); continue; }
        if (!empty($r['json']['@odata.deltaLink'])) { $st['cursor'] = (string) $r['json']['@odata.deltaLink']; $st['phase'] = 'idle'; $metaDoneNow = true; break; }
        return $finish('error', 'Respuesta inesperada de OneDrive (sin enlace de continuación).');
      }
    }

    // 3) Reconciliar con lo que hay en disco (solo cuando los metadatos están completos)
    if ($metaDoneNow) {
      $desired = [];
      foreach ($st['items'] as $id => $it) {
        if ((int) $it['d'] === 1 || strtolower(pathinfo((string) $it['n'], PATHINFO_EXTENSION)) !== 'md') continue;
        if ((int) $it['sz'] > OD_MAX_NOTE_BYTES) continue;
        $p = od_item_path($st['items'], (string) $id, $root);
        if ($p !== null) $desired[(string) $id] = $p;
      }
      foreach ($st['files'] as $id => $f) {                            // notas que ya no existen (o han salido de la carpeta)
        if (!isset($desired[$id])) { od_remove_note((string) $f['path']); unset($st['files'][$id]); $changed++; }
      }
      $todo = [];
      foreach ($desired as $id => $path) {
        $f = $st['files'][$id] ?? null;
        if ($f && $f['path'] !== $path) {                              // renombrada o movida
          if (od_move_note((string) $f['path'], $path)) { $st['files'][$id]['path'] = $path; $changed++; }
          else $todo[] = (string) $id;
        }
        if (!$f || $f['c'] !== $st['items'][$id]['c'] || !is_file((string) od_local($path))) $todo[] = (string) $id;   // nueva, modificada o borrada a mano
      }
      $st['todo'] = array_values(array_unique($todo));
      $st['desired'] = $desired;
    }

    // 4) Descargar las notas pendientes
    $desired = $st['desired'] ?? [];
    $errors = 0;
    while ($st['todo'] && $left() > 2) {
      $id = array_shift($st['todo']);
      if (!isset($desired[$id]) || !isset($st['items'][$id])) continue;           // ya no procede
      $r = od_graph('/me/drive/items/' . rawurlencode($id) . '/content');
      $body = null;
      if (in_array($r['code'], [301, 302, 303, 307, 308], true) && !empty($r['headers']['location'])) {
        $loc = $r['headers']['location'];
        $d = od_http('GET', $loc, ['timeout' => 20]);             // URL temporal ya firmada: sin cabecera de autorización
        if ($d['code'] === 200 && $d['error'] === '') $body = $d['body'];
      } elseif ($r['code'] === 200) { $body = $r['body']; }
      elseif ($r['code'] === 404) { continue; }
      if ($body === null || strlen($body) > OD_MAX_NOTE_BYTES) { $errors++; $st['todo'][] = $id; if ($errors >= 3) break; continue; }
      $path = $desired[$id];
      if (!od_write_note($path, $body)) { $errors++; $st['todo'][] = $id; if ($errors >= 3) break; continue; }
      $st['files'][$id] = ['path' => $path, 'c' => (string) $st['items'][$id]['c']];
      $changed++;
    }
    if ($errors >= 3) return $finish('error', 'Fallan varias descargas seguidas desde OneDrive. Se reintentará más tarde.');

    $done = (!$st['todo'] && $st['phase'] === 'idle');
    return $finish('synced', $done ? ($changed > 0 ? 'Notas actualizadas.' : 'Todo estaba al día.') : 'Sincronizando… quedan notas por descargar.');
  } catch (Throwable $e) {
    error_log('OneDrive sync: ' . $e->getMessage());
    if (isset($lock) && is_resource($lock)) { od_write_json('state.json', $st); flock($lock, LOCK_UN); fclose($lock); }
    return ['state' => 'error', 'changed' => $changed, 'pending' => 0, 'message' => 'Error interno durante la sincronización (ver el registro del servidor).'];
  }
}

// Borra del servidor las notas .md que NO provienen de OneDrive (restos del vault antiguo). Solo si todo está sincronizado.
// Devuelve [nº borradas, mensaje de error o '']
function od_prune_untracked(): array {
  $st = od_read_json('state.json') + od_new_state();
  if ($st['phase'] !== 'idle' || $st['todo'] || !$st['files']) return [0, 'Primero tiene que terminar una sincronización completa.'];
  $keep = [];
  foreach ($st['files'] as $f) $keep[$f['path']] = true;
  $base = realpath(OD_VAULT);
  if (!$base) return [0, 'No existe la carpeta del vault.'];
  $n = 0;
  $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::LEAVES_ONLY);
  foreach ($it as $file) {
    if (!$file->isFile() || $file->isLink() || strtolower($file->getExtension()) !== 'md') continue;
    $rel = str_replace(DIRECTORY_SEPARATOR, '/', substr($file->getPathname(), strlen($base) + 1));
    if (preg_match('#(^|/)\.#', $rel) || isset($keep[$rel])) continue;     // ocultos (.obsidian…) y lo sincronizado se quedan
    if (@unlink($file->getPathname())) { $n++; od_remove_note_dirs(dirname($file->getPathname())); }
  }
  return [$n, ''];
}

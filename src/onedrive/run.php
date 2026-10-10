<?php
// src/onedrive/run.php: ejecuta un tramo de la sincronización y devuelve el progreso en JSON (lo llama onedrive.js en bucle).
// POST csrf + mode = sync | continue | reset. Solo administradores.
require_once __DIR__ . '/../../backend/config/bootstrap.php';
require_once __DIR__ . '/../../backend/lib/onedrive.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if (!is_logged_in()) { http_response_code(401); echo json_encode(['state' => 'error', 'message' => 'La sesión ha caducado. Recarga la página.']); exit(); }
if (!is_admin()) { http_response_code(403); echo json_encode(['state' => 'error', 'message' => 'Solo el administrador puede hacerlo.']); exit(); }
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_valid($_POST['csrf'] ?? null)) { http_response_code(403); echo json_encode(['state' => 'error', 'message' => 'Petición no válida: recarga la página.']); exit(); }
session_write_close();                       // no bloquear otras pestañas mientras se sincroniza

$mode = (string) ($_POST['mode'] ?? 'sync');
$r = od_sync(12, $mode === 'reset');
$s = od_status();
echo json_encode($r + ['notes' => $s['notes'], 'total' => $s['total'], 'todo' => $s['todo'], 'last_sync' => $s['last_sync'], 'last_change' => $s['last_change']], JSON_UNESCAPED_UNICODE);

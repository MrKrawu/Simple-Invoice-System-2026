<?php
// Gemeinsame Sicherheitsfunktionen: Session, Login-/Rollenprüfung, CSRF, Passwort-Hashing, DB-Helfer.

if (session_status() === PHP_SESSION_NONE) {
  session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/',
    'secure'   => !empty($_SERVER['HTTPS']),
    'httponly' => true,
    'samesite' => 'Lax',
  ]);
  session_start();
}

function h($s) {
  return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function json_fail($code, $msg) {
  http_response_code($code);
  header('Content-Type: application/json');
  echo json_encode(['status' => 'error', 'error' => $msg, 'message' => $msg]);
  exit;
}

function csrf_token() {
  if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
  }
  return $_SESSION['csrf'];
}

// Für Ajax-Endpunkte: Login + (bei POST) CSRF-Token erforderlich; optional Admin.
function require_api($admin = false) {
  if (empty($_SESSION['user_id'])) json_fail(401, 'Not logged in.');
  if ($admin && ($_SESSION['user_role'] ?? '') !== 'Admin') json_fail(403, 'Forbidden.');
  if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $t = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!is_string($t) || !hash_equals($_SESSION['csrf'] ?? '', $t)) json_fail(403, 'Invalid CSRF token.');
  }
}

// Für Seiten (HTML): Weiterleitung zum Login.
function require_page($admin = false) {
  if (empty($_SESSION['user_email'])) { header('Location: login.html'); exit; }
  if ($admin && ($_SESSION['user_role'] ?? '') !== 'Admin') { http_response_code(403); exit('Forbidden.'); }
}

function pw_hash($plain) {
  return password_hash($plain, PASSWORD_DEFAULT);
}

// Prüft bcrypt/argon-Hash oder Alt-Hash (sha1). Gibt true/false zurück; $needs_rehash zeigt Upgrade an.
function pw_verify($plain, $stored, &$needs_rehash = false) {
  $needs_rehash = false;
  if (preg_match('/^[0-9a-f]{40}$/i', $stored)) {
    if (hash_equals(strtolower($stored), sha1($plain))) { $needs_rehash = true; return true; }
    return false;
  }
  if (password_verify($plain, $stored)) {
    $needs_rehash = password_needs_rehash($stored, PASSWORD_DEFAULT);
    return true;
  }
  return false;
}

// Prepared-Statement-Helfer. $types z. B. "ssi". Gibt mysqli_stmt zurück (oder false).
function q($conn, $sql, $types = '', $params = []) {
  $st = mysqli_prepare($conn, $sql);
  if (!$st) { error_log('SQL prepare: ' . mysqli_error($conn)); return false; }
  if ($types !== '') mysqli_stmt_bind_param($st, $types, ...$params);
  if (!mysqli_stmt_execute($st)) { error_log('SQL exec: ' . mysqli_stmt_error($st)); return false; }
  return $st;
}
function q_rows($conn, $sql, $types = '', $params = []) {
  $st = q($conn, $sql, $types, $params);
  if (!$st) return [];
  $res = mysqli_stmt_get_result($st);
  return $res ? mysqli_fetch_all($res, MYSQLI_ASSOC) : [];
}
function q_one($conn, $sql, $types = '', $params = []) {
  $r = q_rows($conn, $sql, $types, $params);
  return $r ? $r[0] : null;
}
function q_ok($conn, $sql, $types = '', $params = []) {
  return q($conn, $sql, $types, $params) !== false;
}

// DataTables-Sortierung: nur Spalten aus der Whitelist.
function dt_order($allowed, $default) {
  $i   = (int)($_POST['order'][0]['column'] ?? 0);
  $col = $_POST['columns'][$i]['data'] ?? $default;
  if (!in_array($col, $allowed, true)) $col = $default;
  $dir = (($_POST['order'][0]['dir'] ?? 'asc') === 'desc') ? 'DESC' : 'ASC';
  return "$col $dir";
}

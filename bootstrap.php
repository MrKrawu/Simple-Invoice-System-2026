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

// Prüft bei jedem Request Status und Rolle gegen die Datenbank (nicht nur beim Login).
// Deaktivierte/gelöschte Konten verlieren sofort die Session; Rollenänderungen greifen sofort.
function refresh_user() {
  if (empty($_SESSION['user_id'])) return false;
  global $conn;
  if (!isset($conn)) require_once __DIR__ . '/connection.php';
  $u = q_one($conn, "SELECT user_status, user_role FROM tbl_users WHERE user_id = ?", "i", [(int)$_SESSION['user_id']]);
  if (!$u || $u['user_status'] !== 'Active') {
    $_SESSION = [];
    session_destroy();
    return false;
  }
  $_SESSION['user_status'] = $u['user_status'];
  $_SESSION['user_role']   = $u['user_role'];
  return true;
}

// Für Ajax-Endpunkte: Login + (bei POST) CSRF-Token erforderlich; optional Admin.
function require_api($admin = false) {
  if (!refresh_user()) json_fail(401, 'Not logged in.');
  if ($admin && ($_SESSION['user_role'] ?? '') !== 'Admin') json_fail(403, 'Forbidden.');
  if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $t = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!is_string($t) || !hash_equals($_SESSION['csrf'] ?? '', $t)) json_fail(403, 'Invalid CSRF token.');
  }
}

// Für Seiten (HTML): Weiterleitung zum Login.
function require_page($admin = false) {
  if (empty($_SESSION['user_email']) || !refresh_user()) { header('Location: login.html'); exit; }
  if ($admin && ($_SESSION['user_role'] ?? '') !== 'Admin') { http_response_code(403); exit('Forbidden.'); }
}

// POST-Wert nur als String (Arrays/Fremdtypen → leer).
function post_str($key, $default = '') {
  $v = $_POST[$key] ?? $default;
  return is_string($v) ? $v : $default;
}

// Geldbeträge: immer zwei Nachkommastellen.
function money($n) { return number_format((float)$n, 2, '.', ''); }

// Steuersatz (wie im Client: 18 %).
const TAX_RATE = 0.18;

// Eine Position serverseitig berechnen (Client-Beträge werden ignoriert).
function build_item_line($name, $qty, $price) {
  $subtotal = round($qty * $price, 2);
  $tax      = round($subtotal * TAX_RATE, 2);
  $total    = round($subtotal + $tax, 2);
  return str_replace('||', '', $name) . '||' . $qty . '||' . money($price) . '||' . money($tax) . '||' . money($subtotal) . '||' . money($total);
}

// Summen aus den Session-Zeilen "name||qty||price||tax||subtotal||total".
function item_totals($lines) {
  $sub = 0.0; $tax = 0.0; $tot = 0.0;
  foreach ((array)$lines as $line) {
    $r = explode('||', (string)$line);
    if (count($r) < 6) continue;
    $sub += (float)$r[4]; $tax += (float)$r[3]; $tot += (float)$r[5];
  }
  return [round($sub, 2), round($tax, 2), round($tot, 2)];
}

// Login-Bremse pro IP und pro IP+E-Mail (DB-basiert, nicht per Cookie umgehbar).
function throttle_keys($email) {
  $ip = $_SERVER['REMOTE_ADDR'] ?? '0';
  return [hash('sha256', "ip|$ip"), hash('sha256', "ipmail|$ip|" . strtolower($email))];
}
function throttle_init($conn) {
  mysqli_query($conn, "CREATE TABLE IF NOT EXISTS login_attempts (k CHAR(64) NOT NULL PRIMARY KEY, fails INT NOT NULL, last_at INT NOT NULL)");
}
function throttle_blocked($conn, $email) {
  throttle_init($conn);
  [$kip, $kmail] = throttle_keys($email);
  foreach ([[$kip, 20], [$kmail, 5]] as [$k, $max]) {
    $r = q_one($conn, "SELECT fails, last_at FROM login_attempts WHERE k = ?", "s", [$k]);
    if ($r && $r['fails'] >= $max && time() - $r['last_at'] < 60) return true;
  }
  return false;
}
function throttle_fail($conn, $email) {
  foreach (throttle_keys($email) as $k) {
    q_ok($conn, "INSERT INTO login_attempts (k, fails, last_at) VALUES (?, 1, ?) ON DUPLICATE KEY UPDATE fails = IF(? - last_at >= 60, 1, fails + 1), last_at = ?",
         "siii", [$k, time(), time(), time()]);
  }
}
function throttle_reset($conn, $email) {
  q_ok($conn, "DELETE FROM login_attempts WHERE k = ?", "s", [throttle_keys($email)[1]]);
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

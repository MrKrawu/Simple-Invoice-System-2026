<?php
require_once "bootstrap.php";
require_once "connection.php";

header('Content-Type: application/json');

$email    = (string)($_POST['email'] ?? '');
$password = (string)($_POST['password'] ?? '');

// Einfache Bremse gegen Brute-Force (pro Session)
$_SESSION['login_fail'] = $_SESSION['login_fail'] ?? 0;
if ($_SESSION['login_fail'] >= 5 && time() - ($_SESSION['login_fail_at'] ?? 0) < 60) {
  echo json_encode(['status' => 'error', 'error' => 'Too many attempts. Please wait a minute.']);
  exit;
}

$user = q_one($conn, "SELECT * FROM tbl_users WHERE user_email = ?", "s", [$email]);
$rehash = false;

if ($user && pw_verify($password, $user['user_password'], $rehash)) {
  if ($user['user_status'] === 'Inactive') {
    echo json_encode(['status' => 'inactive', 'error' => 'This user has been set as inactive. Please contact your administrator.']);
    exit;
  }
  if ($rehash) {
    q_ok($conn, "UPDATE tbl_users SET user_password = ? WHERE user_id = ?", "si", [pw_hash($password), $user['user_id']]);
  }
  session_regenerate_id(true);
  $_SESSION['login_fail'] = 0;
  foreach (['user_id','user_created_by','user_last_update_by','user_full_name','user_email','user_gender','user_status','user_role','user_created_at','user_updated_at'] as $k) {
    $_SESSION[$k] = $user[$k];
  }
  csrf_token();
  echo json_encode(['status' => 'success']);
} else {
  $_SESSION['login_fail']++;
  $_SESSION['login_fail_at'] = time();
  echo json_encode(['status' => 'error', 'error' => 'Incorrect email or password.']);
}

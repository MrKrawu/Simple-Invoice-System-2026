<?php
require_once "bootstrap.php";
require_once "connection.php";

header('Content-Type: application/json');

$email    = post_str('email');
$password = post_str('password');

// Bremse gegen Brute-Force: pro IP und pro IP+E-Mail, in der Datenbank (nicht per neuer Session umgehbar)
if (throttle_blocked($conn, $email)) {
  echo json_encode(['status' => 'error', 'error' => 'Too many attempts. Please wait a minute.']);
  exit;
}

$user = q_one($conn, "SELECT * FROM tbl_users WHERE user_email = ?", "s", [$email]);
$rehash = false;

// Bei unbekannter E-Mail trotzdem einen Hash prüfen (gleiche Antwortzeit, kein Konto-Orakel)
$stored = $user['user_password'] ?? '$2y$10$usesomesillystringforsaleeIG1v0pzYQNRlq4bDq0cq4aTNmG4yK';

if (pw_verify($password, $stored, $rehash) && $user) {
  if ($user['user_status'] === 'Inactive') {
    echo json_encode(['status' => 'inactive', 'error' => 'This user has been set as inactive. Please contact your administrator.']);
    exit;
  }
  if ($rehash) {
    q_ok($conn, "UPDATE tbl_users SET user_password = ? WHERE user_id = ?", "si", [pw_hash($password), $user['user_id']]);
  }
  session_regenerate_id(true);
  throttle_reset($conn, $email);
  foreach (['user_id','user_created_by','user_last_update_by','user_full_name','user_email','user_gender','user_status','user_role','user_created_at','user_updated_at'] as $k) {
    $_SESSION[$k] = $user[$k];
  }
  csrf_token();
  echo json_encode(['status' => 'success']);
} else {
  throttle_fail($conn, $email);
  echo json_encode(['status' => 'error', 'error' => 'Incorrect email or password.']);
}

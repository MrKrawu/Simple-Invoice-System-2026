<?php
// Aktionen des eingeloggten Benutzers (Profil, Passwort).
require_once "bootstrap.php";
require_once "connection.php";
require_api();

$uid = (int)$_SESSION['user_id'];
$action = $_POST['action'] ?? '';

if ($action == "profile_fetch") {
  $r = q_one($conn, "SELECT user_id, user_full_name, user_email, user_gender, user_status, user_role, user_created_at FROM tbl_users WHERE user_id = ?", "i", [$uid]);
  echo json_encode([
    'user_id' => $r['user_id'], 'user_full_name' => $r['user_full_name'], 'user_email' => $r['user_email'],
    'user_gender' => $r['user_gender'], 'user_status' => $r['user_status'], 'user_role' => $r['user_role'],
    'user_created_at' => $r['user_created_at'],
  ]);
}

if ($action == "update_profile") {
  $gender = in_array($_POST['gender'] ?? '', ['Male', 'Female'], true) ? $_POST['gender'] : null;
  $name = trim((string)($_POST['full_name'] ?? ''));
  if ($gender === null || $name === '') json_fail(400, 'Invalid input.');
  if (q_ok($conn, "UPDATE tbl_users SET user_full_name = ?, user_gender = ? WHERE user_id = ?", "ssi", [$name, $gender, $uid])) {
    $_SESSION['user_full_name'] = $name;
    echo json_encode(['status' => 'success', 'message' => ' Your profile has been updated successfully.']);
  } else {
    json_fail(500, 'Update failed.');
  }
}

if ($action == "update_password") {
  $user = q_one($conn, "SELECT user_password FROM tbl_users WHERE user_id = ?", "i", [$uid]);
  $new = (string)($_POST['new_password'] ?? '');
  if ($user && pw_verify((string)($_POST['current_password'] ?? ''), $user['user_password']) && strlen($new) >= 8) {
    if (q_ok($conn, "UPDATE tbl_users SET user_password = ? WHERE user_id = ?", "si", [pw_hash($new), $uid])) {
      echo json_encode(['status' => 'success', 'message' => ' Password updated successfully.']);
    } else {
      json_fail(500, 'Update failed.');
    }
  } else {
    echo json_encode(['error' => 'true']);
  }
}

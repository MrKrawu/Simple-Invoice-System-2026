<?php
// user_action.php – Benutzerverwaltung (nur Admin)
require_once "bootstrap.php";
require_once "connection.php";
require_api(true);

$action = $_POST["action"] ?? '';
$genders  = ['Male', 'Female'];
$statuses = ['Active', 'Inactive'];
$roles    = ['Admin', 'User'];

function valid_user_input($name, $email, $gender, $status, $role) {
  global $genders, $statuses, $roles;
  return trim($name) !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)
    && in_array($gender, $genders, true) && in_array($status, $statuses, true) && in_array($role, $roles, true);
}

if ($action == "user_fetch") {
  $start  = max(0, (int)($_POST['start'] ?? 0));
  $length = min(100, max(1, (int)($_POST['length'] ?? 10)));
  $order  = dt_order(['user_id','user_full_name','user_email','user_gender','user_role','user_status'], 'user_id');

  $where = ''; $types = ''; $params = [];
  $s = (string)($_POST['search']['value'] ?? '');
  if ($s !== '') {
    $like = '%' . $s . '%';
    $where = " WHERE (user_id LIKE ? OR user_full_name LIKE ? OR user_email LIKE ? OR user_gender LIKE ? OR user_status LIKE ? OR user_role LIKE ?)";
    $types = 'ssssss'; $params = array_fill(0, 6, $like);
  }
  $total    = q_one($conn, "SELECT count(*) AS c FROM tbl_users")['c'];
  $filtered = q_one($conn, "SELECT count(*) AS c FROM tbl_users" . $where, $types, $params)['c'];
  $rows = q_rows($conn, "SELECT user_id, user_full_name, user_email, user_gender, user_status, user_role FROM tbl_users" . $where . " ORDER BY $order LIMIT $start, $length", $types, $params);

  $data = [];
  foreach ($rows as $row) {
    $status = $row["user_status"] == "Active" ? '<label class="badge badge-success">Active</label>'
            : ($row["user_status"] == "Inactive" ? '<label class="badge badge-danger">Inactive</label>' : '');
    $id = (int)$row['user_id'];
    $data[] = [
      "user_id" => $id,
      "user_full_name" => h($row['user_full_name']),
      "user_email" => h($row['user_email']),
      "user_gender" => h($row['user_gender']),
      "user_role" => h($row['user_role']),
      "user_status" => $status,
      "action" => '<button type="button" class="btn btn-secondary view_user btn-sm" data-toggle="modal" data-target="#readModal" id="'.$id.'"><i class="fas fa-eye"></i></button>
                   <button type="button" class="btn btn-success update_user btn-sm" id="'.$id.'"><i class="fas fa-edit"></i></button>',
    ];
  }
  echo json_encode(["draw" => intval($_POST['draw'] ?? 0), "iTotalRecords" => $total, "iTotalDisplayRecords" => $filtered, "aaData" => $data]);
}

if ($action == "add_user") {
  $name = post_str('full_name'); $email = post_str('email');
  $gender = post_str('gender'); $status = post_str('status'); $role = post_str('role');
  $pw = post_str('password');
  if (!valid_user_input($name, $email, $gender, $status, $role) || strlen($pw) < 8) json_fail(400, 'Invalid input.');

  if (q_one($conn, "SELECT user_id FROM tbl_users WHERE user_email = ?", "s", [$email])) {
    echo json_encode(['status' => 'error', 'message' => 'This email address is already in use.']);
  } elseif (q_ok($conn, "INSERT INTO tbl_users (user_created_by, user_full_name, user_email, user_gender, user_status, user_role, user_password, user_created_at) VALUES (?,?,?,?,?,?,?,NOW())",
      "issssss", [(int)$_SESSION['user_id'], trim($name), $email, $gender, $status, $role, pw_hash($pw)])) {
    echo json_encode(['status' => 'success', 'message' => ' New user has been successfully added.']);
  } else {
    json_fail(500, 'Insert failed.');
  }
}

if ($action == "single_fetch") {
  $row = q_one($conn, "SELECT u1.user_id, u1.user_full_name, u1.user_email, u1.user_gender, u1.user_status, u1.user_role,
      u2.user_full_name AS created_by, u1.user_created_at, u1.user_updated_at, u3.user_full_name AS updated_by
    FROM tbl_users AS u1 INNER JOIN tbl_users AS u2 ON u1.user_created_by = u2.user_id
    LEFT JOIN tbl_users AS u3 ON u1.user_last_update_by = u3.user_id WHERE u1.user_id = ?", "i", [(int)($_POST['user_id'] ?? 0)]);
  if (!$row) json_fail(404, 'Not found.');
  echo json_encode([
    "user_id" => $row['user_id'], "user_full_name" => $row['user_full_name'], "user_email" => $row['user_email'],
    "user_gender" => $row['user_gender'], "user_status" => $row['user_status'], "user_role" => $row['user_role'],
    "user_created_by" => $row['created_by'], "user_created_at" => $row['user_created_at'],
    "user_updated_at" => $row['user_updated_at'], "user_last_update_by" => $row['updated_by'],
  ]);
}

if ($action == "update_user_profile") {
  $id = (int)($_POST['update_profile_id'] ?? 0);
  $name = post_str('update_full_name'); $email = post_str('update_email');
  $gender = post_str('update_gender'); $status = post_str('update_status'); $role = post_str('update_role');
  if (!valid_user_input($name, $email, $gender, $status, $role)) json_fail(400, 'Invalid input.');
  if ($id === (int)$_SESSION['user_id'] && ($role !== 'Admin' || $status !== 'Active')) json_fail(400, 'You cannot demote or deactivate your own account.');

  if (q_one($conn, "SELECT user_id FROM tbl_users WHERE user_email = ? AND user_id != ?", "si", [$email, $id])) {
    echo json_encode(['status' => 'error', 'message' => 'This email address is already in use.']);
  } elseif (q_ok($conn, "UPDATE tbl_users SET user_last_update_by = ?, user_full_name = ?, user_email = ?, user_gender = ?, user_role = ?, user_status = ?, user_updated_at = NOW() WHERE user_id = ?",
      "isssssi", [(int)$_SESSION['user_id'], trim($name), $email, $gender, $role, $status, $id])) {
    echo json_encode(['status' => 'success', 'message' => ' User profile has been successfully updated.']);
  } else {
    json_fail(500, 'Update failed.');
  }
}

if ($action == "update_user_password") {
  $pw = post_str('update_password');
  if (strlen($pw) < 8) json_fail(400, 'Invalid input.');
  $st = q($conn, "UPDATE tbl_users SET user_last_update_by = ?, user_password = ?, user_updated_at = NOW() WHERE user_id = ?",
      "isi", [(int)$_SESSION['user_id'], pw_hash($pw), (int)($_POST['update_password_id'] ?? 0)]);
  if ($st && mysqli_stmt_affected_rows($st) < 1) json_fail(404, 'User not found.');
  if ($st) {
    echo json_encode(['status' => 'success', 'message' => ' User password has been successfully updated.']);
  } else {
    json_fail(500, 'Update failed.');
  }
}

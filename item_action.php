<?php
// item_action.php – Rechnungen (Liste, Anlegen, Ändern, Löschen)
require_once "bootstrap.php";
require_once "connection.php";
require_api();

$action = $_POST["action"] ?? '';

// Positionen werden als "||"-getrennte Strings in der Session gehalten.
// Beträge (Steuer, Zwischensumme, Summe) berechnet ausschließlich der Server; Client-Werte werden ignoriert.
function clean_item_field($v) { return str_replace('||', '', (string)$v); }
function item_from_post() {
  $name = trim(clean_item_field(post_str('item_name')));
  $q = $_POST['qty'] ?? null; $p = $_POST['price'] ?? null;
  if (!is_string($q) || !is_string($p) || !is_numeric($q) || !is_numeric($p)) json_fail(400, 'Invalid number.');
  $qty = (float)$q; $price = round((float)$p, 2);
  if ($name === '' || mb_strlen($name) > 50) json_fail(400, 'Item name is required (max. 50 characters).');
  if ($qty != floor($qty) || $qty < 1 || $qty > 100) json_fail(400, 'Quantity must be a whole number from 1 to 100.');
  if ($price < 0 || $price > 100000) json_fail(400, 'Price must be between 0 and 100000.');
  return build_item_line($name, (int)$qty, $price);
}
// Kopfdaten prüfen (Spaltenlängen), sonst sauberer Fehler statt halber Rechnung.
function order_head_from_post() {
  $name = trim(post_str('customer_name')); $addr = post_str('customer_address'); $note = post_str('note');
  if ($name === '' || mb_strlen($name) > 100) json_fail(400, 'Customer name is required (max. 100 characters).');
  if (mb_strlen($addr) > 5000 || mb_strlen($note) > 5000) json_fail(400, 'Address or note too long.');
  return [$name, $addr, $note];
}
function insert_items($conn, $order_id, $item_table) {
  foreach ($item_table as $line) {
    $it = explode("||", $line);
    if (count($it) < 6) continue;
    if (!q_ok($conn, "INSERT INTO invoice_order_item (order_id, item_name, order_item_quantity, order_item_price, order_item_tax_amount, order_item_subtotal_amount, order_item_final_amount) VALUES (?,?,?,?,?,?,?)",
        "isidddd", [$order_id, $it[0], (int)$it[1], $it[2], $it[3], $it[4], $it[5]])) return false;
  }
  return true;
}

if ($action == "item_fetch") {
  $start  = max(0, (int)($_POST['start'] ?? 0));
  $length = min(100, max(1, (int)($_POST['length'] ?? 10)));
  $order  = dt_order(['order_id','order_receiver_name','order_total_before_tax','order_total_tax','order_total_after_tax','order_created_at'], 'order_id');

  $where = ''; $types = ''; $params = [];
  $s = is_string($_POST['search']['value'] ?? null) ? $_POST['search']['value'] : '';
  if ($s !== '') {
    $like = '%' . $s . '%';
    $where = " WHERE (order_id LIKE ? OR order_receiver_name LIKE ? OR order_total_before_tax LIKE ? OR order_total_tax LIKE ? OR order_total_after_tax LIKE ? OR order_created_at LIKE ?)";
    $types = 'ssssss'; $params = array_fill(0, 6, $like);
  }
  $total    = q_one($conn, "SELECT count(*) AS c FROM tbl_orders")['c'];
  $filtered = q_one($conn, "SELECT count(*) AS c FROM tbl_orders" . $where, $types, $params)['c'];
  $rows = q_rows($conn, "SELECT order_id, order_receiver_name, order_total_before_tax, order_total_tax, order_total_after_tax, order_created_at FROM tbl_orders" . $where . " ORDER BY $order LIMIT $start, $length", $types, $params);

  $data = [];
  foreach ($rows as $row) {
    $id = (int)$row['order_id'];
    $data[] = [
      "order_id" => $id,
      "order_receiver_name" => h($row['order_receiver_name']),
      "order_total_before_tax" => h($row['order_total_before_tax']),
      "order_total_tax" => h($row['order_total_tax']),
      "order_total_after_tax" => h($row['order_total_after_tax']),
      "order_created_at" => h($row['order_created_at']),
      "view" => '<a href="print_invoice.php?order_id='.$id.'&output" class="btn btn-info btn-sm"><i class="fas fa-download"></i></a>
                 <a href="print_invoice.php?order_id='.$id.'" class="btn btn-primary btn-sm" target="_blank"><i class="fas fa-print"></i></a>
                 <a href="update_invoice.php?order_id='.$id.'" class="btn btn-success btn-sm"><i class="fas fa-edit"></i></a>
                 <button type="button" order_id="'.$id.'" class="btn btn-danger btn-sm delete_invoice"><i class="fas fa-trash-alt"></i></button>',
    ];
  }
  echo json_encode(["draw" => intval($_POST['draw'] ?? 0), "iTotalRecords" => $total, "iTotalDisplayRecords" => $filtered, "aaData" => $data]);
}

if ($action == "add_item") {
  $_SESSION['item_table_temp'][] = item_from_post();
  $_SESSION['customer_name'] = post_str('customer_name');
  $_SESSION['customer_address'] = post_str('customer_address');
  $_SESSION['customer_note'] = post_str('note');
}

if ($action == "eliminate_item") {
  if (isset($_SESSION['item_table_temp'])) {
    unset($_SESSION['item_table_temp'][(int)($_POST['index'] ?? -1)]);
    $_SESSION['item_table_temp'] = array_values($_SESSION['item_table_temp']);
  }
}

if ($action == "create_invoice") {
  [$sub, $tax, $tot] = item_totals($_SESSION['item_table_temp'] ?? []);
  if (!empty($_SESSION['item_table_temp']) && $tot > 0) {
    [$cname, $caddr, $cnote] = order_head_from_post();
    // Kopf und Positionen in einer Transaktion: entweder alles oder nichts
    mysqli_begin_transaction($conn);
    $order_id = 0;
    $ok = q_ok($conn, "INSERT INTO tbl_orders (order_created_by, order_receiver_name, order_receiver_address, order_total_before_tax, order_total_tax, order_total_after_tax, order_note, order_created_at) VALUES (?,?,?,?,?,?,?,NOW())",
      "issddds", [(int)$_SESSION["user_id"], $cname, $caddr, $sub, $tax, $tot, $cnote]);
    if ($ok) {
      $order_id = mysqli_insert_id($conn);
      $ok = insert_items($conn, $order_id, $_SESSION['item_table_temp']);
    }
    if ($ok && mysqli_commit($conn)) {
      $output = ['status' => 'success', 'message' => 'New invoice successfully created!', 'order_id' => $order_id];
      unset($_SESSION['item_table_temp'], $_SESSION['customer_name'], $_SESSION['customer_address']);
    } else {
      mysqli_rollback($conn);
      $output = ['status' => 'error', 'message' => 'Invoice could not be saved.'];
    }
  } else {
    $output = ['status' => 'empty', 'message' => 'Please enter at least one item information.'];
  }
  echo json_encode($output);
}

if ($action == "cancel_invoice") {
  unset($_SESSION['item_table_temp'], $_SESSION['customer_name'], $_SESSION['customer_address']);
}

if ($action == "delete_order") {
  $id = (int)($_POST["order_id"] ?? 0);
  mysqli_begin_transaction($conn);
  $st1 = q($conn, "DELETE FROM invoice_order_item WHERE order_id = ?", "i", [$id]);
  $st2 = q($conn, "DELETE FROM tbl_orders WHERE order_id = ?", "i", [$id]);
  if (!$st1 || !$st2) { mysqli_rollback($conn); json_fail(500, 'Delete failed.'); }
  if (mysqli_stmt_affected_rows($st2) < 1) { mysqli_rollback($conn); json_fail(404, 'Invoice not found.'); }
  mysqli_commit($conn);
  echo json_encode(['status' => 'success', 'message' => 'Invoice has been deleted successfully.']);
}

if ($action == "single_fetch") {
  $id = (int)($_POST["order_id"] ?? 0);
  $row = q_one($conn, "SELECT order_id, order_receiver_name, order_receiver_address, order_note FROM tbl_orders WHERE order_id = ?", "i", [$id]);
  if (!$row) json_fail(404, 'Not found.');
  $_SESSION['order_id'] = $row['order_id'];

  $update_item_table = [];
  foreach (q_rows($conn, "SELECT * FROM invoice_order_item WHERE order_id = ?", "i", [$id]) as $it) {
    $update_item_table[] = implode("||", [clean_item_field($it['item_name']), $it['order_item_quantity'], $it['order_item_price'], $it['order_item_tax_amount'], $it['order_item_subtotal_amount'], $it['order_item_final_amount']]);
  }
  $_SESSION['update_item_table_temp'] = $update_item_table;

  echo json_encode([
    "order_receiver_name" => $row['order_receiver_name'],
    "order_receiver_address" => $row['order_receiver_address'],
    "order_note" => $row['order_note'],
  ]);
}

if ($action == "update_item") {
  $_SESSION['update_item_table_temp'][] = item_from_post();
}

if ($action == "delete_update_item") {
  if (isset($_SESSION['update_item_table_temp'])) {
    unset($_SESSION['update_item_table_temp'][(int)($_POST['index'] ?? -1)]);
    $_SESSION['update_item_table_temp'] = array_values($_SESSION['update_item_table_temp']);
  }
}

if ($action == "cancel_update") {
  unset($_SESSION['order_id'], $_SESSION['update_item_table_temp'], $_SESSION['update_sub_total'], $_SESSION['update_tax_amount'], $_SESSION['update_total'], $_SESSION['update_order_note']);
}

if ($action == "update_invoice") {
  [$sub, $tax, $tot] = item_totals($_SESSION['update_item_table_temp'] ?? []);
  if (!empty($_SESSION['update_item_table_temp']) && $tot > 0 && !empty($_SESSION['order_id'])) {
    [$cname, $caddr, $cnote] = order_head_from_post();
    $oid = (int)$_SESSION["order_id"];
    $output = ['status' => 'error', 'message' => 'Invoice could not be saved.'];
    mysqli_begin_transaction($conn);
    $ok = q_ok($conn, "UPDATE tbl_orders SET order_last_update_by = ?, order_receiver_name = ?, order_receiver_address = ?, order_total_before_tax = ?, order_total_tax = ?, order_total_after_tax = ?, order_note = ?, order_updated_at = NOW() WHERE order_id = ?",
        "issdddsi", [(int)$_SESSION["user_id"], $cname, $caddr, $sub, $tax, $tot, $cnote, $oid])
      && q_ok($conn, "DELETE FROM invoice_order_item WHERE order_id = ?", "i", [$oid])
      && insert_items($conn, $oid, $_SESSION['update_item_table_temp']);
    if ($ok && mysqli_commit($conn)) {
      $output = ['status' => 'success', 'order_id' => $oid];
    } else {
      mysqli_rollback($conn);
    }
  } else {
    $output = ['status' => 'empty', 'message' => 'Please enter at least one item information.'];
  }
  echo json_encode($output);
  unset($_SESSION['order_id'], $_SESSION['update_item_table_temp'], $_SESSION['update_sub_total'], $_SESSION['update_tax_amount'], $_SESSION['update_total'], $_SESSION['update_order_note']);
}

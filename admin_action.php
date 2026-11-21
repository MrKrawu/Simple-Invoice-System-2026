<?php
require_once "bootstrap.php";
require_once "connection.php";
require_api();

$action = $_POST["action"] ?? '';

if ($action == "add_info") {
  $one = function ($sql) use ($conn) { $r = mysqli_fetch_row(mysqli_query($conn, $sql)); return $r[0]; };
  echo json_encode([
    'total_orders' => $one("SELECT count(order_id) FROM tbl_orders"),
    'total_users'  => $one("SELECT count(user_id) FROM tbl_users"),
    'total_admin'  => $one("SELECT count(user_id) FROM tbl_users WHERE user_role = 'Admin'"),
    'total_income' => $one("SELECT SUM(order_total_after_tax) FROM tbl_orders"),
  ]);
}

if ($action == "latest_invoices") {
  $rows = q_rows($conn, "SELECT order_id, order_receiver_name, order_total_before_tax, order_total_tax, order_total_after_tax, order_created_at FROM tbl_orders ORDER BY order_id DESC LIMIT 5");
  $out = '<div><table class="table table-hover table-condensed table-bordered" id="dataTable"><thead><tr>
    <th>ID</th><th>Customer</th><th>Sub Total</th><th>Tax Amount</th><th>Grand Total</th><th>Created Date</th><th>Action</th>
    </tr></thead><tbody>';
  foreach ($rows as $r) {
    $id = (int)$r['order_id'];
    $out .= '<tr>';
    foreach (['order_id','order_receiver_name','order_total_before_tax','order_total_tax','order_total_after_tax','order_created_at'] as $c) {
      $out .= '<td style="text-align: left;">' . h($r[$c]) . '</td>';
    }
    $out .= '<td style="text-align: left;">
      <a href="print_invoice.php?order_id='.$id.'&output" class="btn btn-info btn-sm"><i class="fas fa-download"></i></a>
      <a href="print_invoice.php?order_id='.$id.'" class="btn btn-primary btn-sm" target="_blank"><i class="fas fa-print"></i></a>
      <a href="update_invoice.php?order_id='.$id.'" class="btn btn-success btn-sm"><i class="fas fa-edit"></i></a>
      </td></tr>';
  }
  echo $out . '</tbody></table></div>';
}

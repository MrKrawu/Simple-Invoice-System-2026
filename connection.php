<?php
// Zugangsdaten: per Umgebungsvariable überschreibbar (SIS_DB_HOST, SIS_DB_USER, SIS_DB_PASS, SIS_DB_NAME).
// Produktiv NICHT mit root/leerem Passwort betreiben.
$servername = getenv('SIS_DB_HOST') ?: 'localhost';
$username   = getenv('SIS_DB_USER') ?: 'root';
$password   = getenv('SIS_DB_PASS') !== false ? getenv('SIS_DB_PASS') : '';
$dbname     = getenv('SIS_DB_NAME') ?: 'egm_simple_invoice';

mysqli_report(MYSQLI_REPORT_OFF);
$conn = mysqli_connect($servername, $username, $password, $dbname);

if (!$conn) {
  error_log('DB connection failed: ' . mysqli_connect_error());
  http_response_code(500);
  die('Database connection failed.');
}
mysqli_set_charset($conn, 'utf8mb4');
$connect = $conn;

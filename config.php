<?php
// Direct MySQL configuration for this SCADA deployment.
$host = 'localhost';
$username = 'root';
$password = 'Arun@811001';
$dbname = 'vinoba-renewbale';

$conn = null;
$dbError = null;

try {
    $conn = new mysqli($host, $username, $password, $dbname);
    $conn->set_charset('utf8mb4');
} catch (Throwable $e) {
    $dbError = 'Database connection failed for shared database: ' . $dbname;
    error_log('[config] MySQL connection failed: ' . $e->getMessage());
    $conn = null;
}
?>

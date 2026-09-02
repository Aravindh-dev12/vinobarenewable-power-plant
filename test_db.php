<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');
header('Content-Type: text/plain; charset=utf-8');

require_once __DIR__ . '/config.php';

echo "=== SCADA DB CONNECTION TEST ===\n\n";
if ($conn->connect_error) {
    echo "FAIL: " . $conn->connect_error . "\n";
    exit;
}

echo "OK - connected to database: " . $dbname . "\n";
echo "Server: " . $conn->host_info . "\n";
echo "MySQL: " . $conn->server_info . "\n\n";

$required = ['users', 'plants', 'inverter_readings', 'vcb_readings', 'transformer_readings', 'weather_readings'];
foreach ($required as $table) {
    $safe = $conn->real_escape_string($table);
    $r = $conn->query("SHOW TABLES LIKE '$safe'");
    echo ($r && $r->num_rows ? '[OK] ' : '[MISSING] ') . $table . "\n";
}

echo "\nConfigured database can be set with SCADA_DB_NAME.\n";
echo "Default database for new installations: solar_scada\n";
?>

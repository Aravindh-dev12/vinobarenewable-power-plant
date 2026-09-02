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

echo "OK - connected to shared database: " . $dbname . "\n";
echo "Expected database: vinoba-renewbale\n";
echo "Plants sharing this DB: vinoba-1, ssv\n";
echo "Server: " . $conn->host_info . "\n";
echo "MySQL: " . $conn->server_info . "\n\n";

$required = ['users', 'plants', 'inverter_readings', 'inverter_strings', 'vcb_readings', 'transformer_readings', 'weather_readings', 'telemetry_history'];
foreach ($required as $table) {
    $safe = $conn->real_escape_string($table);
    $r = $conn->query("SHOW TABLES LIKE '$safe'");
    echo ($r && $r->num_rows ? '[OK] ' : '[MISSING] ') . $table . "\n";
}

echo "\nBoth plants use these same tables; telemetry is separated by plant_id.\n";
echo "Configured database can still be overridden with SCADA_DB_NAME when required.\n";
?>

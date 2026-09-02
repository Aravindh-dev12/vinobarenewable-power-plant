<?php
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

error_reporting(E_ALL);
ini_set('display_errors', '1');
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/plant_config.php';

echo "=== VINoba Renewable SCADA Diagnostic ===\n\n";
echo "PHP: " . PHP_VERSION . "\n";
echo "Expected DB: {$dbname}\n";
echo "Canonical plants: " . implode(', ', array_keys(plant_catalog())) . "\n\n";

if (!isset($conn) || !($conn instanceof mysqli)) {
    echo "[FAIL] Database connection unavailable.\n";
    echo "       Configure SCADA_DB_* environment variables or config.local.php.\n";
    echo "       Example: copy config.local.example.php to config.local.php and set the real MySQL password.\n";
    exit(1);
}

echo "[OK] Database connection successful.\n";
$r = $conn->query('SELECT DATABASE() AS db');
$currentDb = $r ? ($r->fetch_assoc()['db'] ?? '') : '';
echo "[OK] Connected database: {$currentDb}\n\n";

$tables = ['plants','users','vcb_readings','inverter_readings','inverter_strings','transformer_readings','weather_readings','telemetry_history'];
echo "--- Tables ---\n";
foreach ($tables as $table) {
    $safe = $conn->real_escape_string($table);
    $exists = $conn->query("SHOW TABLES LIKE '{$safe}'");
    if (!$exists || $exists->num_rows === 0) {
        echo "[MISSING] {$table}\n";
        continue;
    }
    $count = $conn->query("SELECT COUNT(*) AS c FROM `{$table}`");
    $rows = $count ? (int)($count->fetch_assoc()['c'] ?? 0) : 0;
    echo "[OK] {$table} ({$rows} rows)\n";
}

echo "\n--- Plants ---\n";
if ($res = $conn->query("SELECT id,name,service_number FROM plants ORDER BY id")) {
    if ($res->num_rows === 0) echo "[MISSING] No plant rows found.\n";
    while ($row = $res->fetch_assoc()) {
        echo "[OK] {$row['id']} | {$row['name']} | {$row['service_number']}\n";
    }
}

echo "\n--- Login Users ---\n";
if ($res = $conn->query("SELECT email,role,plant_id FROM users ORDER BY role,email")) {
    if ($res->num_rows === 0) echo "[MISSING] No users found. Run: php setup_users.php\n";
    while ($row = $res->fetch_assoc()) {
        echo "[OK] {$row['email']} | {$row['role']} | {$row['plant_id']}\n";
    }
}

echo "\n--- Latest Telemetry ---\n";
foreach (['inverter_readings','vcb_readings','transformer_readings','weather_readings'] as $table) {
    $exists = $conn->query("SHOW TABLES LIKE '{$table}'");
    if (!$exists || $exists->num_rows === 0) continue;
    $res = $conn->query("SELECT plant_id,MAX(recorded_at) AS latest FROM `{$table}` GROUP BY plant_id ORDER BY plant_id");
    if (!$res || $res->num_rows === 0) {
        echo "[NO DATA] {$table}\n";
        continue;
    }
    while ($row = $res->fetch_assoc()) {
        echo "[DATA] {$table} | {$row['plant_id']} | {$row['latest']}\n";
    }
}

echo "\n--- SCADA WebSocket Source ---\n";
$wsHost = getenv('SCADA_WS_HOST') ?: '127.0.0.1';
$wsPort = (int)(getenv('SCADA_WS_PORT') ?: 5000);
$errno = 0; $errstr = '';
$sock = @fsockopen($wsHost, $wsPort, $errno, $errstr, 3);
if ($sock) {
    echo "[OK] TCP connection to {$wsHost}:{$wsPort}\n";
    fclose($sock);
} else {
    echo "[FAIL] Cannot connect to {$wsHost}:{$wsPort} - {$errstr} ({$errno})\n";
}

echo "\n--- Collector Process ---\n";
$output = shell_exec("ps aux | grep '[w]s_bridge' 2>/dev/null");
if ($output && trim($output) !== '') echo "[OK] ws_bridge process found.\n";
else echo "[NOT RUNNING] ws_bridge process not found.\n";

echo "\n=== Diagnostic Complete ===\n";

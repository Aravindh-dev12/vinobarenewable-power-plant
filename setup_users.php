<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/plant_config.php';
header('Content-Type: text/plain; charset=utf-8');

$queries = [
    "ALTER TABLE users ADD COLUMN IF NOT EXISTS plant_id VARCHAR(50) DEFAULT ''",
    "ALTER TABLE users ADD COLUMN IF NOT EXISTS auth_token VARCHAR(128) DEFAULT NULL",
    "ALTER TABLE users ADD COLUMN IF NOT EXISTS role VARCHAR(20) DEFAULT 'user'"
];
foreach ($queries as $q) {
    try { $conn->query($q); echo "OK: $q\n"; }
    catch (Throwable $e) { echo "SKIP: " . $e->getMessage() . "\n"; }
}

$migrations = [
    ['vinoba-1', ['vinoba-velliyanai', 'vinoba']],
    ['ssv', ['makkalpower', 'makkal-power', 'anushyam']]
];
foreach ($migrations as [$newId, $oldIds]) {
    $quoted = array_map(fn($v) => "'" . $conn->real_escape_string($v) . "'", $oldIds);
    $sql = "UPDATE users SET plant_id='" . $conn->real_escape_string($newId) . "' WHERE plant_id IN (" . implode(',', $quoted) . ")";
    $conn->query($sql);
    echo "Migrated users to $newId: " . $conn->affected_rows . "\n";
}

echo "\nCanonical plants:\n";
foreach (plant_catalog() as $p) {
    echo "- {$p['id']} | {$p['name']} | Service Number {$p['service_number']}\n";
}
echo "\nDone. Existing telemetry rows are not rewritten; new SCADA data is stored under vinoba-1 and ssv.\n";
?>

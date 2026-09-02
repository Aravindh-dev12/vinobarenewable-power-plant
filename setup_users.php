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

// Remove assignments that are not one of the two current SCADA plants.
$validIds = array_keys(plant_catalog());
$quoted = array_map(fn($v) => "'" . $conn->real_escape_string($v) . "'", $validIds);
$conn->query("UPDATE users SET plant_id='' WHERE role<>'admin' AND plant_id<>'' AND plant_id NOT IN (" . implode(',', $quoted) . ")");
echo "Cleared non-current plant assignments: " . $conn->affected_rows . "\n";

echo "\nCurrent plants:\n";
foreach (plant_catalog() as $p) {
    echo "- {$p['id']} | {$p['name']} | Service Number {$p['service_number']}\n";
}
echo "\nDone. Assign every site user to vinoba-1 or ssv from the Admin page/database.\n";
?>

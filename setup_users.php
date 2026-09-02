<?php
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/plant_config.php';

if (!isset($conn) || !($conn instanceof mysqli)) {
    fwrite(STDERR, "Database connection failed. Configure config.local.php or SCADA_DB_* environment variables first.\n");
    exit(1);
}

$conn->query("CREATE TABLE IF NOT EXISTS plants (
    id VARCHAR(50) PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    service_number VARCHAR(100) NOT NULL,
    capacity DECIMAL(10,2) NOT NULL DEFAULT 0,
    location VARCHAR(255) DEFAULT '',
    theme VARCHAR(50) DEFAULT 'emerald'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

$conn->query("CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role VARCHAR(20) NOT NULL DEFAULT 'user',
    plant_id VARCHAR(50) DEFAULT '',
    auth_token VARCHAR(128) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_users_plant (plant_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

foreach ([
    "ALTER TABLE users ADD COLUMN IF NOT EXISTS plant_id VARCHAR(50) DEFAULT ''",
    "ALTER TABLE users ADD COLUMN IF NOT EXISTS auth_token VARCHAR(128) DEFAULT NULL",
    "ALTER TABLE users ADD COLUMN IF NOT EXISTS role VARCHAR(20) DEFAULT 'user'"
] as $sql) {
    $conn->query($sql);
}

$catalog = plant_catalog();
$plantStmt = $conn->prepare("INSERT INTO plants (id,name,service_number,capacity,location,theme)
    VALUES (?,?,?,?,?,?)
    ON DUPLICATE KEY UPDATE name=VALUES(name),service_number=VALUES(service_number),capacity=VALUES(capacity),location=VALUES(location),theme=VALUES(theme)");
foreach ($catalog as $p) {
    $id = $p['id'];
    $name = $p['name'];
    $service = $p['service_number'];
    $capacity = (float)$p['capacity'];
    $location = $p['location'];
    $theme = $id === 'vinoba-1' ? 'violet' : 'emerald';
    $plantStmt->bind_param('sssdss', $id, $name, $service, $capacity, $location, $theme);
    $plantStmt->execute();
}
$plantStmt->close();

$users = [
    ['admin@scada.com', 'admin@123', 'admin', ''],
    ['vinobarenew@scada.com', 'vinoba@123', 'user', 'vinoba-1'],
    ['ssvgreen@scada.com', 'ssv@123', 'user', 'ssv'],
];

$find = $conn->prepare('SELECT id FROM users WHERE email=? LIMIT 1');
$update = $conn->prepare('UPDATE users SET password=?, role=?, plant_id=?, auth_token=NULL WHERE id=?');
$insert = $conn->prepare('INSERT INTO users (email,password,role,plant_id) VALUES (?,?,?,?)');

foreach ($users as [$email, $plainPassword, $role, $plantId]) {
    $hash = password_hash($plainPassword, PASSWORD_DEFAULT);
    $find->bind_param('s', $email);
    $find->execute();
    $find->store_result();
    $existingId = null;
    if ($find->num_rows > 0) {
        $find->bind_result($existingId);
        $find->fetch();
    }
    $find->free_result();

    if ($existingId !== null) {
        $id = (int)$existingId;
        $update->bind_param('sssi', $hash, $role, $plantId, $id);
        $update->execute();
        echo "Updated: {$email}\n";
    } else {
        $insert->bind_param('ssss', $email, $hash, $role, $plantId);
        $insert->execute();
        echo "Inserted: {$email}\n";
    }
}

$find->close();
$update->close();
$insert->close();

echo "\nLogin users are ready in shared database: {$dbname}\n";
echo "Admin:  admin@scada.com / admin@123\n";
echo "Vinoba: vinobarenew@scada.com / vinoba@123\n";
echo "SSV:    ssvgreen@scada.com / ssv@123\n";

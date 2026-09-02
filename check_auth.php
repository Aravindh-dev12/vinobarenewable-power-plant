<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/plant_config.php';

if (!isset($conn) || !($conn instanceof mysqli)) {
    http_response_code(503);
    echo '<!doctype html><html><body style="font-family:Arial,sans-serif;padding:24px"><h2>Dashboard temporarily unavailable</h2><p>Database connection is not configured on this server.</p></body></html>';
    exit;
}

$token = isset($_GET['token']) ? trim((string)$_GET['token']) : '';
$user = null;

if ($token !== '') {
    $stmt = $conn->prepare('SELECT * FROM users WHERE auth_token = ? LIMIT 1');
    if ($stmt) {
        $stmt->bind_param('s', $token);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res && $res->num_rows) $user = $res->fetch_assoc();
        $stmt->close();
    }
}

if (!$user) {
    header('Location: index.php');
    exit;
}

migrate_user_plant_alias($conn, $user);

$currentPage = basename($_SERVER['PHP_SELF'] ?? 'home.php');
$isAdmin = (($user['role'] ?? 'user') === 'admin');
$allowAllPlants = $isAdmin && $currentPage === 'reports.php';
$rawPlant = isset($_GET['plant']) ? trim((string)$_GET['plant']) : '';

if ($rawPlant === 'all' && $allowAllPlants) {
    $currentPlant = 'all';
} else {
    $currentPlant = normalize_plant_id($rawPlant);
    if ($currentPlant !== '' && !is_valid_plant_id($currentPlant)) {
        $currentPlant = '';
    }
}

if (!$isAdmin) {
    $assigned = normalize_plant_id($user['plant_id'] ?? '');
    if (!is_valid_plant_id($assigned)) $assigned = 'vinoba-1';
    $user['plant_id'] = $assigned;

    if ($currentPlant !== $assigned) {
        $query = $_GET;
        $query['plant'] = $assigned;
        header('Location: ' . $currentPage . '?' . http_build_query($query));
        exit;
    }
} else {
    if ($currentPage !== 'admin.php' && $currentPlant === '') {
        $query = $_GET;
        $query['plant'] = 'vinoba-1';
        header('Location: ' . $currentPage . '?' . http_build_query($query));
        exit;
    }

    if ($currentPlant === 'all' && !$allowAllPlants) {
        $query = $_GET;
        $query['plant'] = 'vinoba-1';
        header('Location: ' . $currentPage . '?' . http_build_query($query));
        exit;
    }
}
?>

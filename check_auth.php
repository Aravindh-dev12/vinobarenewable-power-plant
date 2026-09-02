<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/plant_config.php';

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
$rawPlant = isset($_GET['plant']) ? trim((string)$_GET['plant']) : '';
$currentPlant = $rawPlant === 'all' ? 'all' : normalize_plant_id($rawPlant);

if ($rawPlant !== '' && $rawPlant !== 'all' && $currentPlant !== $rawPlant && is_valid_plant_id($currentPlant)) {
    $query = $_GET;
    $query['plant'] = $currentPlant;
    header('Location: ' . $currentPage . '?' . http_build_query($query));
    exit;
}

if (($user['role'] ?? 'user') !== 'admin') {
    $assigned = normalize_plant_id($user['plant_id'] ?? '');
    if (!is_valid_plant_id($assigned)) $assigned = 'vinoba-1';
    $user['plant_id'] = $assigned;

    if ($currentPlant === '' || $currentPlant === 'all') {
        $query = $_GET;
        $query['plant'] = $assigned;
        header('Location: ' . $currentPage . '?' . http_build_query($query));
        exit;
    }
    if ($currentPlant !== $assigned) {
        header('Location: home.php?plant=' . urlencode($assigned) . '&token=' . urlencode($token));
        exit;
    }
} else {
    if ($currentPlant === '' && $currentPage !== 'admin.php') {
        $query = $_GET;
        $query['plant'] = 'vinoba-1';
        header('Location: ' . $currentPage . '?' . http_build_query($query));
        exit;
    }
}
?>

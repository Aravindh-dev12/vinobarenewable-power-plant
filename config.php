<?php
// Database secrets must stay on the server, not in GitHub.
// Priority: environment variables -> config.local.php -> safe defaults.
$localConfig = [];
$localConfigPath = __DIR__ . '/config.local.php';
if (is_file($localConfigPath)) {
    $loaded = require $localConfigPath;
    if (is_array($loaded)) $localConfig = $loaded;
}

$envHost = getenv('SCADA_DB_HOST');
$envUser = getenv('SCADA_DB_USER');
$envPassword = getenv('SCADA_DB_PASSWORD');
$envName = getenv('SCADA_DB_NAME');

$host = ($envHost !== false && $envHost !== '') ? $envHost : (string)($localConfig['host'] ?? 'localhost');
$username = ($envUser !== false && $envUser !== '') ? $envUser : (string)($localConfig['username'] ?? 'root');
$password = ($envPassword !== false) ? $envPassword : (string)($localConfig['password'] ?? '');
$dbname = ($envName !== false && $envName !== '') ? $envName : (string)($localConfig['dbname'] ?? 'vinoba-renewbale');

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

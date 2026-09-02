<?php
$host = getenv('SCADA_DB_HOST') ?: 'localhost';
$username = getenv('SCADA_DB_USER') ?: 'root';
$password = getenv('SCADA_DB_PASSWORD') ?: 'Arun@811001';
$dbname = getenv('SCADA_DB_NAME') ?: 'solar_scada';

$conn = new mysqli($host, $username, $password);

if (!$conn->connect_error) {
    $selected = @$conn->select_db($dbname);

    // Preserve existing installations without keeping a legacy database name
    // in source code. If the configured database is absent, discover the SCADA
    // schema by its required tables.
    if (!$selected) {
        $systemDatabases = ['information_schema', 'mysql', 'performance_schema', 'sys'];
        $dbResult = @$conn->query('SHOW DATABASES');
        if ($dbResult) {
            while ($row = $dbResult->fetch_row()) {
                $candidate = (string)($row[0] ?? '');
                if ($candidate === '' || in_array($candidate, $systemDatabases, true)) continue;
                if (!@$conn->select_db($candidate)) continue;

                $hasUsers = @$conn->query("SHOW TABLES LIKE 'users'");
                $hasInverters = @$conn->query("SHOW TABLES LIKE 'inverter_readings'");
                if ($hasUsers && $hasUsers->num_rows && $hasInverters && $hasInverters->num_rows) {
                    $dbname = $candidate;
                    $selected = true;
                    break;
                }
            }
        }
    }

    if ($selected) $conn->set_charset('utf8mb4');
}

if ($conn->connect_error || empty($selected)) {
    $isApi = !empty($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'json') !== false;
    if ($isApi || (defined('JSON_RESPONSE') && JSON_RESPONSE)) {
        header('Content-Type: application/json');
        die(json_encode(['status' => 'error', 'message' => 'DB connection failed']));
    }
    $dbError = 'Database connection failed. Please check config.php credentials or SCADA_DB_NAME.';
}
?>

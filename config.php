<?php
$host = getenv('SCADA_DB_HOST') ?: 'localhost';
$username = getenv('SCADA_DB_USER') ?: 'root';
$password = getenv('SCADA_DB_PASSWORD') ?: 'Arun@811001';
$dbname = getenv('SCADA_DB_NAME') ?: 'vinoba-renewbale';

// Both SCADA plants (vinoba-1 and ssv) use this one shared database.
// Every telemetry table stores both plants together and separates rows by plant_id.
$conn = new mysqli($host, $username, $password, $dbname);

if (!$conn->connect_error) {
    $conn->set_charset('utf8mb4');
}

if ($conn->connect_error) {
    $isApi = !empty($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'json') !== false;
    if ($isApi || (defined('JSON_RESPONSE') && JSON_RESPONSE)) {
        header('Content-Type: application/json');
        die(json_encode(['status' => 'error', 'message' => 'DB connection failed']));
    }
    $dbError = 'Database connection failed. Expected shared database: ' . $dbname;
}
?>

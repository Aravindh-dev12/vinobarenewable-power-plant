<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');
ini_set('display_errors', '0');
error_reporting(E_ALL);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/plant_config.php';

function liveJson(int $code, array $payload): never {
    http_response_code($code);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

function bearerToken(): string {
    $header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    if ($header === '' && function_exists('getallheaders')) {
        $headers = getallheaders();
        $header = $headers['Authorization'] ?? $headers['authorization'] ?? '';
    }
    return preg_match('/Bearer\s+(\S+)/i', $header, $m) ? $m[1] : '';
}

function readExact($socket, int $length, float $deadline): ?string {
    $data = '';
    while (strlen($data) < $length && microtime(true) < $deadline) {
        $chunk = @fread($socket, $length - strlen($data));
        if ($chunk === false) return null;
        if ($chunk === '') { usleep(1000); continue; }
        $data .= $chunk;
    }
    return strlen($data) === $length ? $data : null;
}

function sendTextFrame($socket, string $payload): bool {
    $len = strlen($payload);
    $mask = random_bytes(4);
    $frame = chr(0x81);
    if ($len <= 125) $frame .= chr(0x80 | $len);
    elseif ($len <= 65535) $frame .= chr(0x80 | 126) . pack('n', $len);
    else $frame .= chr(0x80 | 127) . pack('NN', 0, $len);
    $frame .= $mask;
    for ($i = 0; $i < $len; $i++) $frame .= $payload[$i] ^ $mask[$i % 4];
    return @fwrite($socket, $frame) !== false;
}

function readFrame($socket, float $deadline): ?array {
    $head = readExact($socket, 2, $deadline);
    if ($head === null) return null;
    $b1 = ord($head[0]);
    $b2 = ord($head[1]);
    $opcode = $b1 & 0x0f;
    $masked = (bool)($b2 & 0x80);
    $len = $b2 & 0x7f;
    if ($len === 126) {
        $extra = readExact($socket, 2, $deadline);
        if ($extra === null) return null;
        $len = unpack('n', $extra)[1];
    } elseif ($len === 127) {
        $extra = readExact($socket, 8, $deadline);
        if ($extra === null) return null;
        $parts = unpack('N2', $extra);
        $len = ($parts[1] << 32) | $parts[2];
    }
    if ($len > 1048576) return null;
    $mask = $masked ? readExact($socket, 4, $deadline) : null;
    $payload = $len ? readExact($socket, $len, $deadline) : '';
    if ($payload === null) return null;
    if ($masked && $mask !== null) {
        for ($i = 0; $i < $len; $i++) $payload[$i] = $payload[$i] ^ $mask[$i % 4];
    }
    return ['opcode' => $opcode, 'payload' => $payload];
}

function openWebSocketTarget(array $target): ?array {
    $host = (string)$target['host'];
    $port = (int)$target['port'];
    $transport = (string)$target['transport'];
    $hostHeader = (string)($target['host_header'] ?? ($host . ':' . $port));
    $path = (string)($target['path'] ?? '/');
    $context = null;

    if ($transport === 'tls') {
        $context = stream_context_create([
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
                'peer_name' => $host,
                'SNI_enabled' => true,
                'allow_self_signed' => false,
            ],
        ]);
    }

    $errno = 0;
    $errstr = '';
    $remote = $transport . '://' . $host . ':' . $port;
    $socket = @stream_socket_client(
        $remote,
        $errno,
        $errstr,
        1.8,
        STREAM_CLIENT_CONNECT,
        $context
    );
    if (!$socket) return null;

    stream_set_timeout($socket, 2);
    $key = base64_encode(random_bytes(16));
    $request = "GET {$path} HTTP/1.1\r\n"
        . "Host: {$hostHeader}\r\n"
        . "Upgrade: websocket\r\n"
        . "Connection: Upgrade\r\n"
        . "Sec-WebSocket-Key: {$key}\r\n"
        . "Sec-WebSocket-Version: 13\r\n"
        . "\r\n";
    @fwrite($socket, $request);

    $response = '';
    $handshakeDeadline = microtime(true) + 1.8;
    while (microtime(true) < $handshakeDeadline) {
        $line = @fgets($socket);
        if ($line === false) { usleep(1000); continue; }
        $response .= $line;
        if ($line === "\r\n") break;
    }
    if (strpos($response, ' 101 ') === false) {
        @fclose($socket);
        return null;
    }

    return [$socket, (string)$target['source']];
}

if (!isset($conn) || !($conn instanceof mysqli) || $conn->connect_error) {
    liveJson(500, ['success' => false, 'message' => 'Database unavailable']);
}

$token = trim((string)($_GET['token'] ?? bearerToken()));
if ($token === '') liveJson(401, ['success' => false, 'message' => 'Authentication required']);

$stmt = $conn->prepare('SELECT id,email,role,plant_id FROM users WHERE auth_token=? LIMIT 1');
$stmt->bind_param('s', $token);
$stmt->execute();
$stmt->bind_result($userId, $email, $role, $assignedPlant);
if (!$stmt->fetch()) {
    $stmt->close();
    liveJson(401, ['success' => false, 'message' => 'Invalid session']);
}
$stmt->close();

if ($role === 'admin') {
    $plant = normalize_plant_id($_GET['plant'] ?? 'vinoba-1');
} else {
    $plant = normalize_plant_id($assignedPlant ?? '');
}
if (!is_valid_plant_id($plant)) liveJson(403, ['success' => false, 'message' => 'Plant access unavailable']);

/*
 * Use the exact production WebSocket endpoint that browser pages use first.
 * Internal/direct targets are only fallbacks for installations where PHP cannot
 * resolve or establish TLS to the public reverse-proxy endpoint.
 */
$targets = [
    [
        'transport' => 'tls',
        'host' => 'vinobasolar.scadahub.in',
        'port' => 5001,
        'path' => '/',
        'host_header' => 'vinobasolar.scadahub.in:5001',
        'source' => 'wss://vinobasolar.scadahub.in:5001',
    ],
];

$configuredHost = trim((string)(getenv('SCADA_WS_HOST') ?: ''));
$configuredPort = (int)(getenv('SCADA_WS_PORT') ?: 5000);
if ($configuredHost !== '') {
    $targets[] = [
        'transport' => 'tcp',
        'host' => $configuredHost,
        'port' => $configuredPort,
        'path' => '/',
        'source' => 'ws://' . $configuredHost . ':' . $configuredPort,
    ];
}
$targets[] = ['transport' => 'tcp', 'host' => '127.0.0.1', 'port' => 5000, 'path' => '/', 'source' => 'ws://127.0.0.1:5000'];
$targets[] = ['transport' => 'tcp', 'host' => '161.97.87.75', 'port' => 5000, 'path' => '/', 'source' => 'ws://161.97.87.75:5000'];

$socket = null;
$source = '';
foreach ($targets as $target) {
    $opened = openWebSocketTarget($target);
    if ($opened !== null) {
        [$socket, $source] = $opened;
        break;
    }
}
if (!$socket) liveJson(503, ['success' => false, 'message' => 'Live SCADA source unavailable', 'messages' => []]);

sendTextFrame($socket, json_encode(['type' => 'subscribe', 'unit_id' => $plant], JSON_UNESCAPED_SLASHES));
stream_set_blocking($socket, false);
$messages = [];
$deadline = microtime(true) + 1.8;

while (microtime(true) < $deadline && count($messages) < 50) {
    $read = [$socket];
    $write = null;
    $except = null;
    $remaining = max(0, $deadline - microtime(true));
    $sec = (int)$remaining;
    $usec = (int)(($remaining - $sec) * 1000000);
    $ready = @stream_select($read, $write, $except, $sec, $usec);
    if (!$ready) break;
    $frame = readFrame($socket, $deadline);
    if ($frame === null) break;
    if ($frame['opcode'] === 0x8) break;
    if ($frame['opcode'] !== 0x1) continue;
    $data = json_decode($frame['payload'], true);
    if (!is_array($data) || normalize_plant_id($data['unit_id'] ?? '') !== $plant) continue;
    unset($data['unit_id']);
    $messages[] = $data;
}
@fclose($socket);

liveJson(200, [
    'success' => true,
    'messages' => $messages,
    'received' => count($messages),
    'plant_id' => $plant,
    'source' => $source,
]);

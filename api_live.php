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
    $socket = @stream_socket_client(
        $transport . '://' . $host . ':' . $port,
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
        . "Sec-WebSocket-Version: 13\r\n\r\n";
    @fwrite($socket, $request);

    $response = '';
    $deadline = microtime(true) + 1.8;
    while (microtime(true) < $deadline) {
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

function incomingPlantId(array $message): string {
    $candidates = [
        $message['unit_id'] ?? null,
        $message['unitId'] ?? null,
        $message['plant_id'] ?? null,
        $message['plantId'] ?? null,
        $message['plant'] ?? null,
    ];
    $nested = $message['data'] ?? null;
    if (is_array($nested)) {
        $candidates[] = $nested['unit_id'] ?? null;
        $candidates[] = $nested['unitId'] ?? null;
        $candidates[] = $nested['plant_id'] ?? null;
        $candidates[] = $nested['plantId'] ?? null;
        $candidates[] = $nested['plant'] ?? null;
    }
    foreach ($candidates as $value) {
        $normalized = normalize_plant_id($value ?? '');
        if ($normalized !== '') return $normalized;
    }
    return '';
}

function normalizeLiveMessage(array $message, string $plant): array {
    $nested = $message['data'] ?? null;
    if (is_array($nested) && (
        isset($nested['values']) || isset($nested['device']) || isset($nested['deviceName']) ||
        isset($nested['task']) || isset($nested['virtualTags'])
    )) {
        $message = array_merge($message, $nested);
    }

    if (!isset($message['device']) && isset($message['deviceName'])) {
        $message['device'] = $message['deviceName'];
    }
    if (!isset($message['values']) && isset($message['tags']) && is_array($message['tags'])) {
        $message['values'] = $message['tags'];
    }

    $message['unit_id'] = $plant;
    return $message;
}

function collectFromTarget(array $target, string $plant): ?array {
    $opened = openWebSocketTarget($target);
    if ($opened === null) return null;

    [$socket, $source] = $opened;
    sendTextFrame($socket, json_encode([
        'type' => 'subscribe',
        'unit_id' => $plant,
    ], JSON_UNESCAPED_SLASHES));

    stream_set_blocking($socket, false);
    $messages = [];
    $deadline = microtime(true) + 2.2;

    while (microtime(true) < $deadline && count($messages) < 60) {
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
        if (!is_array($data)) continue;

        $incoming = incomingPlantId($data);
        if ($incoming !== '' && $incoming !== $plant) continue;

        $messages[] = normalizeLiveMessage($data, $plant);
    }

    @fclose($socket);
    return ['source' => $source, 'messages' => $messages];
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

$plant = $role === 'admin'
    ? normalize_plant_id($_GET['plant'] ?? 'vinoba-1')
    : normalize_plant_id($assignedPlant ?? '');
if (!is_valid_plant_id($plant)) {
    liveJson(403, ['success' => false, 'message' => 'Plant access unavailable']);
}

$targets = [
    ['transport' => 'tcp', 'host' => '161.97.87.75', 'port' => 5000, 'path' => '/', 'source' => 'ws://161.97.87.75:5000'],
];

$configuredHost = trim((string)(getenv('SCADA_WS_HOST') ?: ''));
$configuredPort = (int)(getenv('SCADA_WS_PORT') ?: 5000);
if ($configuredHost !== '' && $configuredHost !== '161.97.87.75') {
    $targets[] = [
        'transport' => 'tcp',
        'host' => $configuredHost,
        'port' => $configuredPort,
        'path' => '/',
        'source' => 'ws://' . $configuredHost . ':' . $configuredPort,
    ];
}

$targets[] = ['transport' => 'tcp', 'host' => '127.0.0.1', 'port' => 5000, 'path' => '/', 'source' => 'ws://127.0.0.1:5000'];
$targets[] = [
    'transport' => 'tls',
    'host' => 'vinobasolar.scadahub.in',
    'port' => 5001,
    'path' => '/',
    'host_header' => 'vinobasolar.scadahub.in:5001',
    'source' => 'wss://vinobasolar.scadahub.in:5001',
];

$firstConnectedSource = '';
foreach ($targets as $target) {
    $result = collectFromTarget($target, $plant);
    if ($result === null) continue;
    if ($firstConnectedSource === '') $firstConnectedSource = $result['source'];

    if (count($result['messages']) === 0) continue;

    liveJson(200, [
        'success' => true,
        'messages' => $result['messages'],
        'received' => count($result['messages']),
        'plant_id' => $plant,
        'source' => $result['source'],
    ]);
}

if ($firstConnectedSource !== '') {
    liveJson(200, [
        'success' => true,
        'messages' => [],
        'received' => 0,
        'plant_id' => $plant,
        'source' => $firstConnectedSource,
        'message' => 'Connected but no telemetry received during snapshot window',
    ]);
}

liveJson(503, [
    'success' => false,
    'message' => 'Live SCADA source unavailable',
    'messages' => [],
    'plant_id' => $plant,
]);

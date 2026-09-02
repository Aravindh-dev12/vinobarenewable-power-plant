<?php
error_reporting(0);
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }
date_default_timezone_set('Asia/Kolkata');
require_once __DIR__ . '/plant_config.php';

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) { http_response_code(400); echo json_encode(['success'=>false,'error'=>'Invalid JSON']); exit; }

$plantId = normalize_plant_id($input['plantId'] ?? '');
$task = (string)($input['task'] ?? '');
$device = (string)($input['device'] ?? '');
$deviceId = (string)($input['deviceId'] ?? '');
$tag = (string)($input['tag'] ?? '');
$value = $input['value'] ?? '';
$level = (string)($input['level'] ?? '');
$channels = is_array($input['channels'] ?? null) ? $input['channels'] : [];
$timestamp = (string)($input['timestamp'] ?? date('Y-m-d H:i:s'));
$telegram = is_array($input['telegram'] ?? null) ? $input['telegram'] : null;
$logOnly = (bool)($input['logOnly'] ?? false);

if (!is_valid_plant_id($plantId) || $tag === '' || $level === '') {
    http_response_code(422);
    echo json_encode(['success'=>false,'error'=>'Invalid plant or missing required fields']);
    exit;
}

$plant = plant_info($plantId);
$logLine = sprintf("[%s] Plant:%s Task:%s Device:%s(%s) Tag:%s Value:%s Level:%s\n", $timestamp, $plantId, $task, $device, $deviceId, $tag, (string)$value, $level);
file_put_contents(__DIR__ . '/alert.log', $logLine, FILE_APPEND);

if ($level === '3' || $logOnly) {
    echo json_encode(['success'=>true,'message'=>'Logged only']);
    exit;
}

if (in_array('telegram', $channels, true) && $telegram) {
    $apiKey = trim((string)($telegram['apiKey'] ?? ''));
    $chatId = trim((string)($telegram['chatId'] ?? ''));
    if ($apiKey !== '' && $chatId !== '') {
        $msg = "🚨 *SOLAR SCADA ALERT*\n"
             . "*Plant:* {$plant['name']}\n"
             . "*Service Number:* {$plant['service_number']}\n"
             . "*SCADA ID:* {$plantId}\n"
             . "*Task:* {$task}\n"
             . "*Device:* {$device} ({$deviceId})\n"
             . "*Tag:* {$tag}\n"
             . "*Value:* {$value}\n"
             . "*Level:* {$level}\n"
             . "*Time:* {$timestamp}";
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => "https://api.telegram.org/bot{$apiKey}/sendMessage",
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_POSTFIELDS => json_encode(['chat_id'=>$chatId,'text'=>$msg,'parse_mode'=>'Markdown']),
            CURLOPT_TIMEOUT => 5
        ]);
        curl_exec($ch);
        $err = curl_error($ch);
        curl_close($ch);
        if ($err) { echo json_encode(['success'=>false,'error'=>$err]); exit; }
    }
}

echo json_encode(['success'=>true,'message'=>'Alert processed']);
?>

<?php
require_once '../config.php';
require_once 'iflytek_helper.php';

header('Content-Type: application/json');

$type = $_GET['type'] ?? 'stt';

if ($type === 'stt') {
    echo json_encode([
        'url' => iFlytekHelper::getSttUrl(),
        'app_id' => IFLYTEK_APPID
    ]);
} elseif ($type === 'tts') {
    echo json_encode([
        'url' => iFlytekHelper::getTtsUrl(),
        'app_id' => IFLYTEK_APPID
    ]);
} else {
    echo json_encode(['error' => 'Invalid type']);
}

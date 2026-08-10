<?php
require_once __DIR__ . '/../config.php';
session_start();

header('Content-Type: application/json');

$raw = file_get_contents('php://input');
$input = json_decode($raw, true);
if (!is_array($input) || !isset($input['image'])) {
    echo json_encode(['status' => 'error', 'message' => 'No image data received']);
    exit;
}

$img = trim((string)$input['image']);
if ($img === '') {
    echo json_encode(['status' => 'error', 'message' => 'Image payload empty']);
    exit;
}

if (preg_match('/^data:image\/(jpeg|jpg|png|webp);base64,/', $img)) {
    $img = preg_replace('/^data:image\/(jpeg|jpg|png|webp);base64,/', '', $img);
}

$img = str_replace(' ', '+', $img);
$data = base64_decode($img, true);
if ($data === false || strlen($data) < 2048) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid image payload']);
    exit;
}

$folder = '../img/captures/';
if (!file_exists($folder)) {
    mkdir($folder, 0777, true);
}

$fileName = 'capture_' . date('Ymd_His') . '_' . bin2hex(random_bytes(3)) . '.jpg';
$filePath = $folder . $fileName;

if (!file_put_contents($filePath, $data)) {
    echo json_encode(['status' => 'error', 'message' => 'Failed to save file']);
    exit;
}

try {
    $db = getDbConnection();
    $insertedId = $db->insertOne('customer_captures', [
        'image_path' => 'img/captures/' . $fileName,
        'captured_at' => date('Y-m-d H:i:s')
    ]);
    echo json_encode(['status' => 'success', 'path' => 'img/captures/' . $fileName, 'id' => $insertedId]);
} catch (Throwable $e) {
    echo json_encode(['status' => 'error', 'message' => 'DB error: ' . $e->getMessage()]);
}

<?php
require_once '../config.php';
header('Content-Type: application/json');

$codes = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    if (isset($input['codes']) && is_array($input['codes'])) {
        $codes = $input['codes'];
    }
} else {
    $raw = $_GET['codes'] ?? '';
    if ($raw) {
        $codes = explode(',', $raw);
    }
}

$codes = array_values(array_unique(array_filter(array_map(function($c) {
    $c = strtoupper(trim((string)$c));
    return (strlen($c) === 3) ? $c : null;
}, $codes))));

if (empty($codes)) {
    echo json_encode(['error' => 'Codes required']);
    exit;
}

try {
    $pdo = getDbConnection();
    $placeholders = implode(',', array_fill(0, count($codes), '?'));
    $stmt = $pdo->prepare("SELECT iata_code, latitude_deg, longitude_deg FROM airports WHERE iata_code IN ($placeholders)");
    $stmt->execute($codes);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $result = [];
    foreach ($rows as $row) {
        $result[$row['iata_code']] = [
            'lat' => (float)$row['latitude_deg'],
            'lon' => (float)$row['longitude_deg']
        ];
    }
    echo json_encode($result);
} catch (Throwable $e) {
    echo json_encode(['error' => $e->getMessage()]);
}

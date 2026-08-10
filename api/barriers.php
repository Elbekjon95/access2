<?php
require_once __DIR__ . '/../config.php';
header('Content-Type: application/json');

$map_id = $_GET['map_id'] ?? 1;

try {
    $db = getDbConnection();
    $filter = [
        '$or' => [
            ['map_id' => (int)$map_id],
            ['map_id' => (string)$map_id]
        ]
    ];
    $barriers = $db->find('map_barriers', $filter);
    
    // barrier_data ni JSON-dan massivga aylantirish (agar string bo'lsa)
    foreach ($barriers as &$b) {
        if (isset($b['barrier_data']) && is_string($b['barrier_data'])) {
            $b['barrier_data'] = json_decode($b['barrier_data'], true);
        }
    }
    
    echo json_encode($barriers);
} catch (Throwable $e) {
    echo json_encode(['error' => $e->getMessage()]);
}

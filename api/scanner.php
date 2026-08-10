<?php
require_once __DIR__ . '/../config.php';
header('Content-Type: application/json');

try {
    $db = getDbConnection();
    $mapNodes = $db->find('map_points', [], [
        'projection' => ['name' => 1, 'type' => 1, 'pos_x' => 1, 'pos_y' => 1]
    ]);
    echo json_encode($mapNodes);
} catch (Throwable $e) {
    echo json_encode(['error' => $e->getMessage()]);
}

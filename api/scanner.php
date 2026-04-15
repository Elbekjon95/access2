<?php
require_once '../config.php';
header('Content-Type: application/json');

try {
    $pdo = getDbConnection();
    $stmt = $pdo->query("SELECT name, type, pos_x, pos_y FROM map_points");
    $mapNodes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($mapNodes);
} catch (PDOException $e) {
    echo json_encode(['error' => $e->getMessage()]);
}

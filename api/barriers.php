<?php
require_once '../config.php';
header('Content-Type: application/json');

try {
    $pdo = getDbConnection();
    
    // map_id: GET parametrdan yoki bazadagi birinchi xaritadan
    if (isset($_GET['map_id'])) {
        $map_id = (int)$_GET['map_id'];
    } else {
        // Admin paneldek bazadagi birinchi xaritani olish
        $mapStmt = $pdo->query("SELECT id FROM maps LIMIT 1");
        $mapRow = $mapStmt->fetch(PDO::FETCH_ASSOC);
        $map_id = $mapRow ? (int)$mapRow['id'] : 1;
    }
    
    $stmt = $pdo->prepare("SELECT id, barrier_data FROM map_barriers WHERE map_id = ?");
    $stmt->execute([$map_id]);
    $barriers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // barrier_data ni JSON-dan massivga aylantirish
    foreach ($barriers as &$b) {
        $b['barrier_data'] = json_decode($b['barrier_data'], true);
    }
    
    echo json_encode($barriers);
} catch (PDOException $e) {
    echo json_encode(['error' => $e->getMessage()]);
}


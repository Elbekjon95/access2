<?php
require_once '../config.php';
session_start();

header('Content-Type: application/json');


try {
    $pdo = getDbConnection();
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['map_image'])) {
        if (!isset($_SESSION['admin_id'])) {
            http_response_code(401);
            echo json_encode(['error' => 'Unauthorized']);
            exit;
        }
        $file = $_FILES['map_image'];
        
        if ($file['error'] !== UPLOAD_ERR_OK) {
            echo json_encode(['error' => 'Upload xatosi']);
            exit;
        }
        
        $allowed = ['image/jpeg', 'image/jpg', 'image/png'];
        if (!in_array($file['type'], $allowed)) {
            echo json_encode(['error' => 'Faqat JPG/PNG ruxsat']);
            exit;
        }
        
        $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
        $newName = 'airport_map.' . $ext;
        $targetPath = '../img/' . $newName;
        
        if (move_uploaded_file($file['tmp_name'], $targetPath)) {
            $pdo->exec("CREATE TABLE IF NOT EXISTS maps (id INT PRIMARY KEY AUTO_INCREMENT, image_path VARCHAR(255), floor_name VARCHAR(100) DEFAULT 'default', updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP)");
            $pdo->exec("DELETE FROM maps");
            $stmt = $pdo->prepare("INSERT INTO maps (image_path, floor_name) VALUES (?, ?)");
            $stmt->execute(['img/' . $newName, 'default']);
            
            echo json_encode(['success' => true, 'path' => 'img/' . $newName]);
        } else {
            echo json_encode(['error' => 'Faylni saqlashda xato']);
        }
        exit;
    }
    
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $pdo->exec("CREATE TABLE IF NOT EXISTS maps (id INT PRIMARY KEY AUTO_INCREMENT, image_path VARCHAR(255), floor_name VARCHAR(100) DEFAULT 'default', updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP)");
        $stmt = $pdo->query("SELECT image_path FROM maps LIMIT 1");
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        echo json_encode(['path' => $row ? $row['image_path'] : 'img/airport_map.jpg']);
        exit;
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}

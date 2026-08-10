<?php
require_once __DIR__ . '/../config.php';
session_start();

header('Content-Type: application/json');


try {
    $db = getDbConnection();
    
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
            $db->deleteMany('maps', []);
            $db->insertOne('maps', [
                'map_id' => 1,
                'image_path' => 'img/' . $newName,
                'floor_name' => 'default',
                'updated_at' => date('Y-m-d H:i:s')
            ]);
            
            echo json_encode(['success' => true, 'path' => 'img/' . $newName]);
        } else {
            echo json_encode(['error' => 'Faylni saqlashda xato']);
        }
        exit;
    }
    
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $row = $db->findOne('maps', []);
        $path = $row ? $row['image_path'] : 'img/airport_map_opt.webp';
        if (!file_exists(__DIR__ . '/../' . $path)) {
            if (file_exists(__DIR__ . '/../img/airport_map_opt.webp')) {
                $path = 'img/airport_map_opt.webp';
            } elseif (file_exists(__DIR__ . '/../img/airport_map_opt.jpg')) {
                $path = 'img/airport_map_opt.jpg';
            } else {
                $path = 'img/airport_map.jpg';
            }
        }
        echo json_encode(['path' => $path]);
        exit;
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}

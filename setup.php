<?php
if (php_sapi_name() !== 'cli') {
    die("Xatolik: Ushbu fayl faqat terminal (CLI) orqali yozib ishga tushirilishi mumkin!");
}
require_once 'config.php';

echo "Database initialization started...\n";

try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";charset=utf8mb4", DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "Creating database if not exists: " . DB_NAME . "\n";
    $pdo->exec("CREATE DATABASE IF NOT EXISTS " . DB_NAME . " CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo->exec("USE " . DB_NAME);

    echo "Creating 'users' table...\n";
    $pdo->exec("CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(50) NOT NULL UNIQUE,
        password VARCHAR(255) NOT NULL,
        full_name VARCHAR(100) NOT NULL,
        role ENUM('admin', 'user') DEFAULT 'user',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB");

    echo "Creating 'customer_captures' table...\n";
    $pdo->exec("CREATE TABLE IF NOT EXISTS customer_captures (
        id INT AUTO_INCREMENT PRIMARY KEY,
        image_path VARCHAR(255) NOT NULL,
        captured_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB");

    echo "Creating 'chats' table...\n";
    $pdo->exec("CREATE TABLE IF NOT EXISTS chats (
        id INT AUTO_INCREMENT PRIMARY KEY,
        capture_id INT NULL,
        user_message TEXT NOT NULL,
        ai_response TEXT NOT NULL,
        language VARCHAR(10) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (capture_id) REFERENCES customer_captures(id) ON DELETE SET NULL
    ) ENGINE=InnoDB");

    echo "Creating 'maps' table...\n";
    $pdo->exec("CREATE TABLE IF NOT EXISTS maps (
        id INT AUTO_INCREMENT PRIMARY KEY,
        floor_name VARCHAR(50) NOT NULL,
        image_path VARCHAR(255) NOT NULL,
        width INT DEFAULT 0,
        height INT DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB");

    echo "Creating 'map_points' table...\n";
    $pdo->exec("CREATE TABLE IF NOT EXISTS map_points (
        id INT AUTO_INCREMENT PRIMARY KEY,
        map_id INT NOT NULL,
        name VARCHAR(100) NOT NULL,
        type ENUM('gate', 'fids', 'toilet', 'reception', 'door', 'entrance', 'exit', 'mosque', 'cip', 'vip_lounge', 'counter', 'other', 'kiosk_start') DEFAULT 'other',
        pos_x FLOAT NOT NULL,
        pos_y FLOAT NOT NULL,
        FOREIGN KEY (map_id) REFERENCES maps(id) ON DELETE CASCADE
    ) ENGINE=InnoDB");

    echo "Creating 'map_barriers' table...\n";
    $pdo->exec("CREATE TABLE IF NOT EXISTS map_barriers (
        id INT AUTO_INCREMENT PRIMARY KEY,
        map_id INT NOT NULL,
        barrier_data LONGTEXT NOT NULL,
        FOREIGN KEY (map_id) REFERENCES maps(id) ON DELETE CASCADE
    ) ENGINE=InnoDB");

    echo "Creating 'complaints' table...\n";
    $pdo->exec("CREATE TABLE IF NOT EXISTS complaints (
        id INT AUTO_INCREMENT PRIMARY KEY,
        full_name VARCHAR(100) NULL,
        contact VARCHAR(100) NULL,
        message TEXT NOT NULL,
        transcript TEXT NULL,
        audio_path VARCHAR(255) NULL,
        status ENUM('new', 'seen', 'resolved') DEFAULT 'new',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB");

    echo "Creating 'airports' table...\n";
    $pdo->exec("CREATE TABLE IF NOT EXISTS airports (
        id INT AUTO_INCREMENT PRIMARY KEY,
        iata_code CHAR(3) NOT NULL UNIQUE,
        name VARCHAR(200) NOT NULL,
        city VARCHAR(200) NULL,
        country CHAR(2) NULL,
        latitude_deg DOUBLE NOT NULL,
        longitude_deg DOUBLE NOT NULL,
        type VARCHAR(50) NULL,
        scheduled_service TINYINT(1) DEFAULT 0,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB");

    echo "Initial setup completed successfully!\n";
} catch (PDOException $e) {
    die("Setup failed: " . $e->getMessage());
}

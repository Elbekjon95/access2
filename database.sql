-- ACCSESS Project Database Schema (v2 - Production)
-- Foydalanish: mysql -u root -p acsess4 < database.sql

CREATE DATABASE IF NOT EXISTS acsess4 CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE acsess4;

-- Foydalanuvchilar (Admin va Kiosk foydalanuvchilari)
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    role ENUM('admin', 'user') DEFAULT 'user',
    face_encoding TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Xaritalar
CREATE TABLE IF NOT EXISTS maps (
    id INT AUTO_INCREMENT PRIMARY KEY,
    floor_name VARCHAR(50) NOT NULL,
    image_path VARCHAR(255) NOT NULL,
    width INT DEFAULT 0,
    height INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Xaritadagi navigatsiya nuqtalari
CREATE TABLE IF NOT EXISTS map_points (
    id INT AUTO_INCREMENT PRIMARY KEY,
    map_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    type ENUM('gate', 'fids', 'toilet', 'reception', 'other', 'kiosk_start') DEFAULT 'other',
    pos_x FLOAT NOT NULL,
    pos_y FLOAT NOT NULL,
    FOREIGN KEY (map_id) REFERENCES maps(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Xarita devorlari/to'siqlar (Navigatsiya uchun)
CREATE TABLE IF NOT EXISTS map_barriers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    map_id INT NOT NULL,
    barrier_data LONGTEXT NOT NULL,
    FOREIGN KEY (map_id) REFERENCES maps(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Kamera captures (Mijozlar rasmlari)
CREATE TABLE IF NOT EXISTS customer_captures (
    id INT AUTO_INCREMENT PRIMARY KEY,
    image_path VARCHAR(255) NOT NULL,
    captured_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Chat jurnali (Statistika uchun)
CREATE TABLE IF NOT EXISTS chats (
    id INT AUTO_INCREMENT PRIMARY KEY,
    capture_id INT NULL,
    user_message TEXT NOT NULL,
    ai_response TEXT NOT NULL,
    language VARCHAR(10) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (capture_id) REFERENCES customer_captures(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Shikoyatlar (Complaints) - transcript va audio_path ustunlari QOSHILDI
CREATE TABLE IF NOT EXISTS complaints (
    id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(100) NULL,
    contact VARCHAR(100) NULL,
    message TEXT NOT NULL,
    transcript TEXT NULL,
    audio_path VARCHAR(500) NULL,
    status ENUM('new', 'seen', 'resolved') DEFAULT 'new',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Boshlang'ich Admin foydalanuvchisi (parol: admin123)
-- MUHIM: Serverga o'rnatgandan keyin parolni ALBATTA o'zgartiring!
INSERT INTO users (username, password, full_name, role)
VALUES ('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Administrator', 'admin')
ON DUPLICATE KEY UPDATE id=id;

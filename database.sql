-- ========================================================
-- ACSESS Project Database Schema (MIGRATED TO MONGODB)
-- Diqqat: Ushbu loyiha ma'lumotlar bazasi MySQL dan MongoDB ga to'liq ko'chirildi.
-- MongoDB init skripti: database_mongo.js
-- PHP setup skripti: setup.php
-- MongoDB Database: acsess4
-- Kolleksiyalar:
--   - users (admin va foydalanuvchilar)
--   - maps (aeroport xaritalari)
--   - map_points (xarita nuqtalari: gate, toilet, fids, ...)
--   - map_barriers (devorlar va to'siqlar)
--   - customer_captures (mijozlar rasmlari)
--   - chats (AI va mijoz muloqotlari)
--   - complaints (shikoyatlar)
--   - airports (global aeroportlar ma'lumotlari)
-- ========================================================

-- Tarixiy MySQL sxemasi (Arxiv):
-- CREATE DATABASE IF NOT EXISTS acsess4 CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
-- USE acsess4;

-- Xaritalar
CREATE TABLE IF NOT EXISTS maps (
    id INT AUTO_INCREMENT PRIMARY KEY,
    floor_name VARCHAR(50) NOT NULL,
    image_path VARCHAR(255) NOT NULL,
    width INT DEFAULT 0,
    height INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Xaritadagi navigatsiya nuqtalari
CREATE TABLE IF NOT EXISTS map_points (
    id INT AUTO_INCREMENT PRIMARY KEY,
    map_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    type ENUM('gate', 'fids', 'toilet', 'reception', 'other', 'kiosk_start') DEFAULT 'other',
    pos_x FLOAT NOT NULL,
    pos_y FLOAT NOT NULL,
    FOREIGN KEY (map_id) REFERENCES maps(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Xarita devorlari/to'siqlar (Navigatsiya uchun)
CREATE TABLE IF NOT EXISTS map_barriers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    map_id INT NOT NULL,
    barrier_data LONGTEXT NOT NULL, -- JSON formatida to'siqlar koordinatalari
    FOREIGN KEY (map_id) REFERENCES maps(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Kamera captures (Mijozlar rasmlari)
CREATE TABLE IF NOT EXISTS customer_captures (
    id INT AUTO_INCREMENT PRIMARY KEY,
    image_path VARCHAR(255) NOT NULL,
    captured_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Chat jurnali (Statistika uchun)
CREATE TABLE IF NOT EXISTS chats (
    id INT AUTO_INCREMENT PRIMARY KEY,
    capture_id INT NULL,
    user_message TEXT NOT NULL,
    ai_response TEXT NOT NULL,
    language VARCHAR(10) NOT NULL, -- 'uz', 'ru', 'en' va h.k.
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (capture_id) REFERENCES customer_captures(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Shikoyatlar (Complaints)
CREATE TABLE IF NOT EXISTS complaints (
    id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(100) NULL,
    contact VARCHAR(100) NULL,
    message TEXT NOT NULL,
    status ENUM('new', 'seen', 'resolved') DEFAULT 'new',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Aeroportlar (Global IATA ma'lumotlari)
CREATE TABLE IF NOT EXISTS airports (
    iata_code CHAR(3) PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    city VARCHAR(255) NOT NULL,
    country CHAR(2) NOT NULL,
    latitude_deg DOUBLE NOT NULL,
    longitude_deg DOUBLE NOT NULL,
    type VARCHAR(50) NOT NULL,
    scheduled_service TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Ba'zi asosiy aeroportlarni qo'shib qo'yamiz
INSERT IGNORE INTO airports (iata_code, name, city, country, latitude_deg, longitude_deg, type, scheduled_service) VALUES 
('TAS', 'Tashkent International Airport', 'Tashkent', 'UZ', 41.2579, 69.2812, 'large_airport', 1),
('IST', 'Istanbul Airport', 'Istanbul', 'TR', 41.2753, 28.7519, 'large_airport', 1),
('DXB', 'Dubai International Airport', 'Dubai', 'AE', 25.2532, 55.3657, 'large_airport', 1),
('LHR', 'London Heathrow Airport', 'London', 'GB', 51.4700, -0.4543, 'large_airport', 1),
('PEK', 'Beijing Capital International Airport', 'Beijing', 'CN', 40.0799, 116.6031, 'large_airport', 1),
('DME', 'Domodedovo International Airport', 'Moscow', 'RU', 55.4088, 37.9063, 'large_airport', 1);

-- Boshlang'ich Admin foydalanuvchisi (parol: admin123)
INSERT INTO users (username, password, full_name, role) VALUES ('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Administrator', 'admin');

<?php
if (php_sapi_name() !== 'cli') {
    die("Xatolik: Ushbu fayl faqat terminal (CLI) orqali yozib ishga tushirilishi mumkin!\n");
}
require_once 'config.php';

echo "=== MongoDB Database Initialization Started ===\n";

try {
    $db = getDbConnection();
    echo "Connected to MongoDB database: " . DB_NAME . "\n";

    echo "1. Setting up indexes...\n";
    // Users unikal index
    $db->createIndex('users', ['username' => 1], ['unique' => true]);
    // Airports unikal index
    $db->createIndex('airports', ['iata_code' => 1], ['unique' => true]);
    // Map points index
    $db->createIndex('map_points', ['map_id' => 1, 'name' => 1, 'type' => 1]);
    // Map barriers index
    $db->createIndex('map_barriers', ['map_id' => 1]);
    // Chats index
    $db->createIndex('chats', ['created_at' => -1]);
    // Customer captures index
    $db->createIndex('customer_captures', ['captured_at' => -1]);
    // Complaints index
    $db->createIndex('complaints', ['created_at' => -1]);

    echo "2. Initializing default admin user if not exists...\n";
    $adminUser = $db->findOne('users', ['username' => 'admin']);
    if (!$adminUser) {
        $adminId = $db->insertOne('users', [
            'username' => 'admin',
            'password' => password_hash('admin123', PASSWORD_DEFAULT),
            'full_name' => 'Administrator',
            'role' => 'admin',
            'created_at' => date('Y-m-d H:i:s')
        ]);
        echo "Default admin user created: admin / admin123 (ID: {$adminId})\n";
    } else {
        echo "Admin user already exists.\n";
    }

    echo "3. Initializing default map if not exists...\n";
    $defaultMap = $db->findOne('maps', []);
    if (!$defaultMap) {
        $mapId = $db->insertOne('maps', [
            'map_id' => 1,
            'floor_name' => '1-qavat',
            'image_path' => 'img/airport_map.jpg',
            'width' => 1000,
            'height' => 800,
            'created_at' => date('Y-m-d H:i:s')
        ]);
        echo "Default map created (ID: {$mapId})\n";
    } else {
        echo "Default map already exists.\n";
    }

    echo "4. Initializing default airports if not exists...\n";
    $airportCount = $db->count('airports');
    if ($airportCount === 0) {
        $initialAirports = [
            ['iata_code' => 'TAS', 'name' => 'Tashkent International Airport', 'city' => 'Tashkent', 'country' => 'UZ', 'latitude_deg' => 41.2579, 'longitude_deg' => 69.2812, 'type' => 'large_airport', 'scheduled_service' => 1],
            ['iata_code' => 'IST', 'name' => 'Istanbul Airport', 'city' => 'Istanbul', 'country' => 'TR', 'latitude_deg' => 41.2753, 'longitude_deg' => 28.7519, 'type' => 'large_airport', 'scheduled_service' => 1],
            ['iata_code' => 'DXB', 'name' => 'Dubai International Airport', 'city' => 'Dubai', 'country' => 'AE', 'latitude_deg' => 25.2532, 'longitude_deg' => 55.3657, 'type' => 'large_airport', 'scheduled_service' => 1],
            ['iata_code' => 'LHR', 'name' => 'London Heathrow Airport', 'city' => 'London', 'country' => 'GB', 'latitude_deg' => 51.4700, 'longitude_deg' => -0.4543, 'type' => 'large_airport', 'scheduled_service' => 1],
            ['iata_code' => 'PEK', 'name' => 'Beijing Capital International Airport', 'city' => 'Beijing', 'country' => 'CN', 'latitude_deg' => 40.0799, 'longitude_deg' => 116.6031, 'type' => 'large_airport', 'scheduled_service' => 1],
            ['iata_code' => 'DME', 'name' => 'Domodedovo International Airport', 'city' => 'Moscow', 'country' => 'RU', 'latitude_deg' => 55.4088, 'longitude_deg' => 37.9063, 'type' => 'large_airport', 'scheduled_service' => 1],
        ];
        $insertedCount = $db->insertMany('airports', $initialAirports);
        echo "Inserted {$insertedCount} initial airports.\n";
    } else {
        echo "Airports already populated ({$airportCount} found).\n";
    }

    echo "\n=== MongoDB Setup completed successfully! ===\n";
} catch (Throwable $e) {
    die("Setup failed: " . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n");
}

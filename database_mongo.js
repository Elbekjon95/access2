// ===============================================
// ACSESS Project - MongoDB Database Initialization Script
// Ushbu skript MongoDB mongosh yoki MongoDB Compass orqali ishga tushiriladi.
// Ishga tushirish: mongosh < database_mongo.js
// ===============================================

// Ma'lumotlar bazasiga o'tish
db = db.getSiblingDB('acsess4');

// 1. Foydalanuvchilar (users)
db.createCollection('users');
db.users.createIndex({ "username": 1 }, { unique: true });

// 2. Xaritalar (maps)
db.createCollection('maps');

// 3. Xarita navigatsiya nuqtalari (map_points)
db.createCollection('map_points');
db.map_points.createIndex({ "map_id": 1, "name": 1, "type": 1 });

// 4. Xarita to'siqlari / devorlari (map_barriers)
db.createCollection('map_barriers');
db.map_barriers.createIndex({ "map_id": 1 });

// 5. Kamera captures / Mijozlar rasmlari (customer_captures)
db.createCollection('customer_captures');
db.customer_captures.createIndex({ "captured_at": -1 });

// 6. Chat muloqot jurnali (chats)
db.createCollection('chats');
db.chats.createIndex({ "created_at": -1 });
db.chats.createIndex({ "capture_id": 1 });

// 7. Shikoyatlar (complaints)
db.createCollection('complaints');
db.complaints.createIndex({ "created_at": -1 });

// 8. Aeroportlar (airports)
db.createCollection('airports');
db.airports.createIndex({ "iata_code": 1 }, { unique: true });


// ===============================================
// Boshlang'ich Ma'lumotlar (Seed Data)
// ===============================================

// Boshlang'ich admin (admin / admin123)
if (db.users.countDocuments({ username: 'admin' }) === 0) {
    db.users.insertOne({
        username: 'admin',
        password: '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', // admin123
        full_name: 'Administrator',
        role: 'admin',
        created_at: new Date().toISOString().slice(0, 19).replace('T', ' ')
    });
    print("Default admin foydalanuvchisi yaratildi.");
}

// Boshlang'ich xarita
if (db.maps.countDocuments() === 0) {
    db.maps.insertOne({
        map_id: 1,
        floor_name: '1-qavat',
        image_path: 'img/airport_map.jpg',
        width: 1000,
        height: 800,
        created_at: new Date().toISOString().slice(0, 19).replace('T', ' ')
    });
    print("Boshlang'ich xarita yaratildi.");
}

// Boshlang'ich aeroportlar
const initialAirports = [
    { iata_code: 'TAS', name: 'Tashkent International Airport', city: 'Tashkent', country: 'UZ', latitude_deg: 41.2579, longitude_deg: 69.2812, type: 'large_airport', scheduled_service: 1 },
    { iata_code: 'IST', name: 'Istanbul Airport', city: 'Istanbul', country: 'TR', latitude_deg: 41.2753, longitude_deg: 28.7519, type: 'large_airport', scheduled_service: 1 },
    { iata_code: 'DXB', name: 'Dubai International Airport', city: 'Dubai', country: 'AE', latitude_deg: 25.2532, longitude_deg: 55.3657, type: 'large_airport', scheduled_service: 1 },
    { iata_code: 'LHR', name: 'London Heathrow Airport', city: 'London', country: 'GB', latitude_deg: 51.4700, longitude_deg: -0.4543, type: 'large_airport', scheduled_service: 1 },
    { iata_code: 'PEK', name: 'Beijing Capital International Airport', city: 'Beijing', country: 'CN', latitude_deg: 40.0799, longitude_deg: 116.6031, type: 'large_airport', scheduled_service: 1 },
    { iata_code: 'DME', name: 'Domodedovo International Airport', city: 'Moscow', country: 'RU', latitude_deg: 55.4088, longitude_deg: 37.9063, type: 'large_airport', scheduled_service: 1 }
];

initialAirports.forEach(airport => {
    db.airports.updateOne(
        { iata_code: airport.iata_code },
        { $setOnInsert: Object.assign({}, airport, { created_at: new Date().toISOString().slice(0, 19).replace('T', ' ') }) },
        { upsert: true }
    );
});

print("MongoDB acsess4 bazasi muvaffaqiyatli initsializatsiya qilindi!");

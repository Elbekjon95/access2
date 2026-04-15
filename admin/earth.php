<?php
require_once '../config.php';
session_start();

if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="uz">
<head>
    <meta charset="UTF-8">
    <title>3D Yer - ACCSESS Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- Three.js Libraries -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/three.js/r128/three.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/three@0.128.0/examples/js/controls/OrbitControls.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/three@0.128.0/examples/js/loaders/GLTFLoader.js"></script>
    <style>
        :root { --sidebar-w: 250px; --accent: #00c6ff; --bg: #050a14; --panel: rgba(255,255,255,0.05); }
        body { font-family: 'Outfit', sans-serif; background: var(--bg); color: white; margin: 0; display: flex; }
        
        aside { width: var(--sidebar-w); height: 100vh; background: var(--panel); border-right: 1px solid rgba(255,255,255,0.1); padding: 2rem 1rem; position: fixed; }
        .logo { font-size: 1.5rem; font-weight: 700; color: var(--accent); margin-bottom: 3rem; text-align: center; }
        nav a { display: block; padding: 12px 20px; color: rgba(255,255,255,0.7); text-decoration: none; border-radius: 10px; margin-bottom: 10px; transition: 0.3s; }
        nav a:hover, nav a.active { background: var(--accent); color: white; }
        
        main { flex: 1; margin-left: var(--sidebar-w); padding: 3rem; }
        .earth-container { background: var(--panel); padding: 0; border-radius: 20px; border: 1px solid rgba(255,255,255,0.1); overflow: hidden; }
        #earth-canvas { width: 100%; height: 75vh; position: relative; }
        .earth-controls { display: flex; gap: 1rem; margin: 2rem 0; flex-wrap: wrap; }
        .btn { padding: 12px 20px; border-radius: 10px; cursor: pointer; border: none; font-size: 0.9rem; font-weight: 600; transition: 0.3s; font-family: 'Outfit', sans-serif; }
        .btn-primary { background: var(--accent); color: white; }
        .btn-primary:hover { opacity: 0.8; }
        .btn-danger { background: #ff5252; color: white; }
        .btn-danger:hover { opacity: 0.8; }
        .earth-info { padding: 1.5rem; background: rgba(0,0,0,0.3); border-radius: 10px; font-size: 0.9rem; color: rgba(255,255,255,0.7); margin-top: 2rem; }
        h1 { margin-bottom: 1rem; }
        .subtitle { color: rgba(255,255,255,0.6); font-size: 0.95rem; margin-bottom: 2rem; }
    </style>
</head>
<body>
    <aside>
        <div class="logo">ACCSESS ADMIN</div>
        <nav>
            <a href="index.php"><i class="fas fa-chart-line"></i> Dashboard</a>
            <a href="chats.php"><i class="fas fa-comments"></i> Chat Tarixi</a>
            <a href="complaints.php"><i class="fas fa-exclamation-circle"></i> Shikoyatlar</a>
            <a href="map.php"><i class="fas fa-map-marked-alt"></i> Xarita Sozlamalari</a>
            <a href="json_import.php"><i class="fas fa-file-import"></i> JSON Import</a>
            <a href="earth.php" class="active"><i class="fas fa-globe"></i> 3D Yer</a>
            <a href="users.php"><i class="fas fa-users"></i> Adminlar</a>
            <hr style="border: 0; border-top: 1px solid rgba(255,255,255,0.1); margin: 15px 0;">
            <a href="../index.php" style="color: var(--accent);"><i class="fas fa-external-link-alt"></i> Kioskga o'tish</a>
            <a href="logout.php" style="margin-top: 20px; color: #ff5252;"><i class="fas fa-sign-out-alt"></i> Chiqish</a>
        </nav>
    </aside>

    <main>
        <h1>🌍 3D Global Reys Vizualizatsiyasi</h1>
        <p class="subtitle">Dunyodagi barcha aeroportlar va ularning joylashuvi. Sichqoncha bilan aylantirib ko'ring!</p>
        
        <div class="earth-container">
            <div id="earth-canvas"></div>
        </div>
        
        <div class="earth-controls">
            <button class="btn btn-primary" onclick="testRoute('TAS', 'IST')">
                <i class="fas fa-plane"></i> Toshkent → Istanbul
            </button>
            <button class="btn btn-primary" onclick="testRoute('TAS', 'MOW')">
                <i class="fas fa-plane"></i> Toshkent → Moskva
            </button>
            <button class="btn btn-primary" onclick="testRoute('TAS', 'DXB')">
                <i class="fas fa-plane"></i> Toshkent → Dubai
            </button>
            <button class="btn btn-primary" onclick="testRoute('TAS', 'LHR')">
                <i class="fas fa-plane"></i> Toshkent → London
            </button>
            <button class="btn btn-primary" onclick="testRoute('TAS', 'PEK')">
                <i class="fas fa-plane"></i> Toshkent → Beijing
            </button>
            <button class="btn btn-danger" onclick="clearEarthRoute()">
                <i class="fas fa-eraser"></i> Tozalash
            </button>
        </div>
        
        <div class="earth-info">
            <strong><i class="fas fa-info-circle"></i> Qanday foydalanish:</strong><br>
            • Sichqoncha bilan Yerni aylantirib ko'ring (drag to rotate)<br>
            • Scroll qilish orqali zoom in/out qiling<br>
            • Yuqoridagi tugmalar orqali namuna yo'nalishlarni ko'ring<br>
            • <span style="color: #00ff00;">Yashil nuqta</span> - Toshkent (origin) | <span style="color: #ff0000;">Qizil nuqta</span> - Manzil (destination)<br>
            • <span style="color: #00ffff;">Ko'k chiziq</span> - Reys yo'nalishi (flight path)
        </div>
    </main>

    <!-- TopoJSON for country borders -->
    <script src="https://cdn.jsdelivr.net/npm/topojson-client@3"></script>
    <script>window.API_BASE = "../";</script>
    <script src="../earth.js?v=20260209z"></script>
    <script>
        // Initialize 3D Earth on page load
        window.addEventListener('load', () => {
            setTimeout(() => {
                initEarth('earth-canvas');
            }, 300);
        });
        
        // Test route functions
        function testRoute(origin, dest) {
            clearRoute();
            setTimeout(() => {
                showFlightRoute(origin, dest);
            }, 100);
        }
        
        function clearEarthRoute() {
            clearRoute();
        }
    </script>
</body>
</html>

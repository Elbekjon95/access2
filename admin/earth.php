<?php
require_once 'classes/AdminPage.php';
$page = new AdminPage("3D Yer", "earth");

$page->renderHeader('
    <script src="https://cdnjs.cloudflare.com/ajax/libs/three.js/r128/three.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/three@0.128.0/examples/js/controls/OrbitControls.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/three@0.128.0/examples/js/loaders/GLTFLoader.js"></script>
    <style>
        .earth-container { background: var(--panel); padding: 0; border-radius: 20px; border: 1px solid rgba(255,255,255,0.1); overflow: hidden; }
        #earth-canvas { width: 100%; height: 75vh; position: relative; }
        .earth-controls { display: flex; gap: 1rem; margin: 2rem 0; flex-wrap: wrap; }
        .btn-primary { background: var(--accent); color: white; }
        .btn-danger { background: #ff5252; color: white; }
        .earth-info { padding: 1.5rem; background: rgba(0,0,0,0.3); border-radius: 10px; font-size: 0.9rem; color: rgba(255,255,255,0.7); margin-top: 2rem; }
        .subtitle { color: rgba(255,255,255,0.6); font-size: 0.95rem; margin-bottom: 2rem; }
    </style>
');
$page->renderSidebar();
?>

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

<script src="https://cdn.jsdelivr.net/npm/topojson-client@3"></script>
<script>window.API_BASE = "../";</script>
<script src="../earth.js?v=1.3.4"></script>
<script>
    window.addEventListener('load', () => {
        setTimeout(() => {
            initEarth('earth-canvas');
        }, 300);
    });
    
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

<?php $page->renderFooter(); ?>

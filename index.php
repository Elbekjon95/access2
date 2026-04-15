<?php
require_once 'config.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: admin/login.php");
    exit;
}

// Xarita ma'lumotlarini olish
$stmtMap = $pdo->query("SELECT * FROM maps LIMIT 1");
$mapInfo = $stmtMap->fetch(PDO::FETCH_ASSOC);

// Real rasm o'lchamini va yo'lini aniqlash (DB-dagi noto'g'ri ma'lumotlarni tuzatish uchun)
$dbImagePath = $mapInfo ? $mapInfo['image_path'] : 'img/airport_map.jpg';
$cleanImagePath = str_replace('../', '', $dbImagePath);
$localImagePath = __DIR__ . '/' . $cleanImagePath;

// Agar fayl mavjud bo'lmasa, default rasm ishlatamiz
if (!file_exists($localImagePath)) {
    $cleanImagePath = 'img/airport_map.jpg';
    $localImagePath = __DIR__ . '/' . $cleanImagePath;
}

$imgSize = @getimagesize($localImagePath);
$mapWidth = $imgSize ? (int)$imgSize[0] : ($mapInfo ? (int)$mapInfo['width'] : 16700);
$mapHeight = $imgSize ? (int)$imgSize[1] : ($mapInfo ? (int)$mapInfo['height'] : 11813);
$mapImagePath = $cleanImagePath;

// Agar DB-da o'lcham yoki yo'l boshqacha bo'lsa, yangilab qo'yamiz (Self-healing)
if ($mapInfo && ($mapInfo['width'] != $mapWidth || $mapInfo['height'] != $mapHeight || $mapInfo['image_path'] != $mapImagePath)) {
    // MUHIM: Agar o'lcham 1000 dan katta o'lchamga o'tyotgan bo'lsa, mavjud nuqtalarni ham masshtablaymiz
    if ((int)$mapInfo['width'] <= 1001 && $mapWidth > 2000) {
        $ratioX = $mapWidth / (int)$mapInfo['width'];
        $ratioY = $mapHeight / (int)$mapInfo['height'];
        $pdo->exec("UPDATE map_points SET pos_x = pos_x * $ratioX, pos_y = pos_y * $ratioY");
        $pdo->exec("UPDATE map_barriers SET x = x * $ratioX, y = y * $ratioY, width = width * $ratioX, height = height * $ratioY");
    }

    $stmtUpd = $pdo->prepare("UPDATE maps SET width = ?, height = ?, image_path = ? WHERE id = ?");
    $stmtUpd->execute([$mapWidth, $mapHeight, $mapImagePath, $mapInfo['id']]);
}

// Nuqtalarni olish
$stmtPoints = $pdo->query("SELECT * FROM map_points ORDER BY id DESC");
$all_points = $stmtPoints->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="uz">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ACCSESS - Aerovokzal Ma'lumot Kioski</title>
    <link rel="stylesheet" href="style.css?v=1.2.4">
    <link rel="preload" href="img/airport_map.jpg" as="image">
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700&family=Outfit:wght@300;400;600&display=swap" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/three.js/r128/three.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>

<body>
    <div id="app-container">
        <div id="hologram-container"></div>

        <main id="main-ui">
            <header>
                <div class="logo-text">ACCESS</div>
                <div id="status-bar">
                    <button id="weather-temp-btn" class="weather-temp-btn">
                        <i class="fas fa-cloud-sun"></i>
                        <span id="toshkent-temp">--°C</span>
                    </button>
                    <span id="ai-status">Tizim tayyor</span>
                    <div id="time-display"></div>
                    <div id="lang-dropdown" class="lang-dropdown" data-value="auto">
                        <button type="button" class="lang-toggle" aria-haspopup="listbox" aria-expanded="false">
                            <span class="lang-flag" aria-hidden="true"></span>
                            <span class="lang-label">Auto</span>
                            <span class="lang-caret">▾</span>
                        </button>
                        <div class="lang-menu" role="listbox" aria-label="Tilni tanlash">
                            <button type="button" class="lang-option" role="option" data-value="auto" data-label="Auto">
                                <span class="flag-icon">
                                    <svg width="24" height="16" viewBox="0 0 30 20" xmlns="http://www.w3.org/2000/svg">
                                        <circle cx="15" cy="10" r="9" fill="#0aa0ff" />
                                        <path d="M6 10h18M15 1v18M9 4c2 2 2 10 0 12M21 4c-2 2-2 10 0 12" stroke="#ffffff" stroke-width="1" />
                                    </svg>
                                </span>
                                <span>Auto</span>
                            </button>
                            <button type="button" class="lang-option" role="option" data-value="uz" data-label="O'zbek">
                                <span class="flag-icon">
                                    <svg width="24" height="16" viewBox="0 0 30 20" xmlns="http://www.w3.org/2000/svg">
                                        <rect width="30" height="20" fill="#1eb6ff" />
                                        <rect y="7" width="30" height="6" fill="#ffffff" />
                                        <rect y="13" width="30" height="7" fill="#1eb53a" />
                                        <rect y="6.5" width="30" height="1" fill="#ce1126" />
                                        <rect y="12.5" width="30" height="1" fill="#ce1126" />
                                    </svg>
                                </span>
                                <span>O'zbek</span>
                            </button>
                            <button type="button" class="lang-option" role="option" data-value="ru" data-label="Русский">
                                <span class="flag-icon">
                                    <svg width="24" height="16" viewBox="0 0 30 20" xmlns="http://www.w3.org/2000/svg">
                                        <rect width="30" height="20" fill="#ffffff" />
                                        <rect y="7" width="30" height="6" fill="#0039a6" />
                                        <rect y="13" width="30" height="7" fill="#d52b1e" />
                                    </svg>
                                </span>
                                <span>Русский</span>
                            </button>
                            <button type="button" class="lang-option" role="option" data-value="en" data-label="English">
                                <span class="flag-icon">
                                    <svg width="24" height="16" viewBox="0 0 30 20" xmlns="http://www.w3.org/2000/svg">
                                        <rect width="30" height="20" fill="#012169" />
                                        <rect y="8" width="30" height="4" fill="#ffffff" />
                                        <rect x="13" width="4" height="20" fill="#ffffff" />
                                        <rect y="9" width="30" height="2" fill="#c8102e" />
                                        <rect x="14" width="2" height="20" fill="#c8102e" />
                                    </svg>
                                </span>
                                <span>English</span>
                            </button>
                            <button type="button" class="lang-option" role="option" data-value="es" data-label="Español">
                                <span class="flag-icon">
                                    <svg width="24" height="16" viewBox="0 0 30 20" xmlns="http://www.w3.org/2000/svg">
                                        <rect width="30" height="20" fill="#c60b1e" />
                                        <rect y="5" width="30" height="10" fill="#ffc400" />
                                    </svg>
                                </span>
                                <span>Español</span>
                            </button>
                            <button type="button" class="lang-option" role="option" data-value="zh" data-label="中文">
                                <span class="flag-icon">
                                    <svg width="24" height="16" viewBox="0 0 30 20" xmlns="http://www.w3.org/2000/svg">
                                        <rect width="30" height="20" fill="#de2910" />
                                        <circle cx="6" cy="6" r="3" fill="#ffde00" />
                                    </svg>
                                </span>
                                <span>中文</span>
                            </button>
                            <button type="button" class="lang-option" role="option" data-value="hi" data-label="हिन्दी">
                                <span class="flag-icon">
                                    <svg width="24" height="16" viewBox="0 0 30 20" xmlns="http://www.w3.org/2000/svg">
                                        <rect width="30" height="20" fill="#ff9933" />
                                        <rect y="6.7" width="30" height="6.6" fill="#ffffff" />
                                        <rect y="13.3" width="30" height="6.7" fill="#138808" />
                                        <circle cx="15" cy="10" r="2.2" fill="#000088" />
                                    </svg>
                                </span>
                                <span>हिन्दी</span>
                            </button>
                            <button type="button" class="lang-option" role="option" data-value="ar" data-label="العربية">
                                <span class="flag-icon">
                                    <svg width="24" height="16" viewBox="0 0 30 20" xmlns="http://www.w3.org/2000/svg">
                                        <rect width="30" height="20" fill="#006c35" />
                                        <rect x="6" y="9" width="18" height="2" fill="#ffffff" />
                                    </svg>
                                </span>
                                <span>العربية</span>
                            </button>
                            <button type="button" class="lang-option" role="option" data-value="bn" data-label="বাংলা">
                                <span class="flag-icon">
                                    <svg width="24" height="16" viewBox="0 0 30 20" xmlns="http://www.w3.org/2000/svg">
                                        <rect width="30" height="20" fill="#006a4e" />
                                        <circle cx="13" cy="10" r="5" fill="#f42a41" />
                                    </svg>
                                </span>
                                <span>বাংলা</span>
                            </button>
                            <button type="button" class="lang-option" role="option" data-value="pt" data-label="Português">
                                <span class="flag-icon">
                                    <svg width="24" height="16" viewBox="0 0 30 20" xmlns="http://www.w3.org/2000/svg">
                                        <rect width="12" height="20" fill="#006600" />
                                        <rect x="12" width="18" height="20" fill="#ff0000" />
                                    </svg>
                                </span>
                                <span>Português</span>
                            </button>
                            <button type="button" class="lang-option" role="option" data-value="ja" data-label="日本語">
                                <span class="flag-icon">
                                    <svg width="24" height="16" viewBox="0 0 30 20" xmlns="http://www.w3.org/2000/svg">
                                        <rect width="30" height="20" fill="#ffffff" />
                                        <circle cx="15" cy="10" r="5" fill="#bc002d" />
                                    </svg>
                                </span>
                                <span>日本語</span>
                            </button>
                            <button type="button" class="lang-option" role="option" data-value="de" data-label="Deutsch">
                                <span class="flag-icon">
                                    <svg width="24" height="16" viewBox="0 0 30 20" xmlns="http://www.w3.org/2000/svg">
                                        <rect width="30" height="20" fill="#000000" />
                                        <rect y="6.7" width="30" height="6.6" fill="#dd0000" />
                                        <rect y="13.3" width="30" height="6.7" fill="#ffce00" />
                                    </svg>
                                </span>
                                <span>Deutsch</span>
                            </button>
                            <button type="button" class="lang-option" role="option" data-value="it" data-label="Italiano">
                                <span class="flag-icon">
                                    <svg width="24" height="16" viewBox="0 0 30 20" xmlns="http://www.w3.org/2000/svg">
                                        <rect width="10" height="20" fill="#009246" />
                                        <rect x="10" width="10" height="20" fill="#ffffff" />
                                        <rect x="20" width="10" height="20" fill="#ce2b37" />
                                    </svg>
                                </span>
                                <span>Italiano</span>
                            </button>
                            <button type="button" class="lang-option" role="option" data-value="ko" data-label="한국어">
                                <span class="flag-icon">
                                    <svg width="24" height="16" viewBox="0 0 30 20" xmlns="http://www.w3.org/2000/svg">
                                        <rect width="30" height="20" fill="#ffffff" />
                                        <defs>
                                            <linearGradient id="kr" x1="0" y1="0" x2="0" y2="1">
                                                <stop offset="50%" stop-color="#c60c30" />
                                                <stop offset="50%" stop-color="#003478" />
                                            </linearGradient>
                                        </defs>
                                        <circle cx="15" cy="10" r="5" fill="url(#kr)" />
                                    </svg>
                                </span>
                                <span>한국어</span>
                            </button>
                            <button type="button" class="lang-option" role="option" data-value="tr" data-label="Türkçe">
                                <span class="flag-icon">
                                    <svg width="24" height="16" viewBox="0 0 30 20" xmlns="http://www.w3.org/2000/svg">
                                        <rect width="30" height="20" fill="#e30a17" />
                                        <circle cx="12" cy="10" r="5" fill="#ffffff" />
                                        <circle cx="13.5" cy="10" r="4" fill="#e30a17" />
                                    </svg>
                                </span>
                                <span>Türkçe</span>
                            </button>
                            <button type="button" class="lang-option" role="option" data-value="ur" data-label="اردو">
                                <span class="flag-icon">
                                    <svg width="24" height="16" viewBox="0 0 30 20" xmlns="http://www.w3.org/2000/svg">
                                        <rect width="30" height="20" fill="#01411c" />
                                        <rect width="6" height="20" fill="#ffffff" />
                                        <circle cx="17" cy="10" r="4" fill="#ffffff" />
                                    </svg>
                                </span>
                                <span>اردو</span>
                            </button>
                            <button type="button" class="lang-option" role="option" data-value="tg" data-label="Тоҷикӣ">
                                <span class="flag-icon">
                                    <svg width="24" height="16" viewBox="0 0 30 20" xmlns="http://www.w3.org/2000/svg">
                                        <rect width="30" height="20" fill="#d81e05" />
                                        <rect y="6.7" width="30" height="6.6" fill="#ffffff" />
                                        <rect y="13.3" width="30" height="6.7" fill="#006600" />
                                    </svg>
                                </span>
                                <span>Тоҷикӣ</span>
                            </button>
                            <button type="button" class="lang-option" role="option" data-value="ky" data-label="Кыргызча">
                                <span class="flag-icon">
                                    <svg width="24" height="16" viewBox="0 0 30 20" xmlns="http://www.w3.org/2000/svg">
                                        <rect width="30" height="20" fill="#e8112d" />
                                        <circle cx="15" cy="10" r="4.5" fill="#ffcc00" />
                                    </svg>
                                </span>
                                <span>Кыргызча</span>
                            </button>
                            <button type="button" class="lang-option" role="option" data-value="kk" data-label="Қазақша">
                                <span class="flag-icon">
                                    <svg width="24" height="16" viewBox="0 0 30 20" xmlns="http://www.w3.org/2000/svg">
                                        <rect width="30" height="20" fill="#00a3dd" />
                                        <circle cx="15" cy="10" r="4.5" fill="#ffcc00" />
                                    </svg>
                                </span>
                                <span>Қазақша</span>
                            </button>
                            <button type="button" class="lang-option" role="option" data-value="tk" data-label="Türkmençe">
                                <span class="flag-icon">
                                    <svg width="24" height="16" viewBox="0 0 30 20" xmlns="http://www.w3.org/2000/svg">
                                        <rect width="30" height="20" fill="#007a3d" />
                                        <rect width="7" height="20" fill="#c8102e" />
                                    </svg>
                                </span>
                                <span>Türkmençe</span>
                            </button>
                        </div>
                    </div>
                    <?php if ($_SESSION['user_role'] == 'admin'): ?>
                        <a href="admin/index.php" style="color: var(--secondary-blue); margin-left: 15px; text-decoration: none; font-size: 0.8rem; border: 1px solid var(--secondary-blue); padding: 2px 8px; border-radius: 5px;">Admin Panel</a>
                    <?php endif; ?>
                    <a href="admin/logout.php" style="color: #ff5252; margin-left: 15px; text-decoration: none; font-size: 0.8rem;"><i class="fas fa-sign-out-alt"></i></a>
                </div>
            </header>

            <section id="interaction-area">
                <div id="voice-waves">
                    <div class="bar"></div>
                    <div class="bar"></div>
                    <div class="bar"></div>
                    <div class="bar"></div>
                    <div class="bar"></div>
                </div>
                <div id="chat-output" class="glass-panel">
                    <p id="assistant-text">Xush kelibsiz! Menga savol berishingiz mumkin.</p>
                </div>
            </section>

            <nav id="bottom-nav">
                <button id="btn-map" class="nav-btn">Harita</button>
                <button id="btn-call" class="nav-btn" title="Operatorga qo'ng'iroq"><i class="fas fa-phone-alt"></i></button>
                <div class="voice-controls">
                    <button id="btn-pause-voice" class="action-btn" title="Pause/Resume" aria-label="Pause or resume assistant voice"><i class="fas fa-pause"></i></button>
                    <button id="btn-voice" class="nav-btn mic-btn pulsing">
                        <i class="fas fa-microphone"></i>
                    </button>
                    <button id="btn-stop-voice" class="action-btn" title="Stop" aria-label="Stop assistant voice"><i class="fas fa-stop"></i></button>
                </div>
                <button id="btn-flights" class="nav-btn">Reyslar</button>
                <button id="btn-complaint" class="nav-btn" style="background: rgba(255,82,82,0.2); border-color: #ff5252;" title="E'tiroz va taklif qoldirish">E'tiroz va taklif qoldirish</button>
            </nav>
        </main>

        <div id="qr-container" class="hide">
            <div class="qr-content">
                <button id="close-qr">&times;</button>
                <img id="qr-image" src="" alt="QR Code">
                <div id="qr-label"></div>
            </div>
        </div>

        <video id="webcam" autoplay muted playsinline style="display:none;"></video>
        <canvas id="recognition-overlay" style="display:none;"></canvas>
    </div>

    <!-- Modallar -->
    <div id="map-modal" class="modal hide">
        <div class="modal-content glass">
            <button class="close-modal" id="map-close-btn">&times;</button>
            <header>
                <h3>Aerovokzal Haritasi</h3>
            </header>
            <div id="map-canvas-container">
                <canvas id="map-canvas"></canvas>
                <div class="map-side-panel left" id="map-points-left">
                    <div class="panel-header">Xizmatlar</div>
                    <div class="panel-list" id="list-services"></div>
                </div>
                <div class="map-side-panel right" id="map-points-right">
                    <div class="panel-header">Darvozalar</div>
                    <div class="panel-list" id="list-gates"></div>
                </div>
            </div>
        </div>
    </div>

    <div id="flights-modal" class="modal hide">
        <div class="modal-content glass">
            <header>
                <h3>Joriy reyslar jadvali</h3>
                <button class="close-modal">&times;</button>
            </header>
            <div id="flights-table-container">
                <div class="flights-tabs">
                    <button class="flight-tab-btn active" data-type="departure">
                        <i class="fas fa-plane-departure"></i>
                        <span>Uchib ketish (Departures)</span>
                    </button>
                    <button class="flight-tab-btn" data-type="arrival">
                        <i class="fas fa-plane-arrival"></i>
                        <span>Uchib kelish (Arrivals)</span>
                    </button>
                </div>
                <table id="flights-table">
                    <thead>
                        <tr>
                            <th>Reys</th>
                            <th>Yo'nalish</th>
                            <th>Vaqt</th>
                            <th>Gate</th>
                            <th>Stoyka</th>
                            <th>Holat</th>
                        </tr>
                    </thead>
                    <tbody id="flights-body">
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div id="earth-modal" class="modal hide">
        <div class="modal-content glass" style="max-width: 95vw; max-height: 95vh; padding: 0; display: flex; flex-direction: column;">
            <header style="padding: 1rem; border-bottom: 1px solid var(--glass-border);">
                <h3 style="margin: 0;">🌍 Reys yo'nalishi</h3>
                <button class="close-modal">&times;</button>
            </header>
            <div style="display: flex; flex: 1; overflow: hidden;">
                <div id="earth-container" style="flex: 1; position: relative; min-width: 60%;"></div>
                <div class="earth-flight-info-panel">
                    <div class="terminal-header"><span>/// FLIGHT INFORMATION ///</span></div>
                    <div class="terminal-text" id="terminal-text">Reys ma'lumotlari yuklanmoqda...</div>
                    <div class="earth-route-actions" id="earth-route-actions"></div>
                </div>
            </div>
            <div style="padding: 0.8rem; text-align: center; color: rgba(255,255,255,0.5); border-top: 1px solid var(--glass-border); font-size: 0.85rem;">
                <small>🟢 Toshkent (TAS) &nbsp;|&nbsp; 🔴 Manzil</small>
            </div>
        </div>
    </div>

    <div id="complaint-modal" class="modal hide">
        <div class="modal-content glass" style="max-width: 500px; height: auto;">
            <header>
                <h3>Shikoyat yoki Taklif</h3>
                <button class="close-modal">&times;</button>
            </header>
            <div style="padding: 1rem;">
                <input type="text" id="comp-name" placeholder="Ismingiz" class="nav-btn" style="width: 100%; margin-bottom: 1rem; text-align: left;">
                <input type="text" id="comp-contact" placeholder="Telefon yoki Email" class="nav-btn" style="width: 100%; margin-bottom: 1rem; text-align: left;">
                <button id="btn-complaint-record" class="nav-btn" style="width: 100%; background: var(--secondary-blue); margin-bottom: 0.8rem;">OVOZLI SHIKOYATNI BOSHLASH</button>
                <div id="complaint-status" style="font-size: 0.9rem; opacity: 0.85; margin-bottom: 0.8rem;">Yozuv tugagach shikoyat avtomatik yuboriladi.</div>
                <audio id="complaint-audio-preview" controls style="width: 100%; display: none;"></audio>
            </div>
        </div>
    </div>

    <div id="weather-modal" class="modal hide">
        <div class="modal-content glass" style="max-width: 800px; height: 80vh;">
            <header>
                <h3>🌍 Manzil Shaharlar Ob-havosi</h3>
                <button class="close-modal">&times;</button>
            </header>
            <div id="weather-grid" class="weather-grid" style="flex: 1; overflow-y: auto; padding: 1.5rem; display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 1.5rem;">
                <div class="loader-container" style="grid-column: 1/-1; text-align: center; padding: 3rem;">
                    <i class="fas fa-circle-notch fa-spin fa-2x"></i>
                    <p style="margin-top: 1rem;">Yuklanmoqda...</p>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/three@0.128.0/examples/js/controls/OrbitControls.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/three@0.128.0/examples/js/loaders/GLTFLoader.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/topojson-client@3"></script>
    <script src="earth.js?v=1.2.4"></script>
    <script>
        window.NAV_LINE_WIDTH = 12;
        window.NAV_LINE_GLOW = 1;
        window.NAV_LINE_DASH = false;
        window.NAV_LINE_COLOR = "#ff3b30";
        window.NAV_CAMERA_FOLLOW = true;
        window.NAV_CAMERA_ZOOM = 1.7;
    </script>
    <script src="navigation.js?v=1.2.4"></script>
    <script type="module" src="./js/main.js?v=1.2.4"></script>
</body>

</html>
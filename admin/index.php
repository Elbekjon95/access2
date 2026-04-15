<?php
require_once '../config.php';
session_start();

if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit;
}

// Statistika ma'lumotlarini olish
$stmt = $pdo->query("SELECT language, COUNT(*) as count FROM chats GROUP BY language");
$langStats = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

$stmt = $pdo->query("SELECT DATE_FORMAT(created_at, '%Y-%m') as month, COUNT(*) as count FROM chats GROUP BY month ORDER BY month DESC LIMIT 6");
$monthlyStats = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

// So'nggi shikoyatlar
$stmt = $pdo->query("SELECT * FROM complaints ORDER BY created_at DESC LIMIT 5");
$complaints = $stmt->fetchAll();

// Xarita rasmi
$mapImagePath = '../img/airport_map.jpg';
$stmt = $pdo->query("SELECT image_path FROM maps LIMIT 1");
$mapRow = $stmt->fetch(PDO::FETCH_ASSOC);
if ($mapRow && !empty($mapRow['image_path'])) {
    $mapImagePath = '../' . ltrim($mapRow['image_path'], '/\\');
}
?>
<!DOCTYPE html>
<html lang="uz">
<head>
    <meta charset="UTF-8">
    <title>ACCSESS - Dashboard</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;600&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root { --sidebar-w: 250px; --accent: #00c6ff; --bg: #050a14; --panel: rgba(255,255,255,0.05); }
        body { font-family: 'Outfit', sans-serif; background: var(--bg); color: white; margin: 0; display: flex; }
        
        aside { width: var(--sidebar-w); height: 100vh; background: var(--panel); border-right: 1px solid rgba(255,255,255,0.1); padding: 2rem 1rem; position: fixed; }
        .logo { font-size: 1.5rem; font-weight: 700; color: var(--accent); margin-bottom: 3rem; text-align: center; }
        nav a { display: block; padding: 12px 20px; color: rgba(255,255,255,0.7); text-decoration: none; border-radius: 10px; margin-bottom: 10px; transition: 0.3s; }
        nav a:hover, nav a.active { background: var(--accent); color: white; }
        
        main { flex: 1; margin-left: var(--sidebar-w); padding: 3rem; }
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 2rem; margin-bottom: 3rem; }
        .chart-card { background: var(--panel); padding: 2rem; border-radius: 20px; border: 1px solid rgba(255,255,255,0.1); }
        
        table { width: 100%; border-collapse: collapse; margin-top: 1rem; }
        th, td { padding: 15px; text-align: left; border-bottom: 1px solid rgba(255,255,255,0.1); }
        th { color: var(--accent); font-size: 0.8rem; text-transform: uppercase; }
        .status-pill { padding: 4px 10px; border-radius: 5px; font-size: 0.75rem; background: rgba(0,198,255,0.2); }

        /* Modal for Camera Test */
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.8); z-index: 1000; justify-content: center; align-items: center; }
        .modal.active { display: flex; }
        .modal-content { background: #0a1220; padding: 2rem; border-radius: 20px; border: 1px solid var(--accent); width: 90%; max-width: 600px; position: relative; }
        .close-btn { position: absolute; top: 15px; right: 20px; font-size: 1.5rem; cursor: pointer; color: #ff5252; }
        #test-video { width: 100%; border-radius: 10px; background: #000; border: 1px solid rgba(255,255,255,0.1); }
        .cam-status { margin-top: 15px; padding: 10px; border-radius: 8px; font-size: 0.9rem; }
        .cam-status.ok { background: rgba(0, 255, 136, 0.1); color: #00ff88; border: 1px solid #00ff88; }
        .cam-status.err { background: rgba(255, 82, 82, 0.1); color: #ff5252; border: 1px solid #ff5252; }
        .earth-btn { background: var(--accent); color: black; font-weight: 600; border: none; padding: 10px 16px; border-radius: 8px; cursor: pointer; font-size: 0.85rem; }
        .earth-btn.ghost { background: rgba(255,255,255,0.1); color: white; border: 1px solid rgba(255,255,255,0.2); }
        .map-test-card { background: var(--panel); padding: 1.5rem; border-radius: 20px; border: 1px solid rgba(255,255,255,0.1); margin-bottom: 2rem; }
        #admin-map-wrapper { width: 100%; height: 600px; overflow: hidden; background: rgba(0,0,0,0.35); border: 1px solid rgba(255,255,255,0.1); border-radius: 14px; display: flex; justify-content: center; align-items: center; padding: 10px; }
        #admin-map-canvas { display: block; width: auto; height: auto; max-width: 100%; max-height: 100%; }
        .point-toolbar { display: flex; flex-wrap: wrap; gap: 10px; align-items: center; margin-top: 12px; }
        .point-toolbar input { background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.2); padding: 10px; border-radius: 8px; color: white; width: 220px; }
        .point-list { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 12px; max-height: 160px; overflow: auto; }
        .point-btn { background: rgba(255,255,255,0.08); border: 1px solid rgba(255,255,255,0.2); color: white; padding: 6px 10px; border-radius: 999px; cursor: pointer; font-size: 0.8rem; }
        .point-btn.active { background: var(--accent); color: black; font-weight: 600; }
    </style>
</head>
<body>
    <aside>
        <div class="logo">ACCSESS ADMIN</div>
        <nav>
            <a href="index.php" class="active"><i class="fas fa-chart-line"></i> Dashboard</a>
            <a href="chats.php"><i class="fas fa-comments"></i> Chat Tarixi</a>
            <a href="complaints.php"><i class="fas fa-exclamation-circle"></i> Shikoyatlar</a>
            <a href="map.php"><i class="fas fa-map-marked-alt"></i> Xarita Sozlamalari</a>
            <a href="json_import.php"><i class="fas fa-file-import"></i> JSON Import</a>
            <a href="earth.php"><i class="fas fa-globe"></i> 3D Yer</a>
            <a href="users.php"><i class="fas fa-users"></i> Adminlar</a>
            <hr style="border: 0; border-top: 1px solid rgba(255,255,255,0.1); margin: 15px 0;">
            <a href="../index.php" style="color: var(--accent);"><i class="fas fa-external-link-alt"></i> Kioskga o'tish</a>
            <a href="logout.php" style="margin-top: 20px; color: #ff5252;"><i class="fas fa-sign-out-alt"></i> Chiqish</a>
        </nav>
    </aside>

    <main>
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
            <h1>Xush kelibsiz, <?php echo $_SESSION['admin_name']; ?>!</h1>
            <div style="display: flex; gap: 10px;">
                <button onclick="testTTS()" class="status-pill" id="btn-test-tts" style="cursor: pointer; background: #1eb6ff; color: white; font-weight: 600; padding: 8px 15px; border: none; font-size: 0.85rem;">
                    <i class="fas fa-volume-up"></i> UZBEKVOICE
                </button>
                <button onclick="testSTT()" class="status-pill" id="btn-test-stt" style="cursor: pointer; background: #ff5252; color: white; font-weight: 600; padding: 8px 15px; border: none; font-size: 0.85rem;">
                    <i class="fas fa-microphone"></i> UZBEKOVOZI STT
                </button>
                <button onclick="openGeminiTts()" class="status-pill" id="btn-test-gemini" style="cursor: pointer; background: #6f42c1; color: white; font-weight: 600; padding: 8px 15px; border: none; font-size: 0.85rem;">
                    <i class="fas fa-magic"></i> GEMINI TEST
                </button>
                <button onclick="openCamTest()" class="status-pill" style="cursor: pointer; background: rgba(255,255,255,0.1); color: white; font-weight: 600; padding: 8px 15px; border: 1px solid rgba(255,255,255,0.2); font-size: 0.85rem;">
                    <i class="fas fa-camera"></i> KAMERA
                </button>
            </div>
        </div>
        
        <div class="stats-grid">
            <div class="chart-card">
                <h3>Savollar Statistikasi (Monthly)</h3>
                <canvas id="monthlyChart"></canvas>
            </div>
            <div class="chart-card">
                <h3>Tillar bo'yicha ulush</h3>
                <canvas id="langChart"></canvas>
            </div>
        </div>

        <?php if (false): ?>
        <div class="earth-card">
            <h3>3D Yer Test (Yo'nalishlar)</h3>
            <div id="earth-dashboard"></div>
            <div class="earth-tools">
                <input type="text" id="earthOrigin" value="TAS" maxlength="3" placeholder="Origin (IATA)">
                <input type="text" id="earthDest" value="IST" maxlength="3" placeholder="Destination (IATA)">
                <button class="earth-btn" onclick="dashboardRoute()">Route Test</button>
                <button class="earth-btn ghost" onclick="dashboardClear()">Tozalash</button>
                <button class="earth-btn" onclick="dashboardQuick('TAS','DXB')">TAS → DXB</button>
                <button class="earth-btn" onclick="dashboardQuick('TAS','LHR')">TAS → LHR</button>
            </div>
            <p style="font-size: 0.85rem; color: rgba(255,255,255,0.6); margin-top: 10px;">
                * IATA kodlarini 3 harf bilan kiriting. Nuqtalar va yo'nalishlar sinov uchun ko'rsatiladi.
            </p>
        </div>
        <?php endif; ?>



        <div class="chart-card">
            <h3>So'nggi Shikoyatlar</h3>
            <table>
                <thead>
                    <tr>
                        <th>Ism</th>
                        <th>Kontakt</th>
                        <th>Xabar</th>
                        <th>Vaqt</th>
                        <th>Holat</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($complaints as $c): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($c['full_name']); ?></td>
                        <td><?php echo htmlspecialchars($c['contact']); ?></td>
                        <td><?php echo htmlspecialchars($c['message']); ?></td>
                        <td><?php echo $c['created_at']; ?></td>
                        <td><span class="status-pill"><?php echo $c['status']; ?></span></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </main>

    <!-- Gemini TTS Modal -->
    <div id="geminiTtsModal" class="modal">
        <div class="modal-content">
            <span class="close-btn" onclick="closeGeminiTts()">&times;</span>
            <h2 style="color: var(--accent); margin-top: 0;"><i class="fas fa-magic"></i> Gemini TTS Test</h2>
            <div style="margin-bottom: 20px;">
                <label style="display: block; margin-bottom: 8px; color: rgba(255,255,255,0.8);">Matn kiriting:</label>
                <textarea id="gemini-tts-text" style="width: 100%; height: 120px; background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.2); border-radius: 10px; color: white; padding: 12px; font-family: inherit; resize: none;" placeholder="Ovozlantirish uchun matn yozing..."></textarea>
            </div>
            <div style="margin-bottom: 25px;">
                <label style="display: block; margin-bottom: 8px; color: rgba(255,255,255,0.8);">Suhandonni tanlang:</label>
                <select id="gemini-tts-voice" style="width: 100%; background: #0a1220; border: 1px solid rgba(255,255,255,0.2); border-radius: 8px; color: white; padding: 10px; cursor: pointer;">
                    <option value="">Yuklanmoqda...</option>
                </select>
            </div>
            <div style="display: flex; gap: 10px; justify-content: flex-end;">
                <button onclick="closeGeminiTts()" style="background: rgba(255,255,255,0.1); color: white; border: none; padding: 10px 20px; border-radius: 8px; cursor: pointer;">Yopish</button>
                <button id="btn-gemini-play" onclick="playGeminiTts()" style="background: var(--accent); color: black; border: none; padding: 10px 25px; border-radius: 8px; font-weight: 600; cursor: pointer;">
                    <i class="fas fa-play"></i> Ovozlantirish
                </button>
            </div>
            <div id="gemini-status" style="margin-top: 15px; font-size: 0.85rem; color: rgba(255,255,255,0.5);"></div>
        </div>
    </div>

    <!-- Camera Test Modal -->
    <div id="camTestModal" class="modal">
        <div class="modal-content">
            <span class="close-btn" onclick="closeCamTest()">&times;</span>
            <h2 style="color: var(--accent); margin-top: 0;"><i class="fas fa-camera"></i> Kamera Testi</h2>
            <video id="test-video" autoplay playsinline></video>
            <div id="camStatus" class="cam-status"></div>
        </div>
    </div>

    <script>
        window.API_BASE = "../";
        window.NAV_LINE_WIDTH = 12;
        window.NAV_LINE_GLOW = 1;
        window.NAV_LINE_DASH = false;
        window.NAV_LINE_COLOR = "#ff3b30";
        window.NAV_CAMERA_FOLLOW = true;
        window.NAV_CAMERA_ZOOM = 1.7;
    </script>
    <script src="../navigation.js?v=20260206v"></script>
    <script>
        // Monthly Stats Chart
        const monthlyCtx = document.getElementById('monthlyChart').getContext('2d');
        new Chart(monthlyCtx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode(array_keys($monthlyStats)); ?>,
                datasets: [{
                    label: 'Savollar soni',
                    data: <?php echo json_encode(array_values($monthlyStats)); ?>,
                    borderColor: '#00c6ff',
                    fill: false,
                    tension: 0.4
                }]
            },
            options: { plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, grid: { color: 'rgba(255,255,255,0.1)' } } } }
        });

        // Language Stats Chart
        const langCtx = document.getElementById('langChart').getContext('2d');
        new Chart(langCtx, {
            type: 'doughnut',
            data: {
                labels: <?php echo json_encode(array_keys($langStats)); ?>,
                datasets: [{
                    data: <?php echo json_encode(array_values($langStats)); ?>,
                    backgroundColor: ['#0072ff', '#00c6ff', '#d5a107', '#ff5252']
                }]
            }
        });

        // Camera Test Logic
        let camStream = null;
        function openCamTest() {
            document.getElementById('camTestModal').classList.add('active');
            const video = document.getElementById('test-video');
            const statusDiv = document.getElementById('camStatus');
            statusDiv.className = 'cam-status';
            statusDiv.innerText = 'Kamera ulanmoqda...';

            navigator.mediaDevices.getUserMedia({ 
                video: { facingMode: "user", width: 1280, height: 720 } 
            })
            .then(stream => {
                camStream = stream;
                video.srcObject = stream;
                statusDiv.className = 'cam-status ok';
                statusDiv.innerText = 'Kamera muvaffaqiyatli ishga tushdi (720p HD). Signal qabul qilinmoqda.';
            })
            .catch(err => {
                statusDiv.className = 'cam-status err';
                statusDiv.innerText = 'Xatolik: ' + err.message + '. Kamera topilmadi yoki ruxsat berilmagan.';
                console.error("Camera test error:", err);
            });
        }

        function closeCamTest() {
            document.getElementById('camTestModal').classList.remove('active');
            if (camStream) {
                camStream.getTracks().forEach(track => track.stop());
                camStream = null;
            }
            document.getElementById('test-video').srcObject = null;
        }

        async function testTTS() {
            const btn = document.getElementById('btn-test-tts');
            const originalText = btn.innerHTML;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> ...';
            btn.disabled = true;

            try {
                const response = await fetch('../api/tts.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ text: "Bu o'zbek voice tizimining sinov ovozi", language: "uz" })
                });

                if (response.ok) {
                    const blob = await response.blob();
                    const url = URL.createObjectURL(blob);
                    const audio = new Audio(url);
                    audio.play();
                    console.log("UzbekVoice TTS OK");
                } else {
                    alert("UzbekVoice xatosi: " + response.status);
                }
            } catch (err) {
                alert("Xato: " + err.message);
            } finally {
                btn.innerHTML = originalText;
                btn.disabled = false;
            }
        }

        async function testSTT() {
            const btn = document.getElementById('btn-test-stt');
            if (sttRecorder && sttRecorder.state === "recording") {
                sttRecorder.stop();
                btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Kutmoqda...';
                return;
            }
            
            try {
                const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
                sttRecorder = new MediaRecorder(stream);
                sttChunks = [];
                
                sttRecorder.ondataavailable = e => {
                    if (e.data.size > 0) sttChunks.push(e.data);
                };
                
                sttRecorder.onstop = async () => {
                    stream.getTracks().forEach(t => t.stop());
                    const audioBlob = new Blob(sttChunks, { type: 'audio/webm' });
                    
                    const fd = new FormData();
                    fd.append("audio", audioBlob, "test.webm");
                    fd.append("language", "uz");
                    
                    try {
                        const res = await fetch('../api/stt.php', { method: 'POST', body: fd });
                        const data = await res.json();
                        if (data.text) {
                            alert("UzbekVoice STT Natijasi:\n" + data.text);
                        } else {
                            alert("UzbekVoice STT xatosi:\n" + (data.error || "Matn aniqlanmadi (yoki bo'sh)"));
                        }
                    } catch(e) {
                        alert("So'rovda xatolik: " + e.message);
                    } finally {
                        btn.innerHTML = '<i class="fas fa-microphone"></i> UZBEKOVOZI STT';
                        btn.style.background = '#ff5252';
                    }
                };
                
                sttRecorder.start();
                btn.innerHTML = '<i class="fas fa-stop"></i> TO\'XTATISH...';
                btn.style.background = '#d93025';
                
            } catch(e) {
                alert("Mikrofonga ruxsat olinmadi: " + e.message);
            }
        }

        function playAudioChunks(chunks) {
            const totalLen = chunks.reduce((acc, c) => acc + c.length, 0);
            const merged = new Uint8Array(totalLen);
            let offset = 0;
            for (const chunk of chunks) {
                merged.set(chunk, offset);
                offset += chunk.length;
            }

            // Convert raw PCM to WAV (simplest way to play in browser)
            const wavHeader = createWavHeader(totalLen, 16000);
            const blob = new Blob([wavHeader, merged], { type: 'audio/wav' });
            const url = URL.createObjectURL(blob);
            const audio = new Audio(url);
            audio.play();
            console.log("Ovoz ijro etilmoqda...");
        }

        function createWavHeader(dataLen, sampleRate) {
            const header = new ArrayBuffer(44);
            const view = new DataView(header);
            const writeString = (o, s) => { for(let i=0; i<s.length; i++) view.setUint8(o+i, s.charCodeAt(i)); };
            
            writeString(0, 'RIFF');
            view.setUint32(4, 36 + dataLen, true);
            writeString(8, 'WAVE');
            writeString(12, 'fmt ');
            view.setUint32(16, 16, true);
            view.setUint16(20, 1, true);
            view.setUint16(22, 1, true);
            view.setUint32(24, sampleRate, true);
            view.setUint32(28, sampleRate * 2, true);
            view.setUint16(32, 2, true);
            view.setUint16(34, 16, true);
            writeString(36, 'data');
            view.setUint32(40, dataLen, true);
            return header;
        }

        let adminNav = null;
        let adminPoints = [];
        let currentMapPath = 'img/airport_map.jpg';

        async function uploadMap() {
            const input = document.getElementById('mapUpload');
            const status = document.getElementById('uploadStatus');
            const file = input.files[0];
            if (!file) return;

            status.textContent = 'Yuklanmoqda...';
            const formData = new FormData();
            formData.append('map_image', file);

            try {
                const res = await fetch('../api/map_settings.php', { method: 'POST', body: formData });
                const data = await res.json();
                if (data.success) {
                    status.textContent = 'Muvaffaqiyatli yuklandi!';
                    currentMapPath = '../' + data.path;
                    if (adminNav) {
                        adminNav.loadMap(currentMapPath);
                    }
                    setTimeout(() => status.textContent = '', 3000);
                } else {
                    status.textContent = 'Xato: ' + (data.error || 'Unknown');
                }
            } catch (err) {
                status.textContent = 'Xato: ' + err.message;
            }
        }

        function initAdminMapTest() {
            const canvas = document.getElementById('admin-map-canvas');
            if (!canvas || adminNav) return;

            adminNav = new AirportNavigation('admin-map-canvas');
            adminNav.loadMap('<?php echo $mapImagePath; ?>');

            fetch('../api/scanner.php')
                .then(res => res.json())
                .then(points => {
                    if (!Array.isArray(points)) return;
                    adminPoints = points;
                    adminNav.setNodes(points);
                    renderPointList();
                })
                .catch(err => console.error('Map points fetch error:', err));

            const search = document.getElementById('pointSearch');
            if (search) {
                search.addEventListener('input', () => renderPointList(search.value));
            }
        }

        function renderPointList(filterText = '') {
            const list = document.getElementById('admin-point-list');
            if (!list) return;
            list.innerHTML = '';
            const filter = String(filterText || '').trim().toLowerCase();
            const filtered = adminPoints.filter(p => {
                if (!filter) return true;
                const name = String(p.name || '').toLowerCase();
                const type = String(p.type || '').toLowerCase();
                return name.includes(filter) || type.includes(filter);
            });

            if (filtered.length === 0) {
                const empty = document.createElement('div');
                empty.textContent = 'No points found.';
                empty.style.opacity = '0.6';
                list.appendChild(empty);
                return;
            }

            filtered.slice(0, 120).forEach(p => {
                const btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'point-btn';
                btn.textContent = `${p.name} (${p.type})`;
                btn.addEventListener('click', () => {
                    list.querySelectorAll('.point-btn').forEach(b => b.classList.remove('active'));
                    btn.classList.add('active');
                    if (adminNav) adminNav.findPath(p.name);
                });
                list.appendChild(btn);
            });
        }

        function resetAdminPath() {
            if (!adminNav) return;
            adminNav.path = [];
            adminNav.pathRevealProgress = 0;
            adminNav.cameraProgress = 0;
            adminNav.isAnimatingPath = false;
            adminNav.render();
            const list = document.getElementById('admin-point-list');
            if (list) list.querySelectorAll('.point-btn').forEach(b => b.classList.remove('active'));
        }

        // Gemini TTS Logic
        let geminiVoices = [];
        async function openGeminiTts() {
            document.getElementById('geminiTtsModal').classList.add('active');
            const select = document.getElementById('gemini-tts-voice');
            const status = document.getElementById('gemini-status');
            
            if (geminiVoices.length === 0) {
                status.innerText = "Ovozlar ro'yxati yuklanmoqda...";
                // Gemini 2.0 Flash (Multimodal) - QAT'IY TALAB QILINGAN OVOZLAR
                geminiVoices = [
                    { name: 'Aoede', desc: '✨ Aoede (High Quality, Ayol) - DEFAULT', engine: 'gemini' },
                    { name: 'Puck', desc: '✨ Puck (High Quality, Ayol)', engine: 'gemini' },
                    { name: 'Charon', desc: '✨ Charon (High Quality, Erkak)', engine: 'gemini' },
                    { name: 'Fenrir', desc: '✨ Fenrir (High Quality, Erkak)', engine: 'gemini' },
                    { name: 'Kore', desc: '✨ Kore (Yumshoq, Ayol)', engine: 'gemini' },
                    { name: 'Oura', desc: '✨ Oura (Lively, Ayol)', engine: 'gemini' },
                    { name: 'Terra', desc: '✨ Terra (Calm, Ayol)', engine: 'gemini' },
                    { name: 'Zephyr', desc: '✨ Zephyr (Fast, Erkak)', engine: 'gemini' },
                    { name: 'Icarus', desc: '✨ Icarus (Sharp, Erkak)', engine: 'gemini' },
                    { name: 'lola', desc: '👤 Lola (UzbekVoice - Ayol)', engine: 'uzbekvoice' },
                    { name: 'dilshod', desc: '👤 Dilshod (UzbekVoice - Erkak)', engine: 'uzbekvoice' }
                ];

                geminiVoices.forEach(v => {
                    const option = document.createElement('option');
                    option.value = v.name;
                    option.dataset.engine = v.engine;
                    option.innerText = v.desc;
                    if (v.name === 'Aoede') option.selected = true; // Aoede ni default qilish
                    select.appendChild(option);
                });
                status.innerText = "Gemini 2.0 Flash suhandonlari tayyor.";
            }
        }

        function closeGeminiTts() {
            document.getElementById('geminiTtsModal').classList.remove('active');
        }

        async function playGeminiTts() {
            const btn = document.getElementById('btn-gemini-play');
            const text = document.getElementById('gemini-tts-text').value;
            const select = document.getElementById('gemini-tts-voice');
            const voiceName = select.value;
            const langCode = select.options[select.selectedIndex]?.dataset.lang || 'en-US';
            const status = document.getElementById('gemini-status');

            if (!text.trim()) {
                alert("Iltimos, matn kiriting.");
                return;
            }

            const originalHtml = btn.innerHTML;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Ovozlantirilmoqda...';
            btn.disabled = true;
            status.innerText = "So'rov yuborilmoqda...";

            try {
                const voiceOption = select.options[select.selectedIndex];
                const engine = voiceOption.dataset.engine;
                
                let res;
                if (engine === 'uzbekvoice') {
                    // UzbekVoice
                    res = await fetch('../api/tts.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ text: text, language: 'uz', voice: voiceName })
                    });
                } else {
                    // Gemini AI (multimodal audio)
                    res = await fetch('../api/gemini_voice.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ text: text, voice_name: voiceName })
                    });
                }

                if (res.headers.get('Content-Type')?.includes('audio/mpeg')) {
                    const blob = await res.blob();
                    const audio = new Audio(URL.createObjectURL(blob));
                    audio.play();
                    status.innerText = "Muvaffaqiyatli! Audio ijro etilmoqda.";
                } else {
                    const data = await res.json();
                    if (data.success && data.audioContent) {
                        const mime = data.mimeType || 'audio/mpeg';
                        const audio = new Audio("data:" + mime + ";base64," + data.audioContent);
                        audio.play();
                        status.innerText = "Muvaffaqiyatli! Gemini AI audio ijro etilmoqda.";
                    } else if (data.chunks && data.chunks.length > 0) {
                        const audio = new Audio("data:audio/mp3;base64," + data.chunks[0]);
                        audio.play();
                        status.innerText = "Muvaffaqiyatli! Audio (chunk) ijro etilmoqda.";
                    } else {
                        const detailStr = data.details ? JSON.stringify(data.details, null, 2) : "Noma'lum xato";
                        console.error("TTS Xatosi:\n" + (data.error || "") + "\nDetails:\n" + detailStr);
                        status.innerText = "Xatolik yuz berdi (Konsolga qarang).";
                    }
                }
            } catch (e) {
                console.error("Gemini TTS catch Xatosi:", e);
                status.innerText = "Xatolik yuz berdi.";
            } finally {
                btn.innerHTML = originalHtml;
                btn.disabled = false;
            }
        }
    </script>

    <!-- Camera Test Modal -->
    <div id="camTestModal" class="modal">
        <div class="modal-content">
            <span class="close-btn" onclick="closeCamTest()">&times;</span>
            <h2 style="margin-top: 0; color: var(--accent);">Kamera Diagnostikasi</h2>
            <video id="test-video" autoplay muted playsinline></video>
            <div id="camStatus" class="cam-status"></div>
            <p style="font-size: 0.8rem; color: rgba(255,255,255,0.5); margin-top: 15px;">
                * Bu yerda kiosk interfeysida ishlatiladigan kamera oqimi test qilinmoqda. Agar video ko'rinmasa, brauzer ruxsatlarini tekshiring.
            </p>
        </div>
    </div>
</body>
</html>

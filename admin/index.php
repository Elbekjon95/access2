<?php
require_once 'classes/AdminPage.php';
$page = new AdminPage("Dashboard", "dashboard");

// Statistika ma'lumotlarini olish
$db = $page->getDb();

$langAgg = $db->aggregate('chats', [
    ['$group' => ['_id' => '$language', 'count' => ['$sum' => 1]]]
]);
$langStats = [];
foreach ($langAgg as $item) {
    $lang = $item['_id'] ?: 'unknown';
    $langStats[$lang] = (int)$item['count'];
}

$chatsAll = $db->find('chats', [], ['projection' => ['created_at' => 1], 'sort' => ['created_at' => -1]]);
$monthlyStats = [];
foreach ($chatsAll as $ch) {
    $created = $ch['created_at'] ?? '';
    $month = substr((string)$created, 0, 7);
    if ($month && preg_match('/^\d{4}-\d{2}$/', $month)) {
        $monthlyStats[$month] = ($monthlyStats[$month] ?? 0) + 1;
    }
}
$monthlyStats = array_slice($monthlyStats, 0, 6, true);

// So'nggi shikoyatlar
$complaints = $db->find('complaints', [], [
    'sort' => ['created_at' => -1, '_id' => -1],
    'limit' => 5
]);

// Xarita rasmi
$mapImagePath = '../img/airport_map_opt.jpg';

$extra_head = '
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 2rem; margin-bottom: 3rem; }
        .chart-card { background: var(--panel); padding: 2rem; border-radius: 20px; border: 1px solid rgba(255,255,255,0.1); }
        .status-pill { padding: 4px 10px; border-radius: 5px; font-size: 0.75rem; background: rgba(0,198,255,0.2); }
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.8); z-index: 1000; justify-content: center; align-items: center; }
        .modal.active { display: flex; }
        .modal-content { background: #0a1220; padding: 2rem; border-radius: 20px; border: 1px solid var(--accent); width: 90%; max-width: 600px; position: relative; }
        .close-btn { position: absolute; top: 15px; right: 20px; font-size: 1.5rem; cursor: pointer; color: #ff5252; }
        #test-video { width: 100%; border-radius: 10px; background: #000; border: 1px solid rgba(255,255,255,0.1); }
        .cam-status { margin-top: 15px; padding: 10px; border-radius: 8px; font-size: 0.9rem; }
        .earth-btn { background: var(--accent); color: black; font-weight: 600; border: none; padding: 10px 16px; border-radius: 8px; cursor: pointer; font-size: 0.85rem; }
        #admin-map-wrapper { width: 100%; height: 600px; overflow: hidden; background: rgba(0,0,0,0.35); border: 1px solid rgba(255,255,255,0.1); border-radius: 14px; display: flex; justify-content: center; align-items: center; padding: 10px; }
        #admin-map-canvas { display: block; width: auto; height: auto; max-width: 100%; max-height: 100%; }
        .point-toolbar { display: flex; flex-wrap: wrap; gap: 10px; align-items: center; margin-top: 12px; }
        .point-toolbar input { background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.2); padding: 10px; border-radius: 8px; color: white; width: 220px; }
        .point-list { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 12px; max-height: 160px; overflow: auto; }
        .point-btn { background: rgba(255,255,255,0.08); border: 1px solid rgba(255,255,255,0.2); color: white; padding: 6px 10px; border-radius: 999px; cursor: pointer; font-size: 0.8rem; }
        .point-btn.active { background: var(--accent); color: black; font-weight: 600; }
    </style>
';

$page->renderHeader($extra_head);
$page->renderSidebar();
?>
<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
    <h1>Xush kelibsiz, <?php echo $_SESSION['admin_name']; ?>!</h1>
            <div style="display: flex; gap: 10px;">
                <button onclick="testTTS()" class="status-pill" id="btn-test-tts" style="cursor: pointer; background: #1eb6ff; color: white; font-weight: 600; padding: 8px 15px; border: none; font-size: 0.85rem;">
                    <i class="fas fa-volume-up"></i> UZBEKVOICE
                </button>
                <button onclick="testIFlytekTTS()" class="status-pill" id="btn-test-iflytek" style="cursor: pointer; background: #00c6ff; color: black; font-weight: 600; padding: 8px 15px; border: none; font-size: 0.85rem;">
                    <i class="fas fa-bolt"></i> iFLYTEK TEST
                </button>
                <button onclick="testSTT()" class="status-pill" id="btn-test-stt" style="cursor: pointer; background: #ff5252; color: white; font-weight: 600; padding: 8px 15px; border: none; font-size: 0.85rem;">
                    <i class="fas fa-microphone"></i> UZBEKOVOZI STT
                </button>
                <button onclick="testIFlytekSTT()" class="status-pill" id="btn-test-iflytek-stt" style="cursor: pointer; background: #00c6ff; color: black; font-weight: 600; padding: 8px 15px; border: none; font-size: 0.85rem;">
                    <i class="fas fa-headphones"></i> iFLYTEK STT
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

        <div class="map-test-card">
            <h3>Xarita Sozlamalari</h3>
            <div style="margin-bottom: 1rem; display: flex; gap: 10px; align-items: center;">
                <input type="file" id="mapUpload" accept="image/jpeg,image/jpg,image/png" style="display: none;" onchange="uploadMap()">
                <button class="earth-btn" onclick="document.getElementById('mapUpload').click()"><i class="fas fa-upload"></i> Harita Yuklash</button>
                <span id="uploadStatus" style="font-size: 0.85rem; opacity: 0.7;"></span>
            </div>
            <div id="admin-map-wrapper">
                <canvas id="admin-map-canvas"></canvas>
            </div>
            <div class="point-toolbar">
                <input type="text" id="pointSearch" placeholder="Search point (e.g., Gate B2)">
                <button class="earth-btn ghost" onclick="resetAdminPath()">Reset</button>
            </div>
            <div id="admin-point-list" class="point-list"></div>
            <p style="font-size: 0.85rem; color: rgba(255,255,255,0.6); margin-top: 10px;">
                * Nuqtani bosing — yo'nalish kioskdan tanlangan nuqtaga chiziladi.
            </p>
        </div>

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

        async function testIFlytekTTS() {
            const btn = document.getElementById('btn-test-iflytek');
            const originalText = btn.innerHTML;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> ...';
            btn.disabled = true;

            console.log("iFlytek TTS Test boshlandi...");
            try {
                // 1. Get Auth URL
                const authRes = await fetch('../api/iflytek_auth.php?type=tts');
                const authData = await authRes.json();
                if (!authData.url) throw new Error("URL olinmadi");

                console.log("Auth URL olindi:", authData.url);

                // 2. WebSocket Connection
                const socket = new WebSocket(authData.url);
                let audioChunks = [];

                socket.onopen = () => {
                   console.log("WS Ulandi, so'rov yuborilmoqda...");
                   const params = {
                       "common": { "app_id": "<?php echo IFLYTEK_APPID; ?>" },
                       "business": { 
                           "aue": "raw", 
                           "vcn": "xiaoyan", 
                           "speed": 50,
                           "pitch": 50,
                           "volume": 50,
                           "tte": "UTF8",
                           "ent": "mts",
                           "auf": "audio/L16;rate=16000"
                       },
                       "data": { 
                           "status": 2, 
                           "text": btoa(unescape(encodeURIComponent("Assalomu alaykum, iFlytek tizimi muvaffaqiyatli ulandi."))) 
                       }
                   };
                   socket.send(JSON.stringify(params));
                };

                socket.onmessage = (e) => {
                    const res = JSON.parse(e.data);
                    if (res.code !== 0) {
                        console.error("iFlytek Full Error Object:", res);
                        if (res.code === 11201) {
                            alert("iFlytek Xatosi 11201: Xizmat faollashtirilmagan yoki bepul kvota tugagan. Iltimos, console.xfyun.cn orqali 'Text to Speech' xizmatini yoqing.");
                        } else {
                            alert("iFlytek Xatosi: " + res.message + " (Code: " + res.code + ")\nSID: " + res.sid);
                        }
                        socket.close();
                        return;
                    }
                    if (res.data && res.data.audio) {
                        const binary = atob(res.data.audio);
                        const array = new Uint8Array(binary.length);
                        for (let i = 0; i < binary.length; i++) array[i] = binary.charCodeAt(i);
                        audioChunks.push(array);
                    }
                    if (res.data.status === 2) {
                        console.log("Audio to'liq qabul qilindi.");
                        playAudioChunks(audioChunks);
                        socket.close();
                    }
                };

                socket.onerror = (err) => console.error("WS Xatosi:", err);
                socket.onclose = () => console.log("WS Yopildi.");

            } catch (err) {
                console.error(err);
                alert("iFlytek Test Xatosi: " + err.message);
            } finally {
                btn.innerHTML = originalText;
                btn.disabled = false;
            }
        }

        let sttRecorder = null;
        let sttChunks = [];
        
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

        let iflytekSTTTestClient = null;
        let iflytekSTTTestStream = null;
        let iflytekSTTTestProcessor = null;
        let iflytekAccumulatedText = "";

        async function testIFlytekSTT() {
            const btn = document.getElementById('btn-test-iflytek-stt');
            if (iflytekSTTTestClient) {
                iflytekSTTTestClient.stop();
                if (iflytekSTTTestStream) {
                    iflytekSTTTestStream.getTracks().forEach(t => t.stop());
                }
                if (iflytekSTTTestProcessor) {
                    iflytekSTTTestProcessor.disconnect();
                }
                iflytekSTTTestClient = null;
                btn.innerHTML = '<i class="fas fa-headphones"></i> iFLYTEK STT';
                btn.style.background = '#00c6ff';
                if (iflytekAccumulatedText) {
                    alert("iFlytek STT Natijasi:\n" + iflytekAccumulatedText);
                }
                return;
            }

            try {
                btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> iFLYTEK ESHITMOQDA...';
                btn.style.background = '#ffc107';
                iflytekAccumulatedText = "";
                const { IFlytekSTT } = await import('../js/iflytek_stt.js?v=' + Date.now());
                
                const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
                iflytekSTTTestStream = stream;
                
                const audioCtx = new (window.AudioContext || window.webkitAudioContext)();
                
                // AudioWorklet ishlatamiz (Deprecation olib tashlash uchun)
                await audioCtx.audioWorklet.addModule('../recorder-worklet.js');
                const processor = new AudioWorkletNode(audioCtx, 'recorder-worklet');
                iflytekSTTTestProcessor = processor;

                const source = audioCtx.createMediaStreamSource(stream);

                iflytekSTTTestClient = new IFlytekSTT(
                    () => { console.log('iflytek stt connected'); },
                    (text, isReplace) => {
                         if (!text) return;
                         if (isReplace) {
                             iflytekAccumulatedText = text;
                         } else {
                             iflytekAccumulatedText += text;
                         }
                         const sttContent = document.getElementById('iflySTTContent');
                         if (sttContent) {
                             sttContent.innerText = iflytekAccumulatedText;
                         }
                         console.log("iFlytek STT Yozmoqda: ", iflytekAccumulatedText);
                    },
                    (err) => { 
                         const sttContent = document.getElementById('iflySTTContent');
                         if (sttContent) {
                             sttContent.innerHTML = '<span style="color:#ff5252">Xatolik: ' + err + '</span>';
                         }
                         if (iflytekSTTTestStream) iflytekSTTTestStream.getTracks().forEach(t=>t.stop());
                         iflytekSTTTestClient = null;
                         btn.innerHTML = '<i class="fas fa-headphones"></i> iFLYTEK STT';
                         btn.style.background = '#00c6ff';
                    },
                    () => {
                         if (iflytekSTTTestStream) iflytekSTTTestStream.getTracks().forEach(t=>t.stop());
                         iflytekSTTTestClient = null;
                         btn.innerHTML = '<i class="fas fa-headphones"></i> iFLYTEK STT';
                         btn.style.background = '#00c6ff';
                    }
                );
                
                const authUrl = new URL('../api/iflytek_auth.php?type=stt', window.location.href).href;
                await iflytekSTTTestClient.connect('uz', authUrl);

                processor.port.onmessage = (e) => {
                    if (!iflytekSTTTestClient) return;
                    const inputData = e.data;
                    const ratio = audioCtx.sampleRate / 16000;
                    const result = new Float32Array(Math.round(inputData.length / ratio));
                    let offsetResult = 0;
                    let offsetBuffer = 0;
                    while (offsetResult < result.length) {
                        let nextOffsetBuffer = Math.round((offsetResult + 1) * ratio);
                        let accum = 0, count = 0;
                        for (let i = offsetBuffer; i < nextOffsetBuffer && i < inputData.length; i++) {
                            accum += inputData[i];
                            count++;
                        }
                        result[offsetResult] = accum / count;
                        offsetResult++;
                        offsetBuffer = nextOffsetBuffer;
                    }
                    iflytekSTTTestClient.sendAudio(result, false);
                };

                source.connect(processor);
                processor.connect(audioCtx.destination);
                
                // Modal ochish
                const sttModal = document.getElementById('iflySTTModal');
                const sttContent = document.getElementById('iflySTTContent');
                sttContent.innerHTML = '<span style="color:rgba(255,255,255,0.5)">Eshityapman...</span>';
                sttModal.classList.add('active');
                
            } catch(e) {
                const sttContent = document.getElementById('iflySTTContent');
                if (sttContent) {
                    sttContent.innerHTML = '<span style="color:#ff5252">Xatolik: ' + e.message + '</span>';
                }
                const sttModal = document.getElementById('iflySTTModal');
                if (sttModal && !sttModal.classList.contains('active')) {
                    sttModal.classList.add('active');
                }
                btn.innerHTML = '<i class="fas fa-headphones"></i> iFLYTEK STT';
                btn.style.background = '#00c6ff';
                iflytekSTTTestClient = null;
            }
        }

        function closeIflySTT() {
            document.getElementById('iflySTTModal').classList.remove('active');
            if (iflytekSTTTestClient) {
                testIFlytekSTT(); // Stop it if it's running
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

        window.addEventListener('load', () => {
            initAdminMapTest();
        });
    </script>

            </p>
        </div>
    </div>

    <!-- iFlytek STT Modal -->
    <div id="iflySTTModal" class="modal">
        <div class="modal-content" style="max-width: 600px; text-align: center;">
            <span class="close-btn" onclick="closeIflySTT()">&times;</span>
            <h2 style="margin-top: 0; color: #00c6ff;"><i class="fas fa-headphones"></i> iFlytek STT Test</h2>
            <div id="iflySTTContent" style="background: rgba(0,0,0,0.3); padding: 20px; border-radius: 10px; min-height: 100px; margin: 20px 0; font-size: 1.2rem; line-height: 1.6; text-align: left; border: 1px solid rgba(0,198,255,0.2);">
                Eshityapman...
            </div>
            <button onclick="closeIflySTT()" class="status-pill" style="cursor: pointer; background: #ff5252; color: white; border: none; padding: 10px 20px; font-weight: 600;">TO'XTATISH</button>
        </div>
    </div>
<?php $page->renderFooter(); ?>

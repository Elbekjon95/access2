<?php
require_once 'config.php';
require_once 'api/iflytek_helper.php';

session_start();
if (!isset($_SESSION['user_id'])) {
    die("Avval tizimga kiring.");
}
?>
<!DOCTYPE html>
<html lang="uz">
<head>
    <meta charset="UTF-8">
    <title>Voice Compare Test - iFlytek vs UzbekVoice</title>
    <style>
        body { font-family: 'Outfit', sans-serif; background: #0f172a; color: white; padding: 2rem; }
        .card { background: rgba(30, 41, 59, 0.7); border: 1px solid #334155; padding: 1.5rem; border-radius: 12px; margin-bottom: 2rem; }
        h1 { color: #38bdf8; }
        .grid { display: grid; grid-template-columns: 1fr 1fr; gap: 2rem; }
        button { background: #38bdf8; color: #0f172a; border: none; padding: 10px 20px; border-radius: 6px; cursor: pointer; font-weight: bold; }
        pre { background: #000; padding: 10px; border-radius: 5px; overflow-x: auto; font-size: 0.8rem; }
    </style>
</head>
<body>
    <h1>Ovozli Xizmatlar Qiyosiy Testi</h1>
    
    <div class="card">
        <h2>iFlytek Integratsiyasi</h2>
        <p><strong>STT Auth URL:</strong> <code id="if-stt-url">Yuklanmoqda...</code></p>
        <p><strong>TTS Auth URL:</strong> <code id="if-tts-url">Yuklanmoqda...</code></p>
    </div>

    <div class="grid">
        <div class="card">
            <h2>iFlytek (Yangi)</h2>
            <p>Til: O'zbek</p>
            <p>Ovoz: xiaoyan</p>
            <button onclick="testIFlytek()">Test TTS (WebSocket)</button>
            <div id="if-res"></div>
        </div>
        
        <div class="card">
            <h2>UzbekVoice (Mavjud)</h2>
            <p>API Key: <?php echo empty(UZBEKVOICE_API_KEY) ? "❌ Yo'q" : "✅ Bor"; ?></p>
            <p>Ovoz: Lola</p>
            <button onclick="testUzbekVoice()">Test TTS (REST)</button>
            <div id="uv-res"></div>
        </div>
    </div>

    <script>
        async function loadUrls() {
            try {
                const rStt = await fetch('api/iflytek_auth.php?type=stt');
                const dStt = await rStt.json();
                document.getElementById('if-stt-url').innerText = dStt.url.substring(0, 100) + '...';

                const rTts = await fetch('api/iflytek_auth.php?type=tts');
                const dTts = await rTts.json();
                document.getElementById('if-tts-url').innerText = dTts.url.substring(0, 100) + '...';
            } catch(e) {
                console.error(e);
            }
        }
        loadUrls();

        function testIFlytek() {
            alert("iFlytek WebSocket testi uchun asosiy Kiosk interfeysidan foydalaning (O'zbek tili tanlangan holda).");
        }

        async function testUzbekVoice() {
            const res = await fetch('api/tts.php?text=Salom, men UzbekVoice orqali gapiryapman');
            if(res.ok) {
                const blob = await res.blob();
                const url = URL.createObjectURL(blob);
                const audio = new Audio(url);
                audio.play();
                document.getElementById('uv-res').innerHTML = '<p style="color: #4ade80;">✅ Audio chalinyapti...</p>';
            } else {
                document.getElementById('uv-res').innerHTML = '<p style="color: #ef4444;">❌ Xatolik yuz berdi</p>';
            }
        }
    </script>
</body>
</html>

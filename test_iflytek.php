<?php
require_once 'config.php';
?>
<!DOCTYPE html>
<html lang="uz">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>iFlytek TTS Voice Tester | AISCAN</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #00f2fe;
            --secondary: #4facfe;
            --bg: #0f172a;
            --card-bg: rgba(30, 41, 59, 0.7);
            --text: #f8fafc;
        }

        body {
            font-family: 'Outfit', sans-serif;
            background: radial-gradient(circle at top left, #1e293b, #0f172a);
            color: var(--text);
            min-height: 100vh;
            margin: 0;
            padding: 2rem;
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        .container {
            max-width: 1000px;
            width: 100%;
        }

        header {
            text-align: center;
            margin-bottom: 3rem;
        }

        h1 {
            font-size: 2.5rem;
            background: linear-gradient(to right, var(--primary), var(--secondary));
            -webkit-background-clip: text;
            background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 0.5rem;
        }

        .controls {
            display: flex;
            gap: 1rem;
            margin-bottom: 2rem;
            justify-content: center;
        }

        input {
            background: var(--card-bg);
            border: 1px solid rgba(255, 255, 255, 0.1);
            color: white;
            padding: 0.8rem 1.5rem;
            border-radius: 12px;
            width: 400px;
            font-size: 1rem;
            outline: none;
        }

        button {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            border: none;
            color: #0f172a;
            padding: 0.8rem 2rem;
            border-radius: 12px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        button:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0, 242, 254, 0.4);
        }

        .voice-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 1.5rem;
        }

        .voice-card {
            background: var(--card-bg);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.05);
            padding: 1.5rem;
            border-radius: 20px;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .voice-card:hover {
            border-color: var(--primary);
            transform: scale(1.02);
        }

        .voice-name {
            font-weight: 600;
            font-size: 1.2rem;
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .voice-code {
            font-family: monospace;
            color: var(--primary);
            font-size: 0.9rem;
            background: rgba(0, 242, 254, 0.1);
            padding: 0.2rem 0.5rem;
            border-radius: 6px;
        }

        .status {
            font-size: 0.85rem;
            margin-top: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .status-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: #64748b;
        }

        .status-success .status-dot { background: #10b981; }
        .status-error .status-dot { background: #ef4444; }
        .status-loading .status-dot { background: #f59e0b; animation: pulse 1s infinite; }

        @keyframes pulse {
            0% { opacity: 1; }
            50% { opacity: 0.4; }
            100% { opacity: 1; }
        }

        .test-btn {
            margin-top: 1rem;
            width: 100%;
            background: rgba(255, 255, 255, 0.05);
            color: white;
            padding: 0.6rem;
            font-size: 0.9rem;
        }

        .test-btn:hover {
            background: rgba(255, 255, 255, 0.1);
            box-shadow: none;
        }

        .result-msg {
            font-size: 0.8rem;
            color: #94a3b8;
            margin-top: 0.5rem;
            word-break: break-all;
        }
    </style>
</head>
<body>

<div class="container">
    <header>
        <h1>iFlytek TTS Voice Tester</h1>
        <p>Barcha mavjud suhandonlarni WebSocket orqali interaktiv test qiling</p>
    </header>

    <div class="controls">
        <input type="text" id="test-text" value="Assalomu alaykum! AISCAN terminaliga xush kelibsiz. Parvozingiz xayrli bo'lsin.">
        <button onclick="testAll()">Hamma ovozlarni sinash</button>
    </div>

    <div class="voice-grid" id="voice-grid">
        <!-- JS orqali to'ldiriladi -->
    </div>
</div>

<script type="module">
    import { state } from "./js/config.js";
    import { speakIFlytek } from "./js/iflytek_tts.js";

    // iFlytek hujjatlaridagi barcha ma'lum VCN kodlari
    const voices = [
        { vcn: "xiaoyan", name: "Xiaoyan (Standard)", gender: "Ayol", info: "Multilingual / Ko'p tilli" },
        { vcn: "x2_UzUz_Nigina", name: "Nigina (Uzbek)", gender: "Ayol", info: "O'zbek tili uchun maxsus" },
        { vcn: "aisjiaying", name: "Jiaying (Premium)", gender: "Ayol", info: "Multilingual / Yuqori sifat" },
        { vcn: "aisxping", name: "Xiping (Standard)", gender: "Erkak", info: "Multilingual / Erkak ovozi" },
        { vcn: "x2_RuRu_Keshu", name: "Keshu (Russian)", gender: "Ayol", info: "Rus tili va ko'p tilli" },
        { vcn: "rania", name: "Rania (Arabic)", gender: "Ayol", info: "Arab va ko'p tilli" },
        { vcn: "mohamed", name: "Mohamed (Arabic)", gender: "Erkak", info: "Arab tili" },
        { vcn: "x4_EnUk_Lizzy_assist", name: "Lizzy (UK)", gender: "Ayol", info: "Ingliz tili (UK)" },
        { vcn: "x4_lingxiaoyue_oral", name: "Lingxiaoyue (Oral)", gender: "Ayol", info: "Chinese / Oral (Hujjatdagi)" },
        { vcn: "aisjinger", name: "Jinger (Lively)", gender: "Ayol", info: "Multilingual / Quvnoq" },
        { vcn: "aisbabyxu", name: "Baby Xu", gender: "Ayol", info: "Multilingual / Bolalar" },
        { vcn: "aisannie", name: "Annie", gender: "Ayol", info: "English / Inglis tili" },
        { vcn: "aisem", name: "Emma", gender: "Ayol", info: "English / Inglis tili" },
        { vcn: "aisjake", name: "Jake", gender: "Erkak", info: "English / Inglis tili" },
        { vcn: "yefeng", name: "Yefeng", gender: "Erkak", info: "Russian / Rus tili" },
        { vcn: "vinar", name: "Vina", gender: "Ayol", info: "Indonesian / Indonez" },
        { vcn: "mariach", name: "Mariach", gender: "Ayol", info: "Chinese / Xitoy tili" }
    ];

    const voiceGrid = document.getElementById('voice-grid');

    function createCard(voice) {
        const card = document.createElement('div');
        card.className = 'voice-card';
        card.id = `card-${voice.vcn}`;
        card.innerHTML = `
            <div class="voice-name">
                ${voice.name}
                <span class="voice-code">${voice.vcn}</span>
            </div>
            <div style="font-size: 0.8rem; color: #94a3b8; margin-bottom: 0.5rem;">
                ${voice.gender} | ${voice.info}
            </div>
            <div class="status" id="status-${voice.vcn}">
                <div class="status-dot"></div>
                <span class="status-text">Kutilmoqda...</span>
            </div>
            <div class="result-msg" id="msg-${voice.vcn}"></div>
            <button class="test-btn" id="btn-${voice.vcn}">Ovozni sinash</button>
        `;
        
        card.querySelector('.test-btn').onclick = () => testVoice(voice);
        return card;
    }

    voices.forEach(v => voiceGrid.appendChild(createCard(v)));

    async function testVoice(voice) {
        const statusDiv = document.getElementById(`status-${voice.vcn}`);
        const statusText = statusDiv.querySelector('.status-text');
        const msgDiv = document.getElementById(`msg-${voice.vcn}`);
        const text = document.getElementById('test-text').value;

        statusDiv.className = 'status status-loading';
        statusText.innerText = 'Ulanmoqda...';
        msgDiv.innerText = '';

        console.log(`🧪 Testing iFlytek Voice [${voice.vcn}]...`);

        try {
            // override vcn for test
            // bizga iflytek_tts.js dagi funksiyani biroz o'zgartirish kerak yoki context orqali berish kerak
            // hozircha iflytek_tts.js dagi vcn ni vaqtincha almashtira olmaymiz
            // Shuning uchun speakIFlytek funksiyasini ikkinchi argument bilan chaqiramiz
            await speakIFlytek(text, voice.vcn);
            
            statusDiv.className = 'status status-success';
            statusText.innerText = 'Ishlamoqda!';
            console.log(`✅ Voice [${voice.vcn}] ISHLADI!`);
        } catch (err) {
            statusDiv.className = 'status status-error';
            statusText.innerText = 'Xato!';
            const errMsg = err.message || JSON.stringify(err);
            msgDiv.innerText = errMsg;
            console.error(`❌ Voice [${voice.vcn}] XATO:`, err);
        }
    }

    window.testAll = async () => {
        for (const v of voices) {
            await testVoice(v);
            await new Promise(r => setTimeout(r, 1000)); // Har biri orasida kutish
        }
    };
</script>

</body>
</html>

<?php
require_once __DIR__ . '/../config.php';
secureSessionStart();

if (!isset($_SESSION['user_id']) && !isset($_SESSION['admin_id'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Ruxsat yo\'q']);
    exit;
}
require_once 'uzbek_helper.php'; // O'zbek lug'at helper
require_once 'iflytek_helper.php'; // iFlytek helper

header('Content-Type: application/json');
ob_start();

try {
    if (!isset($_FILES['audio'])) {
        ob_clean();
        echo json_encode(['error' => 'Audio fayl yuborilmadi']);
        exit;
    }

    $audioFile = $_FILES['audio']['tmp_name'];
    $lang = $_POST['language'] ?? 'uz';
    if (!in_array($lang, ['uz', 'ru', 'en', 'es', 'zh', 'hi', 'ar', 'bn', 'pt', 'ja', 'de', 'fr', 'it', 'ko', 'tr', 'ur', 'tg', 'ky', 'kk', 'tk', 'auto'], true)) {
        $lang = 'uz';
    }

    if ($lang === 'uz') {
        $prompt = "O'zbek tili. Aerovokzal yordamchisi.";
    } else {
        $promptMap = [
            'ru' => "Русский язык. Ассистент аэропорта.",
            'en' => "English. Airport assistant."
        ];
        $prompt = $promptMap[$lang] ?? "Airport assistant.";
    }
    
    error_log("STT DEBUG - Language: $lang, Prompt: $prompt");


    function callUzbekVoiceSTT($audioFile, $apiKey) {
        $curl = curl_init();
        $postFields = [
            'file' => new CURLFile($audioFile, 'audio/wav', 'recording.wav'),
            'return_offsets' => 'false',
            'run_diarization' => 'false',
            'language' => 'uz',
            'blocking' => 'true'
        ];
        
        curl_setopt_array($curl, [
            CURLOPT_URL => "https://uzbekvoice.ai/api/v1/stt",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $postFields,
            CURLOPT_HTTPHEADER => [
                "Authorization: " . $apiKey
            ],
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_TIMEOUT => 30
        ]);

        $response = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $err = curl_error($curl);
        curl_close($curl);

        return ['response' => $response, 'httpCode' => $httpCode, 'error' => $err, 'engine' => 'uzbekvoice'];
    }

    function callGroqWhisper($audioFile, $apiKey, $lang, $prompt) {
        $curl = curl_init();
        $postFields = [
            'file' => new CURLFile($audioFile, 'audio/wav', 'recording.wav'),
            'model' => 'whisper-large-v3-turbo', // Tezroq versiya
            'prompt' => $prompt,
            'temperature' => 0.0, // Eng aniq natija
            'response_format' => 'verbose_json' // Ko'proq ma'lumot
        ];
        if ($lang !== 'auto') {
            $postFields['language'] = $lang;
        }
        curl_setopt_array($curl, [
            CURLOPT_URL => "https://api.groq.com/openai/v1/audio/transcriptions",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $postFields,
            CURLOPT_HTTPHEADER => [
                "Authorization: Bearer " . $apiKey
            ],
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false
        ]);

        $response = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $err = curl_error($curl);
        curl_close($curl);

        return ['response' => $response, 'httpCode' => $httpCode, 'error' => $err, 'engine' => 'groq'];
    }

    $res = null;
    $usedEngine = '';
    
    if ($lang === 'uz') {
        error_log("🎯 STT ENGINE SELECTION: Uzbek → UzbekVoice (Primary)");
        $res = callUzbekVoiceSTT($audioFile, UZBEKVOICE_API_KEY);
        $usedEngine = 'uzbekvoice';
        
        // Fallback: Agar UzbekVoice ishlamasa (masalan 500 kelsa yoki error bo'lsa), Groq ga o'tish
        if (!empty($res['error']) || $res['httpCode'] >= 400 || !json_decode($res['response'])) {
            error_log("⚠️ UzbekVoice failed (HTTP {$res['httpCode']}). FALLING BACK to Whisper Turbo!");
            $res = callGroqWhisper($audioFile, GROQ_API_KEY, $lang, $prompt);
            $usedEngine = 'groq';
        }
    } else {
        error_log("🎯 STT ENGINE SELECTION: $lang → Whisper Turbo");
        $res = callGroqWhisper($audioFile, GROQ_API_KEY, $lang, $prompt);
        $usedEngine = 'groq';
    }

    ob_clean();
    if ($res['error']) {
        error_log("❌ STT ERROR ({$res['engine']}): " . $res['error']);
        echo json_encode(['error' => 'STT xatosi: ' . $res['error']]);
    } else {
        error_log("✅ STT SUCCESS - Engine: {$usedEngine}, HTTP: " . $res['httpCode']);
        $result = json_decode($res['response'], true);
        
        $transcription = '';
        if ($res['engine'] === 'uzbekvoice') {
            $transcription = trim($result['text'] ?? $result['result']['text'] ?? '');
        } else {
            $transcription = trim($result['text'] ?? '');
        }
        
        if (!empty($transcription)) {
            error_log("📝 STT RAW TRANSCRIPTION ({$usedEngine}): " . $transcription);
            
            if ($lang === 'uz' && class_exists('UzbekDictionaryHelper')) {
                $normalized = UzbekDictionaryHelper::normalizeText($transcription);
                if ($normalized !== $transcription) {
                    error_log("🔄 STT NORMALIZED: $transcription → $normalized");
                    $transcription = $normalized;
                }
            }
            
            error_log("✅ STT FINAL TEXT ({$usedEngine}): " . $transcription);
            echo json_encode([
                'text' => $transcription,
                'language' => $result['language'] ?? $lang,
                'duration' => $result['duration'] ?? null,
                'engine' => $usedEngine // Debug info
            ]);
        } else {
            error_log("❌ STT ERROR - No text in response from {$usedEngine}: " . json_encode($result));
            echo json_encode(['error' => 'STT transkripsiyasi bo\'sh qaytdi', 'details' => $result, 'engine' => $usedEngine]);
        }
    }
} catch (Throwable $e) {
    if (ob_get_level() > 0) ob_clean();
    error_log("🔥 FATAL ERROR in stt.php: " . $e->getMessage());
    echo json_encode(['error' => 'STT Ichki xatosi: ' . $e->getMessage()]);
}

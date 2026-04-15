<?php
require_once '../config.php';

header('Content-Type: application/json');
ob_start();

try {
    if (!isset($_FILES['audio'])) {
        ob_clean();
        echo json_encode(['error' => 'Audio fayl yuborilmadi']);
        exit;
    }

    $audioFile = $_FILES['audio']['tmp_name'];
    $language  = trim($_POST['language'] ?? 'auto');

    // --- UzbekVoice STT (faqat o'zbek tili uchun) ---
    function callUzbekVoiceSTT($audioFile, $apiKey) {
        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL            => "https://uzbekvoice.ai/api/v1/stt",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => [
                'file'     => new CURLFile($audioFile, 'audio/wav', 'recording.wav'),
                'language' => 'uz',
                'blocking' => 'true'
            ],
            CURLOPT_HTTPHEADER   => ["Authorization: " . $apiKey],
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_TIMEOUT      => 30
        ]);
        $response = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);
        return ['response' => $response, 'httpCode' => $httpCode];
    }

    // --- Groq Whisper Large STT (ko'p tilli) ---
    function callGroqWhisperSTT($audioFile, $apiKey, $language = null) {
        // Groq language kodi xaritasi (frontend kod → Whisper kodi)
        $langMap = [
            'ru' => 'ru', 'en' => 'en', 'ar' => 'ar',
            'fr' => 'fr', 'de' => 'de', 'es' => 'es',
            'zh' => 'zh', 'hi' => 'hi', 'ko' => 'ko',
            'ja' => 'ja', 'tr' => 'tr', 'it' => 'it',
        ];

        $postFields = [
            'file'            => new CURLFile($audioFile, 'audio/wav', 'recording.wav'),
            'model'           => 'whisper-large-v3',
            'response_format' => 'json',
            'temperature'     => '0',
        ];

        // Til berilgan bo'lsa — aniq ko'd yuboramiz, aks holda auto detect
        if ($language && isset($langMap[$language])) {
            $postFields['language'] = $langMap[$language];
        }

        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL            => "https://api.groq.com/openai/v1/audio/transcriptions",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $postFields,
            CURLOPT_HTTPHEADER     => [
                "Authorization: Bearer " . $apiKey,
            ],
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_TIMEOUT        => 30
        ]);
        $response = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $curlError = curl_error($curl);
        curl_close($curl);
        return ['response' => $response, 'httpCode' => $httpCode, 'curlError' => $curlError];
    }

    // --- Til asosida engine tanlash ---
    if ($language === 'uz') {
        // O'zbek tili — UzbekVoice
        $res = callUzbekVoiceSTT($audioFile, UZBEKVOICE_API_KEY);
        $result = json_decode($res['response'], true);
        $transcription = trim($result['text'] ?? $result['result']['text'] ?? '');
        $detectedLang = 'uz';
        $engine = 'uzbekvoice';
    } else {
        // Boshqa barcha tillar (ru, en, ar, auto...) — Groq Whisper Large
        $whisperLang = ($language !== 'auto') ? $language : null;
        $res = callGroqWhisperSTT($audioFile, GROQ_API_KEY, $whisperLang);
        $result = json_decode($res['response'], true);
        $transcription = trim($result['text'] ?? '');
        $detectedLang = $language;
        $engine = 'groq-whisper-large-v3';

        // Groq xato bo'lsa — UzbekVoice ga fallback
        if (empty($transcription) && !empty(UZBEKVOICE_API_KEY)) {
            error_log("[STT] Groq failed (HTTP {$res['httpCode']}), fallback to UzbekVoice");
            $res2 = callUzbekVoiceSTT($audioFile, UZBEKVOICE_API_KEY);
            $result2 = json_decode($res2['response'], true);
            $transcription = trim($result2['text'] ?? $result2['result']['text'] ?? '');
            $detectedLang = 'uz';
            $engine = 'uzbekvoice-fallback';
        }
    }

    ob_clean();
    if (empty($transcription)) {
        echo json_encode(['error' => 'STT transkripsiyasi bo\'sh qaytdi', 'engine' => $engine]);
    } else {
        echo json_encode([
            'text'     => $transcription,
            'language' => $detectedLang,
            'engine'   => $engine
        ]);
    }

} catch (Throwable $e) {
    if (ob_get_level() > 0) ob_clean();
    echo json_encode(['error' => $e->getMessage()]);
}

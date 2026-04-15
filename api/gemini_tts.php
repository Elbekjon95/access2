<?php
/**
 * ACCSESS - Gemini TTS (Google Cloud Text-to-Speech) API
 * Google Cloud TTS xizmati orqali matnni ovozlantiradi.
 */

require_once '../config.php';

header('Content-Type: application/json');

$apiKey = GEMINI_API_KEY;
if (empty($apiKey)) {
    echo json_encode(['error' => 'GEMINI_API_KEY topilmadi']);
    exit;
}

$action = $_GET['action'] ?? 'synthesize';

if ($action === 'list_voices') {
    $url = "https://texttospeech.googleapis.com/v1/voices?key=" . $apiKey;
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode === 200) {
        echo $response;
    } else {
        http_response_code($httpCode);
        echo json_encode(['error' => 'Ovozlar ro\'yxatini olib bo\'lmadi', 'details' => json_decode($response)]);
    }
    exit;
}

if ($action === 'synthesize') {
    $inputData = json_decode(file_get_contents('php://input'), true);
    $text = $inputData['text'] ?? '';
    $voiceName = $inputData['voice_name'] ?? 'uz-UZ-Standard-A'; // Default o'zbek tili agar bo'lsa
    $langCode = $inputData['language_code'] ?? 'uz-UZ';

    if (empty($text)) {
        echo json_encode(['error' => 'Matn bo\'sh bo\'lishi mumkin emas']);
        exit;
    }

    // v1beta1 Studio va yangi ovozlar uchun model parametrini qo'llab-quvvatlaydi
    $url = "https://texttospeech.googleapis.com/v1beta1/text:synthesize?key=" . $apiKey;
    
    $voiceParams = [
        'languageCode' => $langCode,
        'name' => $voiceName
    ];

    // Studio ovozlar uchun v1beta1 da model ko'rsatish shart
    if (strpos($voiceName, 'Studio') !== false) {
        $voiceParams['model'] = 'studio';
    } elseif (strpos($voiceName, 'Chirp') !== false) {
        $voiceParams['model'] = 'chirp-hd-1.0';
    }

    $postBody = [
        'input' => ['text' => $text],
        'voice' => $voiceParams,
        'audioConfig' => [
            'audioEncoding' => 'MP3'
        ]
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postBody));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode === 200) {
        $result = json_decode($response, true);
        if (isset($result['audioContent'])) {
            echo json_encode([
                'success' => true,
                'audioContent' => $result['audioContent'],
                'format' => 'mp3'
            ]);
        } else {
            error_log("TTS Error: Audio content not found. Response: " . $response);
            echo json_encode(['error' => 'Audio content topilmadi', 'details' => $result]);
        }
    } else {
        error_log("TTS Google API Error (HTTP $httpCode): " . $response);
        // Brauzer kodi 400 bo'lsa response body ni o'qimasligi mumkin, shuning uchun 200 qaytarib success: false beramiz
        echo json_encode(['error' => 'Google API xatosi', 'success' => false, 'details' => json_decode($response) ?: $response]);
    }
    exit;
}

echo json_encode(['error' => 'Noto\'g\'ri so\'rov']);

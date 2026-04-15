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
    
    // Uzbek Voice STT funksiyasi
    function callUzbekVoiceSTT($audioFile, $apiKey) {
        $curl = curl_init();
        $postFields = [
            'file' => new CURLFile($audioFile, 'audio/wav', 'recording.wav'),
            'language' => 'uz',
            'blocking' => 'true'
        ];
        
        curl_setopt_array($curl, [
            CURLOPT_URL => "https://uzbekvoice.ai/api/v1/stt",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $postFields,
            CURLOPT_HTTPHEADER => ["Authorization: " . $apiKey],
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_TIMEOUT => 30
        ]);

        $response = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);

        return ['response' => $response, 'httpCode' => $httpCode];
    }

    $res = callUzbekVoiceSTT($audioFile, UZBEKVOICE_API_KEY);
    $result = json_decode($res['response'], true);
    $transcription = trim($result['text'] ?? $result['result']['text'] ?? '');

    ob_clean();
    if (empty($transcription)) {
        echo json_encode(['error' => 'STT transkripsiyasi bo\'sh qaytdi']);
    } else {
        echo json_encode([
            'text' => $transcription,
            'language' => 'uz',
            'engine' => 'uzbekvoice'
        ]);
    }

} catch (Throwable $e) {
    if (ob_get_level() > 0) ob_clean();
    echo json_encode(['error' => $e->getMessage()]);
}

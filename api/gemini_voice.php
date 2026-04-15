<?php
/**
 * ACCSESS - Gemini Aoede - PERFORMANCE OPTIMIZED
 */

require_once '../config.php';

header('Content-Type: application/json');

$apiKey = GEMINI_API_KEY;
if (empty($apiKey)) {
    echo json_encode(['error' => 'GEMINI_API_KEY topilmadi']);
    exit;
}

$inputData = json_decode(file_get_contents('php://input'), true);
$text = $inputData['text'] ?? '';
$voiceName = $inputData['voice_name'] ?? 'Aoede'; 

if (empty($text)) {
    echo json_encode(['error' => 'Empty text']);
    exit;
}

require_once __DIR__ . '/classes/GeminiRestTTS.php';

/**
 * Raw PCM to WAV (High Quality 24kHz)
 */
function wrapPcmToWav($pcmData) {
    if (empty($pcmData)) return null;
    $sampleRate = 24000;
    $header = "RIFF" . pack("V", strlen($pcmData) + 36) 
            . "WAVEfmt " . pack("V", 16) 
            . pack("v", 1) . pack("v", 1) 
            . pack("V", $sampleRate) . pack("V", $sampleRate * 2) 
            . pack("v", 2) . pack("v", 16) 
            . "data" . pack("V", strlen($pcmData));
    return $header . $pcmData;
}

try {
    $client = new GeminiRestTTS($apiKey, 'models/gemini-2.5-flash-preview-tts');
    $rawAudio = $client->synthesize($text, $voiceName);
    
    if ($rawAudio && strlen($rawAudio) > 100) {
        $audioContent = base64_encode(wrapPcmToWav($rawAudio));
        echo json_encode([
            'success' => true, 
            'audioContent' => $audioContent, 
            'mimeType' => 'audio/wav', 
            'model' => 'gemini-2.5-flash-24k'
        ]);
        exit;
    } else {
        throw new Exception("Audio output empty or model error");
    }
} catch (Exception $e) {
    echo json_encode([
        'error' => "Gemini TTS error",
        'details' => $e->getMessage()
    ]);
}

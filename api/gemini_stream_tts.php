<?php
/**
 * ACCSESS - Gemini Streaming TTS (SSE)
 * 
 * Matnni jumlalarga bo'lib, har birini Gemini TTS ga yuborib,
 * audio chunklar tayyor bo'lganda darhol SSE orqali frontendga yuboradi.
 * 
 * Cache: md5(jumla) → cache/tts/*.wav saqlanadi.
 */

require_once '../config.php';

// SSE headers
header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('Connection: keep-alive');
header('X-Accel-Buffering: no'); // Nginx buffering o'chirish

// PHP output buffering o'chirish
if (ob_get_level()) ob_end_clean();
ini_set('output_buffering', 'off');
ini_set('zlib.output_compression', false);

$apiKey = GEMINI_API_KEY;
if (empty($apiKey)) {
    sendSSE('error', ['message' => 'GEMINI_API_KEY topilmadi']);
    exit;
}

$inputData = json_decode(file_get_contents('php://input'), true);
$text = $inputData['text'] ?? '';
$voiceName = $inputData['voice_name'] ?? 'Aoede';

if (empty($text)) {
    sendSSE('error', ['message' => 'Empty text']);
    exit;
}

// Cache papkasni tekshirish
$cacheDir = __DIR__ . '/../cache/tts';
if (!is_dir($cacheDir)) {
    @mkdir($cacheDir, 0777, true);
}

require_once __DIR__ . '/classes/GeminiLiveClient.php';
require_once __DIR__ . '/classes/GeminiRestTTS.php';

/**
 * SSE event yuborish
 */
function sendSSE($event, $data) {
    echo "event: {$event}\n";
    echo "data: " . json_encode($data, JSON_UNESCAPED_UNICODE) . "\n\n";
    if (ob_get_level()) ob_flush();
    flush();
}

/**
 * Raw PCM → WAV (High Quality 24kHz)
 */
function pcmToWav($pcmData) {
    if (empty($pcmData)) return null;
    $sampleRate = 24000; // Max quality for dedicated TTS
    $header = "RIFF" . pack("V", strlen($pcmData) + 36) 
            . "WAVEfmt " . pack("V", 16) 
            . pack("v", 1) . pack("v", 1) 
            . pack("V", $sampleRate) . pack("V", $sampleRate * 2) 
            . pack("v", 2) . pack("v", 16) 
            . "data" . pack("V", strlen($pcmData));
    return $header . $pcmData;
}

/**
 * Matnni jumlalarga bo'lish (tinish belgilari bo'yicha)
 */
function splitIntoSentences($text) {
    // Nuqta, undov, savol, nuqtali vergul bo'yicha ajratish
    $sentences = preg_split('/(?<=[.!?;])\s+/u', $text, -1, PREG_SPLIT_NO_EMPTY);
    
    if (empty($sentences)) return [$text];
    
    // Juda qisqa jumlalarni birlashtirish (15 belgidan kam)
    $merged = [];
    $buffer = '';
    foreach ($sentences as $sentence) {
        $sentence = trim($sentence);
        if (empty($sentence)) continue;
        
        if (mb_strlen($buffer . ' ' . $sentence) <= 150) {
            $buffer = trim($buffer . ' ' . $sentence);
        } else {
            if (!empty($buffer)) $merged[] = $buffer;
            $buffer = $sentence;
        }
    }
    if (!empty($buffer)) $merged[] = $buffer;
    
    // Agar bir jumla 300+ belgili bo'lsa, vergul bilan ham bo'lish
    $final = [];
    foreach ($merged as $chunk) {
        if (mb_strlen($chunk) > 300) {
            $subParts = preg_split('/(?<=,)\s+/u', $chunk, -1, PREG_SPLIT_NO_EMPTY);
            $subBuffer = '';
            foreach ($subParts as $sub) {
                if (mb_strlen($subBuffer . ' ' . $sub) <= 200) {
                    $subBuffer = trim($subBuffer . ' ' . $sub);
                } else {
                    if (!empty($subBuffer)) $final[] = $subBuffer;
                    $subBuffer = $sub;
                }
            }
            if (!empty($subBuffer)) $final[] = $subBuffer;
        } else {
            $final[] = $chunk;
        }
    }
    
    return empty($final) ? [$text] : $final;
}

/**
 * TTS uchun matnni tozalash
 */
function cleanTextForTTS($text) {
    // Markdown belgilarini olib tashlash
    $text = preg_replace('/[*_~`#]+/', '', $text);
    // Qavslar ichidagi IATA kodlarni olib tashlash: (DME), (SVO)
    $text = preg_replace('/\s*\([A-Z]{2,4}\)/', '', $text);
    // URL va email olib tashlash
    $text = preg_replace('/https?:\/\/\S+/', '', $text);
    $text = preg_replace('/\S+@\S+/', '', $text);
    // Emoji olib tashlash (ASCII bo'lmagan maxsus belgilar)
    $text = preg_replace('/[\x{1F600}-\x{1F64F}\x{1F300}-\x{1F5FF}\x{1F680}-\x{1F6FF}\x{1F900}-\x{1F9FF}\x{2600}-\x{26FF}\x{2700}-\x{27BF}]/u', '', $text);
    // Ko'p bo'shliqlarni bir bo'shliqqa
    $text = trim(preg_replace('/\s+/', ' ', $text));
    return $text;
}

// ========== GEMINI 3.1 FLASH LIVE (WebSocket) TTS ==========

$sentences = splitIntoSentences($text);
$totalChunks = count($sentences);

sendSSE('start', [
    'totalChunks' => $totalChunks,
    'voice' => $voiceName,
    'model' => 'gemini-3.1-flash-live'
]);

/**
 * Pure TTS System Instruction
 * Agentni savollarga javob berishdan cheklaydi, faqat matn o'qishi uchun.
 */
$systemInstruction = "Siz professional airport suhandonisiz. Sizga berilgan matnni xech qanday o'zgarishsiz, savollarga javob bermasdan so'zma-so'z o'qib berishingiz shart. Matndan tashqari xech qanday gap qo'shmang.";

foreach ($sentences as $index => $sentence) {
    if (empty($sentence)) continue;
    
    $cacheKey = md5($sentence . '_' . $voiceName);
    $cacheFile = $cacheDir . '/' . $cacheKey . '.wav';
    
    if (file_exists($cacheFile) && filesize($cacheFile) > 100) {
        $audioData = file_get_contents($cacheFile);
        sendSSE('chunk', [
            'index' => $index,
            'total' => $totalChunks,
            'audio' => base64_encode($audioData),
            'mime' => 'audio/wav',
            'cached' => true,
            'text' => mb_substr($sentence, 0, 50) . '...'
        ]);
        continue;
    }
    
    try {
        // Barqaror Audio modelini ishlatamiz
        $client = new GeminiRestTTS($apiKey, 'models/gemini-2.5-flash-preview-tts');
        $audioRes = $client->synthesize($sentence, $voiceName);
        
        if ($audioRes && strlen($audioRes) > 100) {
            $wav = pcmToWav24k($audioRes);
            @file_put_contents($cacheFile, $wav);
            
            sendSSE('chunk', [
                'index' => $index,
                'total' => $totalChunks,
                'audio' => base64_encode($wav),
                'mime' => 'audio/wav',
                'cached' => false,
                'text' => mb_substr($sentence, 0, 50) . '...'
            ]);
        } else {
            throw new Exception("Audio output empty or model error");
        }
    } catch (Exception $e) {
        error_log("❌ TTS Error: " . $e->getMessage());
        sendSSE('chunk_error', [
            'index' => $index,
            'total' => $totalChunks,
            'error' => $e->getMessage(),
            'text' => mb_substr($sentence, 0, 50) . '...'
        ]);
    }
}

sendSSE('done', ['totalChunks' => $totalChunks]);

/**
 * High Quality 24kHz WAV Header
 */
function pcmToWav24k($pcmData) {
    if (empty($pcmData)) return null;
    $sampleRate = 24000; // Gemini REST API standard quality
    $header = "RIFF" . pack("V", strlen($pcmData) + 36) 
            . "WAVEfmt " . pack("V", 16) 
            . pack("v", 1) . pack("v", 1) 
            . pack("V", $sampleRate) . pack("V", $sampleRate * 2) 
            . pack("v", 2) . pack("v", 16) 
            . "data" . pack("V", strlen($pcmData));
    return $header . $pcmData;
}

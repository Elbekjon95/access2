<?php
require_once __DIR__ . '/../config.php';
secureSessionStart();

if (!isset($_SESSION['user_id']) && !isset($_SESSION['admin_id'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Ruxsat yo\'q']);
    exit;
}
require_once 'iflytek_helper.php'; // iFlytek helper

ob_start();

try {
    $input = json_decode(file_get_contents('php://input'), true);
    $text = $input['text'] ?? '';
    $lang = $input['language'] ?? 'uz';
    $mode = $_GET['mode'] ?? '';
    $returnChunks = ($mode === 'chunks');

    if (empty($text)) {
        header('Content-Type: application/json');
        ob_clean();
        http_response_code(400);
        echo json_encode(['error' => 'Matn yuborilmadi']);
        exit;
    }

    if ($lang !== 'uz') {
        header('Content-Type: application/json');
        ob_clean();
        echo json_encode(['info' => 'UzbekVoice faqat o\'zbek tili uchun, boshqa tillarda fallback ishlang.']);
        exit;
    }

    function spellNumberUzbek($number) {
        if ($number == 0) return 'nol';
        $isNegative = $number < 0;
        $number = abs((int)$number);
        
        $units = ['', 'ming', 'million', 'milliard', 'trillion'];
        $ones = ['', 'bir', 'ikki', 'uch', 'to\'rt', 'besh', 'olti', 'yetti', 'sakkiz', 'to\'qqiz'];
        $tens = ['', 'o\'n', 'yigirma', 'o\'ttiz', 'qirq', 'ellik', 'oltmish', 'yetmish', 'sakson', 'to\'qson'];
        
        $str = '';
        $unitIdx = 0;
        
        while ($number > 0) {
            $chunk = $number % 1000;
            $number = floor($number / 1000);
            
            if ($chunk > 0) {
                $chunkStr = '';
                $h = floor($chunk / 100);
                $t = floor(($chunk % 100) / 10);
                $o = $chunk % 10;
                
                if ($h > 0) $chunkStr .= $ones[$h] . ' yuz ';
                if ($t > 0) $chunkStr .= $tens[$t] . ' ';
                if ($o > 0) $chunkStr .= $ones[$o] . ' ';
                
                $str = trim($chunkStr) . ' ' . $units[$unitIdx] . ' ' . $str;
            }
            $unitIdx++;
        }
        
        $res = trim($str);
        if ($isNegative) $res = 'minus ' . $res;
        return $res;
    }

    function spellOutNumbers($t) {
        $map = ['0'=>'nol ', '1'=>'bir ', '2'=>'ikki ', '3'=>'uch ', '4'=>'to\'rt ', '5'=>'besh ', '6'=>'olti ', '7'=>'yetti ', '8'=>'sakkiz ', '9'=>'to\'qqiz '];
        
        // 1. Asosiy telefon raqamlar (masalan: +998 90 120 03 00 yoki 71 202-15-15)
        $t = preg_replace_callback('/(\+?998[\s\-]?\d{2}[\s\-]?\d{3}[\s\-]?\d{2}[\s\-]?\d{2}|\b\d{2}[\s\-]?\d{3}[\s\-]?\d{2}[\s\-]?\d{2}\b)/', function($m) use ($map) {
            $digits = preg_replace('/[^\d]/', '', $m[0]);
            $spoken = '';
            for($i=0; $i<strlen($digits); $i++) {
                $spoken .= $map[$digits[$i]];
            }
            return trim($spoken);
        }, $t);

        // 2. 4-xona kodi kabi qisqa (ichki) telefon yoki reys raqamlari (lekin soat 15:40 yoki narx 1.000 ga tegmaslik uchun)
        $t = preg_replace_callback('/(?<![:\.])\b\d{4}\b(?![:\.])/', function($m) use ($map) {
            $digits = $m[0];
            $spoken = '';
            for($i=0; $i<strlen($digits); $i++) {
                $spoken .= $map[$digits[$i]];
            }
            return trim($spoken);
        }, $t);

        // 3. Katta raqamlarni (valyutalar, narxlar) so'zga o'girish 
        $t = preg_replace_callback('/(-?)\b([1-9]\d{0,2}(?:(?:[\.\s]\d{3})+)|\d{4,12})\b/', function($m) {
            $numOnly = preg_replace('/[^\d]/', '', $m[2]);
            if ($m[1] === '-') $numOnly = '-' . $numOnly;
            return spellNumberUzbek((int)$numOnly);
        }, $t);

        // 4. Vaqtlar (13:00 -> o'n uch nol nol)
        $t = preg_replace_callback('/(\d{1,2}):(\d{2})/', function($m) {
            $h = spellNumberUzbek((int)$m[1]);
            $mStr = ($m[2] === '00') ? 'nol nol' : spellNumberUzbek((int)$m[2]);
            return $h . " " . $mStr;
        }, $t);

        return $t;
    }

    function stripMarkdown($t) {
        $t = str_replace('*', ' ', $t); // Eng birinchi yulduzchalarni to'liq olib tashlash
        $t = str_replace(['_', '#', '/', '\\'], ' ', $t); 
        $t = preg_replace('/\s+/', ' ', $t); // Ortiqcha bo'shliqlarni yo'qotish
        return trim($t);
    }

    $text = stripMarkdown($text);
    error_log("🔍 CLEANED TTS TEXT: " . $text); // Debug uchun log
    $text = spellOutNumbers($text);

    $voice = 'xiaoyan'; // iFlytek default ovozi

    function callIFlytekTTS($text, $voice) {
        try {
            $wsUrl = iFlytekHelper::getTtsUrl();
            // iFlytek TTS uchun ham WebSocket kerak. 
            // Hozircha stt.php kabi xatoni qaytarib tursin.
            return ['error' => 'WebSocket client not implemented in PHP yet', 'engine' => 'iflytek'];
        } catch (Exception $e) {
            return ['error' => $e->getMessage(), 'engine' => 'iflytek'];
        }
    }

    function callUzbekVoiceTTS($text, $voice, $apiKey) {
        $url = "https://uzbekvoice.ai/api/v1/tts";
        $curl = curl_init();
        $postData = [
            'text' => $text,
            'model' => strtolower($voice),
            'blocking' => 'true'
        ];
        
        curl_setopt_array($curl, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($postData),
            CURLOPT_HTTPHEADER => [
                "Authorization: " . $apiKey,
                "Content-Type: application/json"
            ],
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CONNECTTIMEOUT => 5
        ]);

        $response = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $err = curl_error($curl);
        curl_close($curl);

        $jsonCheck = json_decode($response, true);
        if ($jsonCheck) {
            $audioUrl = $jsonCheck['result']['url']
                     ?? $jsonCheck['result']['audio_url']
                     ?? $jsonCheck['url'] 
                     ?? $jsonCheck['audio_url'] 
                     ?? null;
                     
            if ($audioUrl) {
                $ch2 = curl_init();
                curl_setopt_array($ch2, [
                    CURLOPT_URL => $audioUrl,
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_HTTPHEADER => [],
                    CURLOPT_SSL_VERIFYPEER => false,
                    CURLOPT_SSL_VERIFYHOST => false,
                    CURLOPT_FOLLOWLOCATION => true,
                    CURLOPT_TIMEOUT => 30
                ]);
                $audioData = curl_exec($ch2);
                $httpCode2 = curl_getinfo($ch2, CURLINFO_HTTP_CODE);
                curl_close($ch2);
                
                if ($audioData !== false && $httpCode2 >= 200 && $httpCode2 < 300) {
                    $response = $audioData;
                    $httpCode = 200;
                } else {
                    $err = "Audio download failed: HTTP $httpCode2";
                    $httpCode = 500;
                }
            } else {
                $err = "API JSON yubordi, lekin audio url topilmadi.";
                $httpCode = 500;
            }
        }

        return ['response' => $response, 'httpCode' => $httpCode, 'error' => $err];
    }

    function splitText($text, $maxLength = 200) {
        if (mb_strlen($text) <= $maxLength) return [$text];
        
        $chunks = [];
        $words = explode(' ', $text);
        $currentChunk = "";
        
        foreach ($words as $word) {
            if (mb_strlen($currentChunk . " " . $word) <= $maxLength) {
                $currentChunk .= (empty($currentChunk) ? "" : " ") . $word;
            } else {
                if (!empty($currentChunk)) $chunks[] = $currentChunk;
                $currentChunk = $word;
            }
        }
        if (!empty($currentChunk)) $chunks[] = $currentChunk;
        
        return $chunks;
    }

    $textChunks = splitText($text, 1000);
    $finalAudio = "";
    $audioChunks = [];
    
    foreach ($textChunks as $chunk) {
        error_log("🎯 TTS ENGINE SELECTION: Trying iFlytek");
        $res = callIFlytekTTS($chunk, $voice);

        if (isset($res['error']) && ($res['engine'] ?? '') === 'iflytek') {
            error_log("⚠️ iFlytek TTS failed - Falling back to UZBEKVOICE");
            $res = callUzbekVoiceTTS($chunk, 'lola', UZBEKVOICE_API_KEY);
        }
        
        if ($res['httpCode'] == 200) {
            if ($returnChunks) {
                $audioChunks[] = base64_encode($res['response']);
            } else {
                $finalAudio .= $res['response'];
            }
        } else {
            header('Content-Type: application/json');
            ob_clean();
            http_response_code($res['httpCode'] ?: 500);
            echo json_encode([
                'error' => true,
                'message' => 'UzbekVoice TTS xatosi (chunking)',
                'details' => $res['response']
            ]);
            exit;
        }
    }

    ob_clean();
    if ($returnChunks) {
        header('Content-Type: application/json');
        echo json_encode(['chunks' => $audioChunks, 'engine' => ($res['engine'] ?? 'uzbekvoice')]);
    } else {
        header('Content-Type: audio/mpeg');
        header('X-TTS-Engine: ' . ($res['engine'] ?? 'uzbekvoice'));
        echo $finalAudio;
    }

} catch (Throwable $e) {
    header('Content-Type: application/json');
    if (ob_get_level() > 0) ob_clean();
    echo json_encode(['error' => 'TTS Ichki xatosi: ' . $e->getMessage()]);
}

<?php
require_once '../config.php';

ob_start();

try {
    $input = json_decode(file_get_contents('php://input'), true);
    $text = $input['text'] ?? '';
    $lang = $input['language'] ?? 'uz';
    
    if (empty($text)) {
        header('Content-Type: application/json');
        ob_clean();
        http_response_code(400);
        echo json_encode(['error' => 'Matn yuborilmadi']);
        exit;
    }

    // Raqamlarni va matnni tozalash funksiyalari (qoldirildi)
    function spellNumberUzbek($number) {
        $units = ['', 'ming', 'million', 'milliard'];
        $ones = ['', 'bir', 'ikki', 'uch', 'to\'rt', 'besh', 'olti', 'yetti', 'sakkiz', 'to\'qqiz'];
        $tens = ['', 'o\'n', 'yigirma', 'o\'ttiz', 'qirq', 'ellik', 'oltmish', 'yetmish', 'sakson', 'to\'qson'];
        if ($number == 0) return 'nol';
        $str = ''; $unitIdx = 0; $number = abs((int)$number);
        while ($number > 0) {
            $chunk = $number % 1000;
            $number = floor($number / 1000);
            if ($chunk > 0) {
                $chunkStr = '';
                $h = floor($chunk / 100); $t = floor(($chunk % 100) / 10); $o = $chunk % 10;
                if ($h > 0) $chunkStr .= $ones[$h] . ' yuz ';
                if ($t > 0) $chunkStr .= $tens[$t] . ' ';
                if ($o > 0) $chunkStr .= $ones[$o] . ' ';
                $str = trim($chunkStr) . ' ' . $units[$unitIdx] . ' ' . $str;
            }
            $unitIdx++;
        }
        return trim($str);
    }

    function stripMarkdown($t) {
        return trim(str_replace(['*', '_', '#'], '', $t));
    }

    $text = stripMarkdown($text);
    
    function callGeminiTTS($text, $voiceModel, $apiKey) {
        $url = "https://generativelanguage.googleapis.com/v1beta/models/" . $voiceModel . ":generateContent?key=" . $apiKey;
        $payload = [
            "contents" => [["parts" => [["text" => "Generate speech for: " . $text]]]],
            "generationConfig" => [
                "responseModalities" => ["audio"],
                "speechConfig" => [
                    "voiceConfig" => ["prebuiltVoiceConfig" => ["voiceName" => "Puck"]] // Eslatma: Puck, Charon va h.k.
                ]
            ]
        ];

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => false
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $result = json_decode($response, true);
        if (isset($result['candidates'][0]['content']['parts'][0]['inlineData']['data'])) {
            $audioData = base64_decode($result['candidates'][0]['content']['parts'][0]['inlineData']['data']);
            return ['response' => $audioData, 'httpCode' => 200];
        }
        
        return ['error' => 'Gemini TTS audio qaytarmadi', 'httpCode' => $httpCode, 'details' => $response];
    }

    function callUzbekVoiceTTS($text, $voice, $apiKey) {
        $url = "https://uzbekvoice.ai/api/v1/tts";
        $postData = [
            'text' => $text,
            'model' => strtolower($voice),
            'blocking' => 'true'
        ];
        
        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($postData),
            CURLOPT_HTTPHEADER => ["Authorization: " . $apiKey, "Content-Type: application/json"],
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_TIMEOUT => 30
        ]);

        $response = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);

        $json = json_decode($response, true);
        $audioUrl = $json['result']['url'] ?? $json['url'] ?? null;
        
        if ($audioUrl) {
            return ['response' => file_get_contents($audioUrl), 'httpCode' => 200];
        }
        return ['error' => 'Audio url topilmadi', 'httpCode' => $httpCode];
    }

    $voiceInput = $input['voice'] ?? 'lola';
    
    // Engine tanlash: Agar ovoz gemini bo'lsa yoki shunday sozlangan bo'lsa
    if (strpos(strtolower($voiceInput), 'gemini') !== false) {
        $res = callGeminiTTS($text, GEMINI_TTS_MODEL, GEMINI_API_KEY);
    } else {
        $res = callUzbekVoiceTTS($text, $voiceInput, UZBEKVOICE_API_KEY);
    }

    if (isset($res['response'])) {
        ob_clean();
        header('Content-Type: audio/mpeg');
        echo $res['response'];
    } else {
        header('Content-Type: application/json');
        ob_clean();
        echo json_encode(['error' => 'UzbekVoice TTS hatosi', 'details' => $res]);
    }

} catch (Throwable $e) {
    header('Content-Type: application/json');
    if (ob_get_level() > 0) ob_clean();
    echo json_encode(['error' => $e->getMessage()]);
}

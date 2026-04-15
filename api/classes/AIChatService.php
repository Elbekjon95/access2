<?php

class AIChatService {
    /**
     * AI modeliga so'rov yuboradi (Faqat Gemini).
     */
    public static function call($systemPrompt, $userMessage, $engine = 'gemini') {
        try {
            // Foydalanuvchi talabi bo'yicha faqat gemini ishlaydi
            return self::callGemini($systemPrompt, $userMessage);
        } catch (Exception $e) {
            error_log("AIChatService Error: " . $e->getMessage());
            return "Kechirasiz, AI xizmatida xatolik yuz berdi.";
        }
    }

    private static function callGemini($systemPrompt, $userMessage) {
        // .env da ko'rsatilgan model nomidan foydalanamiz
        $model = defined('GEMINI_MODEL') ? GEMINI_MODEL : 'gemini-3.1-pro-preview';
        $url = "https://generativelanguage.googleapis.com/v1beta/models/" . $model . ":generateContent?key=" . GEMINI_API_KEY;
        
        $payload = [
            "systemInstruction" => [
                "parts" => [["text" => $systemPrompt]]
            ],
            "contents" => [[
                "role" => "user",
                "parts" => [["text" => $userMessage]]
            ]],
            "generationConfig" => ["temperature" => 0.1, "maxOutputTokens" => 2048],
            "safetySettings" => [
                ["category" => "HARM_CATEGORY_HARASSMENT", "threshold" => "BLOCK_NONE"],
                ["category" => "HARM_CATEGORY_HATE_SPEECH", "threshold" => "BLOCK_NONE"],
                ["category" => "HARM_CATEGORY_SEXUALLY_EXPLICIT", "threshold" => "BLOCK_NONE"],
                ["category" => "HARM_CATEGORY_DANGEROUS_CONTENT", "threshold" => "BLOCK_NONE"]
            ]
        ];

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_TIMEOUT => 25,
            CURLOPT_SSL_VERIFYPEER => false
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        $result = json_decode($response, true);
        
        if ($httpCode !== 200) {
            $errorMsg = $result['error']['message'] ?? "No message";
            error_log("❌ Gemini API Error ($httpCode): " . $errorMsg);
            return "AI xizmatida xatolik ($httpCode): " . $errorMsg;
        }

        if (isset($result['candidates'][0]['content']['parts'][0]['text'])) {
            return $result['candidates'][0]['content']['parts'][0]['text'];
        }

        return "AI javob formati xato.";
    }
}

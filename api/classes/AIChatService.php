<?php

class AIChatService {
    /**
     * AI modeliga so'rov yuboradi.
     */
    public static function call($systemPrompt, $userMessage, $engine = 'openrouter') {
        try {
            switch ($engine) {
                case 'openrouter':
                    return self::callOpenRouter($systemPrompt, $userMessage);
                case 'gemini':
                    $resp = self::callGemini($systemPrompt, $userMessage);
                    if (is_array($resp) && isset($resp['error'])) {
                        return self::callGroq($systemPrompt, $userMessage); // Fallback
                    }
                    return $resp;
                case 'ollama':
                    $resp = self::callOllama($systemPrompt, $userMessage);
                    if (is_array($resp) && isset($resp['error'])) {
                        return self::callGroq($systemPrompt, $userMessage); // Fallback
                    }
                    return $resp;
                case 'groq':
                    return self::callGroq($systemPrompt, $userMessage);
                default:
                    return self::callGroq($systemPrompt, $userMessage);
            }
        } catch (Exception $e) {
            error_log("AIChatService Error: " . $e->getMessage());
            return "Kechirasiz, AI xizmatida xatolik yuz berdi.";
        }
    }

    private static function callOpenRouter($systemPrompt, $userMessage) {
        $postData = [
            'model' => OPENROUTER_MODEL,
            'messages' => [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => $userMessage]
            ],
            'temperature' => 0.1,
            'max_tokens' => 1024
        ];

        $ch = curl_init("https://openrouter.ai/api/v1/chat/completions");
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($postData),
            CURLOPT_HTTPHEADER => [
                "Authorization: Bearer " . OPENROUTER_API_KEY,
                "Content-Type: application/json",
                "HTTP-Referer: http://localhost",
                "X-Title: ACSESS Airport Kiosk"
            ],
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_TIMEOUT => 20
        ]);

        $response = curl_exec($ch);
        curl_close($ch);
        $result = json_decode($response, true);
        return $result['choices'][0]['message']['content'] ?? 'AI javob bera olmadi (OpenRouter)';
    }

    private static function callGemini($systemPrompt, $userMessage) {
        $url = "https://generativelanguage.googleapis.com/v1/models/" . GEMINI_MODEL . ":generateContent?key=" . GEMINI_API_KEY;
        $payload = [
            "contents" => [[
                "role" => "user",
                "parts" => [["text" => "SYSTEM PROMPT: " . $systemPrompt . "\n\nUSER MESSAGE: " . $userMessage]]
            ]],
            "generationConfig" => ["temperature" => 0.1, "maxOutputTokens" => 2048]
        ];

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_TIMEOUT => 20,
            CURLOPT_SSL_VERIFYPEER => false
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        $result = json_decode($response, true);
        
        if ($httpCode !== 200) {
            error_log("❌ Gemini API HTTP Error ($httpCode): " . ($result['error']['message'] ?? $response));
            return ['error' => 'Gemini xatosi: ' . ($result['error']['message'] ?? "HTTP $httpCode")];
        }

        if (isset($result['candidates'][0]['content']['parts'][0]['text'])) {
            return $result['candidates'][0]['content']['parts'][0]['text'];
        }

        error_log("❌ Gemini API Invalid Structure: " . $response);
        return ['error' => 'Gemini javob formati xato'];
    }

    private static function callGroq($systemPrompt, $userMessage) {
        $postData = [
            'model' => 'llama-3.1-8b-instant',
            'messages' => [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => $userMessage]
            ],
            'temperature' => 0.1,
            'max_tokens' => 1024
        ];

        $ch = curl_init("https://api.groq.com/openai/v1/chat/completions");
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($postData),
            CURLOPT_HTTPHEADER => [
                "Authorization: Bearer " . GROQ_API_KEY,
                "Content-Type: application/json"
            ],
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_TIMEOUT => 15,
        ]);

        $response = curl_exec($ch);
        curl_close($ch);
        $result = json_decode($response, true);
        return $result['choices'][0]['message']['content'] ?? 'AI javob bera olmadi (Groq)';
    }

    private static function callOllama($systemPrompt, $userMessage) {
        $payload = [
            'model' => OLLAMA_MODEL,
            'messages' => [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => $userMessage]
            ],
            'stream' => false,
            'options' => ['temperature' => 0.1, 'num_predict' => 768]
        ];
        
        $ch = curl_init(env('OLLAMA_CHAT_URL', 'http://localhost:11434/api/chat'));
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_TIMEOUT => 12,
            CURLOPT_CONNECTTIMEOUT => 3
        ]);
        $response = curl_exec($ch);
        curl_close($ch);
        $result = json_decode($response, true);
        return $result['message']['content'] ?? ['error' => 'Ollama xatosi'];
    }
}

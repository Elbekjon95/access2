<?php
/**
 * ACCSESS - Gemini REST TTS Client (High Fidelity 24kHz)
 */
class GeminiRestTTS {
    private $apiKey;
    private $model = 'models/gemini-2.5-flash-preview-tts'; 
    private $systemInstruction = "Siz professional airport suhandonisiz. Sizga berilgan matnni xech qanday o'zgarishsiz, savollarga javob bermasdan so'zma-so'z o'qib berishingiz shart. Matndan tashqari xech qanday gap qo'shmang.";

    public function __construct($apiKey, $model = null) {
        $this->apiKey = $apiKey;
        if ($model) $this->model = $model;
    }

    public function synthesize($text, $voiceName) {
        $url = "https://generativelanguage.googleapis.com/v1beta/{$this->model}:generateContent?key=" . $this->apiKey;

        $data = [
            'contents' => [['parts' => [['text' => $text]]]],
            'generationConfig' => [
                'responseModalities' => ['AUDIO'],
                'speechConfig' => [
                    'voiceConfig' => [
                        'prebuiltVoiceConfig' => [
                            'voiceName' => $voiceName
                        ]
                    ]
                ]
            ]
        ];

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_TIMEOUT => 25
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            $err = json_decode($response, true);
            file_put_contents(__DIR__ . '/../gemini_rest_tts_error.log', "Code: $httpCode | $response\n", FILE_APPEND);
            error_log("❌ Gemini REST Error ($httpCode): " . $response);
            throw new Exception("Gemini REST Error ($httpCode): " . ($err['error']['message'] ?? 'Unknown'));
        }

        $result = json_decode($response, true);
        if (isset($result['candidates'][0]['content']['parts'])) {
            foreach ($result['candidates'][0]['content']['parts'] as $part) {
                if (isset($part['inlineData']['data'])) {
                    return base64_decode($part['inlineData']['data']);
                }
            }
        }
        
        error_log("⚠️ Gemini Response No Audio: " . $response);
        return null;
    }
}

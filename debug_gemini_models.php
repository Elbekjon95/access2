<?php
require_once 'config.php';

header('Content-Type: text/plain');

$testMessage = "Salom, qandaysan?";
$systemPrompt = "Sen aeroport yordamchisisan.";

$models = ['gemini-1.5-flash', 'gemini-1.5-pro', 'gemini-2.0-flash-exp'];

foreach ($models as $model) {
    echo "=== Testing Model: $model ===\n";
    $url = "https://generativelanguage.googleapis.com/v1beta/models/" . $model . ":generateContent?key=" . GEMINI_API_KEY;
    
    $payload = [
        "contents" => [[
            "parts" => [["text" => $testMessage]]
        ]]
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
    
    echo "HTTP Status: $httpCode\n";
    if ($httpCode === 200) {
        $result = json_decode($response, true);
        echo "Response: " . ($result['candidates'][0]['content']['parts'][0]['text'] ?? 'Format Error') . "\n";
    } else {
        echo "Error: $response\n";
    }
    echo "---------------------------\n\n";
}

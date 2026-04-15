<?php
require_once 'config.php';
require_once 'api/classes/AIChatService.php';

header('Content-Type: text/plain');

$testMessage = "Salom, qandaysan?";
$systemPrompt = "Sen aeroport yordamchisisan.";

$engines = ['gemini', 'groq', 'openrouter', 'ollama'];

foreach ($engines as $engine) {
    echo "=== Testing Engine: $engine ===\n";
    try {
        $start = microtime(true);
        $result = AIChatService::call($systemPrompt, $testMessage, $engine);
        $end = microtime(true);
        
        if (is_array($result) && isset($result['error'])) {
            echo "Result: ERROR\n";
            echo "Message: " . $result['error'] . "\n";
        } else {
            echo "Result: SUCCESS\n";
            echo "Response: " . substr($result, 0, 100) . "...\n";
            echo "Time: " . round($end - $start, 2) . "s\n";
        }
    } catch (Exception $e) {
        echo "Exception: " . $e->getMessage() . "\n";
    }
    echo "---------------------------\n\n";
}

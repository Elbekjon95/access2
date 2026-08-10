<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

try {
    require_once __DIR__ . '/../config.php';
    require_once __DIR__ . '/uzbek_helper.php';
    require_once __DIR__ . '/flights.php';
    require_once __DIR__ . '/chat_helpers.php';
    require_once __DIR__ . '/classes/IntentDetector.php';
    require_once __DIR__ . '/classes/FlightProcessor.php';
    require_once __DIR__ . '/classes/AirportNavigator.php';
    require_once __DIR__ . '/classes/AIChatService.php';
    require_once __DIR__ . '/classes/ChatHandler.php';

    $start = microtime(true);

    $userMessage = "Ertangi Toshkent Istanbul reyslari qachon uchadi?";
    $forcedLang = 'uz';

    echo "Testing query: '$userMessage'\n\n";

    $isDateQuery = preg_match('/\b(ertaga|indinga|sana|[0-9]{1,2})\b/ui', $userMessage);
    $allFlights = getProcessedFlights($isDateQuery); 
    echo "1. Flights loaded. Time: " . round((microtime(true) - $start) * 1000) . " ms\n";

    $db = getDbConnection();
    $mapPoints = $db->find('map_points', [], ['projection' => ['name' => 1, 'type' => 1]]);
    echo "2. Map loaded. Time: " . round((microtime(true) - $start) * 1000) . " ms\n";

    $handler = new ChatHandler($db, $allFlights, $mapPoints);
    $response = $handler->handle($userMessage, $forcedLang);
    echo "3. Chat handled. Total Time: " . round((microtime(true) - $start) * 1000) . " ms\n";

    echo "\nRESPONSE:\n" . json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
} catch (Throwable $e) {
    echo "ERROR: " . $e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine() . "\n";
}

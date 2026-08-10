<?php
/**
 * ACSESS - Chat API
 * Refactored to use modular classes and helpers.
 */

require_once __DIR__ . '/../config.php';
secureSessionStart();

if (!isset($_SESSION['user_id']) && !isset($_SESSION['admin_id'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Ruxsat yo\'q']);
    exit;
}
require_once 'uzbek_helper.php'; 
require_once 'flights.php';
require_once 'chat_helpers.php';

// Klasslarni yuklash
require_once 'classes/IntentDetector.php';
require_once 'classes/FlightProcessor.php';
require_once 'classes/AirportNavigator.php';
require_once 'classes/AIChatService.php';
require_once 'classes/ChatHandler.php';

header('Content-Type: application/json');
ob_start();

try {
    $input = json_decode(file_get_contents('php://input'), true);
    $userMessage = $input['message'] ?? '';
    $forcedLang = $input['language'] ?? '';

    // Til kodi tekshiruvi
    if (!in_array($forcedLang, ['uz', 'ru', 'en', 'es', 'zh', 'hi', 'ar', 'bn', 'pt', 'ja', 'de', 'fr', 'it', 'ko', 'tr', 'ur', 'tg', 'ky', 'kk', 'tk'], true)) {
        $forcedLang = '';
    }

    if (empty($userMessage)) {
        if (ob_get_level() > 0) ob_clean();
        echo json_encode(['error' => 'Xabar yuborilmadi']);
        exit;
    }

    // Reyslarni olish (sana bo'yicha so'rov bo'lsa)
    $isDateQuery = preg_match('/\b(ertaga|indinga|sana|[0-9]{1,2})\b/ui', $userMessage);
    $allFlights = getProcessedFlights($isDateQuery); 

    // Lokatsiyalarni olish
    $db = getDbConnection();
    $mapPoints = $db->find('map_points', [], ['projection' => ['name' => 1, 'type' => 1]]);

    // ChatHandler orqali ishlov berish
    $handler = new ChatHandler($db, $allFlights, $mapPoints);
    $response = $handler->handle($userMessage, $forcedLang);

    if (ob_get_level() > 0) ob_clean();
    echo json_encode($response);

} catch (Throwable $e) {
    if (ob_get_level() > 0) ob_clean();
    error_log("FATAL ERROR in chat.php: " . $e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine());
    echo json_encode(['error' => 'Ichki tizim xatosi yuz berdi. Iltimos birozdan so\'ng qaytadan urinib ko\'ring.']);
}

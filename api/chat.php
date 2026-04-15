<?php
/**
 * ACCSESS - Chat API
 * Refactored to use modular classes and helpers.
 */

require_once '../config.php';
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
    $pdo = getDbConnection();
    $stmtMap = $pdo->query("SELECT name, type FROM map_points");
    $mapPoints = $stmtMap->fetchAll(PDO::FETCH_ASSOC);

    // ChatHandler orqali ishlov berish
    $handler = new ChatHandler($pdo, $allFlights, $mapPoints);
    $response = $handler->handle($userMessage, $forcedLang);

    if (ob_get_level() > 0) ob_clean();
    echo json_encode($response);

} catch (Throwable $e) {
    if (ob_get_level() > 0) ob_clean();
    error_log("FATAL ERROR in chat.php: " . $e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine());
    echo json_encode(['error' => 'Ichki tizim xatosi yuz berdi. Iltimos birozdan so\'ng qaytadan urinib ko\'ring.']);
}

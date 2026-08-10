<?php
require_once __DIR__ . '/../config.php';
require_once 'classes/WeatherProcessor.php';

header('Content-Type: application/json');

$lang = $_GET['lang'] ?? 'uz';

if (isset($_GET['cities'])) {
    $cities = explode(',', $_GET['cities']);
    $results = [];
    foreach ($cities as $city) {
        $w = WeatherProcessor::getWeather(trim($city), $lang);
        if (!isset($w['error'])) {
            $w['formatted'] = WeatherProcessor::formatWeather($w, $lang);
            $results[] = $w;
        }
    }
    echo json_encode(['success' => true, 'results' => $results]);
    exit;
}

$city = $_GET['city'] ?? 'Tashkent';
$w = WeatherProcessor::getWeather($city, $lang);

if (isset($w['error'])) {
    echo json_encode(['success' => false, 'error' => $w['error']]);
} else {
    $w['formatted'] = WeatherProcessor::formatWeather($w, $lang);
    echo json_encode(['success' => true, 'data' => $w]);
}

<?php
require_once 'flights.php';

header('Content-Type: application/json');

try {
    $flights = getProcessedFlights();
    $cities = [];

    foreach ($flights as $f) {
        if (($f['type'] ?? '') === 'departure') {
            $to = $f['to'] ?? '';
            // "City (CODE)" yoki "City" formatidan shahar nomini ajratish
            if (preg_match('/^([^(\n\r]+)/u', $to, $m)) {
                $cityName = trim($m[1]);
                if ($cityName !== 'Tashkent' && $cityName !== 'N/A' && !empty($cityName)) {
                    $cities[] = $cityName;
                }
            }
        }
    }

    $uniqueCities = array_unique($cities);
    sort($uniqueCities);

    echo json_encode([
        'success' => true,
        'cities' => array_values($uniqueCities)
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

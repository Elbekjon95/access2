<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/uzairports_parser.php';


header('Content-Type: application/json');
ob_start();

$lastFlightError = null;

function fetchFlights() {
    global $lastFlightError;
    $cacheFile = __DIR__ . '/../data/flights_cache.json';
    $cacheTtl = 300; // 5 daqiqa yashash vaqti (oldingi 30 soniya edi)

    if (file_exists($cacheFile)) {
        $cached = json_decode(@file_get_contents($cacheFile), true);
        if ($cached && isset($cached['ts'], $cached['data']) && (time() - $cached['ts'] <= $cacheTtl)) {
            return $cached['data'];
        }
    }

    $depUrl = FLIGHT_API_URL;
    $arrUrl = str_replace('flight_type=DEPARTURE', 'flight_type=ARRIVAL', FLIGHT_API_URL);

    $mh = curl_multi_init();
    $ch1 = curl_init();
    $ch2 = curl_init();

    curl_setopt_array($ch1, [
        CURLOPT_URL => $depUrl,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
    ]);

    curl_setopt_array($ch2, [
        CURLOPT_URL => $arrUrl,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
    ]);

    curl_multi_add_handle($mh, $ch1);
    curl_multi_add_handle($mh, $ch2);

    $running = null;
    do {
        curl_multi_exec($mh, $running);
    } while ($running);

    $depResponse = curl_multi_getcontent($ch1);
    $arrResponse = curl_multi_getcontent($ch2);

    $err1 = curl_error($ch1);
    $err2 = curl_error($ch2);

    curl_multi_remove_handle($mh, $ch1);
    curl_multi_remove_handle($mh, $ch2);
    curl_multi_close($mh);

    if ($err1 && $err2) {
        $lastFlightError = $err1 . " | " . $err2;
        return ["error" => $lastFlightError];
    }

    $depData = json_decode($depResponse, true);
    $arrData = json_decode($arrResponse, true);

    $allFlights = [];
    if (isset($depData['flights']) && is_array($depData['flights'])) {
        $allFlights = array_merge($allFlights, $depData['flights']);
    }
    if (isset($arrData['flights']) && is_array($arrData['flights'])) {
        $allFlights = array_merge($allFlights, $arrData['flights']);
    }

    $data = ['flights' => $allFlights];

    if (!empty($allFlights)) {
        @file_put_contents($cacheFile, json_encode(['ts' => time(), 'data' => $data]));
    }
    return $data;
}

function getProcessedFlights($showAll = false) {
    $flights = fetchFlights();
    $apiFlights = [];
    if (isset($flights['flights']) && is_array($flights['flights'])) {
        foreach ($flights['flights'] as $flight) {
            $movement = strtoupper($flight['movement'] ?? 'DEPARTURE');
            $isArrival = ($movement === 'ARRIVAL');
            
            $apiFlights[] = [
                'flight_no' => ($flight['aircompany'] ?? '') . ($flight['flightnumber'] ?? ''),
                'from' => $isArrival ? ($flight['city_code'] ?? $flight['airport'] ?? 'N/A') : 'Tashkent (TAS)',
                'to' => $isArrival ? 'Tashkent (TAS)' : ($flight['city_code'] ?? $flight['airport'] ?? 'N/A'),
                'time' => substr($flight['sched'] ?? '', 11, 5),
                'gate' => $flight['stand'] ?? 'N/A',
                'fids' => $flight['terminal'] ?? 'N/A',
                'status' => $flight['flight_status'] ?? 'N/A',
                'est_time' => ($flight['estimated'] ?? '') ? substr($flight['estimated'], 11, 5) : 'On Time',
                'checkin_counters' => 'N/A',
                'source' => 'api',
                'type' => strtolower($movement)
            ];
        }
    }

    $uzairportsFlights = getCombinedFlights();
    $enrichedFlights = [];
    foreach ($uzairportsFlights as $uzFlight) {
        $enrichedFlights[] = [
            'flight_no' => $uzFlight['flight_no'] ?? 'N/A',
            'from' => $uzFlight['from'] ?? 'N/A',
            'to' => $uzFlight['to'] ?? 'N/A',
            'time' => $uzFlight['time'] ?? 'N/A',
            'gate' => 'N/A', // Uzairports da gate yo'q
            'fids' => 'N/A',
            'status' => $uzFlight['status'] ?? 'N/A',
            'est_time' => 'N/A',
            'checkin_counters' => $uzFlight['checkin_counters'] ?? 'N/A',
            'source' => 'uzairports',
            'type' => $uzFlight['type'] ?? 'departure'
        ];
    }

    $merged = [];
    $processedFlightNos = [];

    foreach ($enrichedFlights as $uzFlight) {
        $flightNo = strtoupper(str_replace(' ', '', $uzFlight['flight_no']));
        if ($flightNo === 'N/A' || $flightNo === '') continue;

        foreach ($apiFlights as $apiFlight) {
            $apiFlightNo = strtoupper(str_replace(' ', '', $apiFlight['flight_no']));
            if ($apiFlightNo === $flightNo) {
                $uzFlight['gate'] = $apiFlight['gate'] !== 'N/A' ? $apiFlight['gate'] : $uzFlight['gate'];
                $uzFlight['fids'] = $apiFlight['fids'] !== 'N/A' ? $apiFlight['fids'] : $uzFlight['fids'];
                break;
            }
        }
        $merged[] = $uzFlight;
        $processedFlightNos[$flightNo] = true;
    }

    foreach ($apiFlights as $apiFlight) {
        $apiFlightNo = strtoupper(str_replace(' ', '', $apiFlight['flight_no']));
        if (!isset($processedFlightNos[$apiFlightNo])) {
            $merged[] = $apiFlight;
        }
    }

    if (!$showAll) {
        $now = time();
        $buffer = 20 * 60; // 20 minutlik bufer (yaqinda o'tib ketgan reyslarni ko'rsatish uchun)
        
        $merged = array_filter($merged, function($f) use ($now, $buffer) {
            $fTime = $f['time'] ?? '00:00';
            if ($fTime === 'N/A') return true;

            $fTimestamp = strtotime(date('Y-m-d ') . $fTime);
            
            if (($now - $fTimestamp) > 12 * 3600) {
                $fTimestamp += 24 * 3600; // Ertangi kunga o'tkazish
            }
            
            return ($fTimestamp + $buffer) >= $now;
        });
        $merged = array_values($merged);
    }

    return $merged;
}

try {
    if (basename($_SERVER['PHP_SELF']) == 'flights.php') {
        $data = getProcessedFlights();
        ob_clean();
        if ($lastFlightError) {
            http_response_code(502);
            echo json_encode(['error' => $lastFlightError]);
        } else {
            echo json_encode($data);
        }
    }
} catch (Throwable $e) {
    if (ob_get_level() > 0) ob_clean();
    echo json_encode(['error' => $e->getMessage()]);
}

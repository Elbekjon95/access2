<?php
require_once __DIR__ . '/../config.php';

/**
 * Uzairports FIDS saytidan reyslarni parsing qilish
 */
function fetchUzairportsFlights($flightType = 'DEPARTURE') {
    $url = "https://bot.uzairports.com/fids/schedule?airport=TAS2&flight_type=" . $flightType;
    
    $curl = curl_init();
    curl_setopt_array($curl, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
    ]);

    $html = curl_exec($curl);
    $error = curl_error($curl);
    curl_close($curl);

    if ($error) {
        error_log("Uzairports fetch error: $error");
        return [];
    }

    // HTML parsing
    $html = mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8');
    libxml_use_internal_errors(true);
    $dom = new DOMDocument();
    $dom->loadHTML($html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
    libxml_clear_errors();

    $xpath = new DOMXPath($dom);
    $rows = $xpath->query('//table//tr');

    $flights = [];
    $isHeader = true;

    foreach ($rows as $row) {
        if ($isHeader) {
            $isHeader = false;
            continue; // Header qatorni o'tkazib yuborish
        }

        $cells = $xpath->query('.//td', $row);
        if ($cells->length < 5) continue; // Kam ma'lumotli qatorlarni o'tkazib yuborish

        $flight = [];
        foreach ($cells as $i => $cell) {
            $text = trim(preg_replace('/\s+/', ' ', $cell->nodeValue));
            switch ($i) {
                case 0: $flight['time'] = $text; break;
                case 1: $flight['airline'] = $text; break;
                case 2: $flight['flight_no'] = $text; break;
                case 3: $flight['destination'] = $text; break;
                case 4: $flight['checkin_counters'] = $text; break; // RO'YXATDAN O'TISH STOYKALAR
                case 5: $flight['status'] = $text; break;
            }
        }

        if (!empty($flight['flight_no']) && !empty($flight['time'])) {
            $flights[] = $flight;
        }
    }

    return $flights;
}

/**
 * Departure va Arrival reyslarni birlashtirish
 */
function getCombinedFlights() {
    $cacheFile = __DIR__ . '/../data/uzairports_cache.json';
    $cacheTtl = 300; // 5 daqiqa yashash vaqti (oldingi 60 soniya edi)
    
    if (file_exists($cacheFile)) {
        $cached = json_decode(@file_get_contents($cacheFile), true);
        if ($cached && isset($cached['ts'], $cached['data']) && (time() - $cached['ts'] <= $cacheTtl)) {
            return $cached['data'];
        }
    }

    $departures = fetchUzairportsFlights('DEPARTURE');
    $arrivals = fetchUzairportsFlights('ARRIVAL');

    // Har birini type bilan belgilash
    foreach ($departures as &$f) {
        $f['type'] = 'departure';
        $f['from'] = 'Tashkent (TAS)';
        $f['to'] = $f['destination'] ?? 'N/A';
    }

    foreach ($arrivals as &$f) {
        $f['type'] = 'arrival';
        $f['to'] = 'Tashkent (TAS)';
        $f['from'] = $f['destination'] ?? 'N/A';
    }

    $result = array_merge($departures, $arrivals);
    if (!empty($result)) {
        @file_put_contents($cacheFile, json_encode(['ts' => time(), 'data' => $result]));
    }
    
    return $result;
}

// Test
if (basename($_SERVER['PHP_SELF']) == 'uzairports_parser.php') {
    header('Content-Type: application/json');
    $flights = getCombinedFlights();
    echo json_encode([
        'total' => count($flights),
        'flights' => array_slice($flights, 0, 5)
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}

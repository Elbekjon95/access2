<?php

require_once __DIR__ . '/../chat_helpers.php';


class ChatHandler {
    private $pdo;
    private $flights;
    private $mapPoints;

    public function __construct($pdo, $flights = [], $mapPoints = []) {
        $this->pdo = $pdo;
        $this->flights = $flights;
        $this->mapPoints = $mapPoints;
    }

    public function handle($userMessage, $forcedLang = null) {
        $detectedLang = $forcedLang ?: detectLanguage($userMessage);
        
        // Agar o'zbek tili bo'lsa va Fast Path (Tezkor javob) mavjud bo'lsa
        if ($detectedLang === 'uz') {
            $intent = IntentDetector::detect($userMessage, $this->flights);
            $fastResponse = $this->handleFastPath($intent, $userMessage, $detectedLang);
            if ($fastResponse) {
                return $this->finalizeResponse($fastResponse, $userMessage, 'uz', 'fast-path');
            }
        }

        // Aks holda AI orqali javob berish
        return $this->handleAIPath($userMessage, $detectedLang);
    }

    private function handleFastPath($intent, $message, $lang) {
        switch ($intent) {
            case 'greeting':
                return ['reply' => "Assalomu alaykum! Toshkent xalqaro aeroportiga xush kelibsiz! Sizga qanday yordam bera olaman?"];
            case 'navigation':
                return AirportNavigator::handle($message, $this->mapPoints, $lang);
            case 'flight_number':
            case 'flight_city':
            case 'flight_list':
                // Bu yerda FlightProcessor orqali murakkabroq qidiruv logikasini qo'shish mumkin
                return null;
            default:
                return null;
        }
    }

    private function handleAIPath($userMessage, $lang) {
        $engine = USE_AI_ENGINE;
        $systemPrompt = $this->getSystemPrompt($lang, $userMessage);
        $answer = AIChatService::call($systemPrompt, $userMessage, $engine);
        
        $response = ['reply' => $answer, 'language' => $lang, 'ai_backend' => $engine];
        
        // Hybrid reys tekshiruvi (Ixtiyoriy: kelajakda qo'shish mumkin)
        
        // Ob-havo ma'lumotlarini aniqlash
        if (preg_match('/\[WEATHER:(.*?)\]/', $answer, $mw)) {
            $weatherCity = trim($mw[1]);
            require_once __DIR__ . '/WeatherProcessor.php';
            $weatherData = WeatherProcessor::getWeather($weatherCity, $lang);
            $weatherText = WeatherProcessor::formatWeather($weatherData, $lang);
            $answer = str_replace($mw[0], $weatherText, $answer);
        }

        // Lokatsiyani aniqlash
        if (preg_match('/\[LOCATION:(.*?)\]/', $answer, $ml)) {
            $response['location'] = trim($ml[1]);
            $answer = str_replace($ml[0], '', $answer);
        }

        // Reys yo'nalishi (Yer shari 3D) - Barcha teg`larni tozalash
        if (preg_match_all('/\[ROUTE:(.*?)-(.*?)\]/i', $answer, $matches, PREG_SET_ORDER)) {
            $response['show_earth_route'] = true;
            // Birinchisini asosiy yo'nalish sifatida olamiz
            $orig = trim($matches[0][1]);
            $dest = trim($matches[0][2]);
            
            $origCode = (strlen($orig) === 3 && ctype_alpha($orig)) ? strtoupper($orig) : (UzbekDictionaryHelper::findCity($orig) ?: 'TAS');
            $destCode = (strlen($dest) === 3 && ctype_alpha($dest)) ? strtoupper($dest) : (UzbekDictionaryHelper::findCity($dest) ?: $dest);

            $response['origin'] = $origCode;
            $response['destination'] = $destCode;
            
            // Barchasini matndan olib tashlaymiz
            foreach ($matches as $m) {
                $answer = str_replace($m[0], '', $answer);
            }
        } else {
            // Fallback: Agar AI yo'nalish tegini yozishni unutgan bo'lsa
            $cityCode = UzbekDictionaryHelper::findCity($answer);
            if (!$cityCode) {
                $cityCode = UzbekDictionaryHelper::findCity($userMessage);
            }
            
            if ($cityCode && $cityCode !== 'TAS' && preg_match('/(reys|parvoz|uch|kel|uchad|kelad|qonad|yo\'nalish|yonalish)/ui', $userMessage . ' ' . $answer)) {
                $response['show_earth_route'] = true;
                
                $msgCombined = mb_strtolower($userMessage . ' ' . $answer, 'UTF-8');
                $isArrival = false;

                if (preg_match('/\b(toshkentdan|tashkentdan)\b/ui', $msgCombined)) {
                    $isArrival = false; 
                } elseif (preg_match('/\b(toshkentga|tashkentga)\b/ui', $msgCombined)) {
                    $isArrival = true;  
                } elseif (preg_match('/(kelad|qonad|kelish|kelgan)/ui', $msgCombined)) {
                    $isArrival = true;  
                } elseif (preg_match('/(uchad|uchish|ketish|ketad)/ui', $msgCombined)) {
                    $isArrival = false; 
                }

                $response['origin'] = $isArrival ? $cityCode : 'TAS';
                $response['destination'] = $isArrival ? 'TAS' : $cityCode;
            }
        }

        $response['reply'] = $answer;

        // Agar lokatsiya hali topilmagan bo'lsa, avval foydalanuvchi so'rovidan, keyin AI javobidan qidirish
        if (empty($response['location'])) {
            $nav = AirportNavigator::handle($userMessage, $this->mapPoints, $lang);
            if (!$nav || empty($nav['location'])) {
                $nav = AirportNavigator::handle($answer, $this->mapPoints, $lang);
            }
            if ($nav && !empty($nav['location'])) {
                $response['location'] = $nav['location'];
            }
        }

        return $this->finalizeResponse($response, $userMessage, $lang, $engine);
    }

    private function getSystemPrompt($lang, $userMessage = '') {
        $locationContext = $this->getLocationContext();
        $flightContext = $this->getFlightContext($userMessage);
        
        $knowledgeContext = "";
        $kbPath = __DIR__ . '/../knowledge_base.txt';
        if (file_exists($kbPath)) {
            $knowledgeContext = file_get_contents($kbPath);
        }

        // chat.php dagi batafsil promtlar
        $prompts = [
            'uz' => "Siz Toshkent aeroporti (TAS) yordamchisisiz. 
                    KAT'IY QOIDALAR:
                    1. FAQAT O'ZBEK TILIDA javob bering.
                    2. HAR BIR REYS UCHUN [ROUTE:Origin-Dest] tegini qo'shish ABSOLYUTNO MAJBURIY! Toshkentdan uchayotganlar uchun [ROUTE:TAS-Shahar], Toshkentga kelayotganlar uchun [ROUTE:Shahar-TAS] deb yozing.
                    3. Aeroport ichidagi har qanday joy (stoyka, hojatxona, kafe, cip, vip, masjid, darvoza va h.k.) haqida so'ralsa, javobingiz oxiriga [LOCATION:JoyNomi] tegini qo'shing (Masalan: [LOCATION:1-10], [LOCATION:Toilet], [LOCATION:CIP]). Bu xaritani avtomatik ochish uchun shart!
                    4. Markdown belgilarini (** , * , #) UMUMAN ishlatmang! Matnni oddiy, lekin chiroyli tarzda yozing.
                    5. Savollarga birinchi qisqa, to'g'ridan-to'g'ri va aniq lo'nda javob bering.
                    6. Agar ob-havo haqida so'rashsa, albatta [WEATHER:ShaharNomiEn] tegini qo'shing.
                    DATA:
                    $locationContext
                    $flightContext",
            'ru' => "Вы помощник аэропорта TAS. 
                    ПРАВИЛА:
                    1. Ответ ТОЛЬКО на РУССКОМ.
                    2. ОБЯЗАТЕЛЬНО добавляйте [ROUTE:Origin-Dest] для каждого рейса!
                    3. Если спрашивают про любое место в аэропорту (стойки, туалет, кафе, cip, vip, гейт и т.д.), ОБЯЗАТЕЛЬНО добавьте тег [LOCATION:НазваниеТочки] в конец ответа.
                    4. Пишите коротко, по факту и прямо. Не используйте много символов ** и *.
                    5. Если спрашивают про погоду, добавьте тег [WEATHER:CityNameEn].
                    ДАННЫЕ:
                    $locationContext
                    $flightContext",
            'en' => "You are TAS airport assistant. 
                    STRICT RULES:
                    1. Respond ONLY in ENGLISH.
                    2. For EVERY flight you mention, include [ROUTE:Origin-Dest] tag.
                    3. If asked about any place inside the airport (check-in counter, toilet, cafe, cip, gate etc.), you MUST include [LOCATION:PointName] at the end of your response.
                    4. Provide short, precise, and direct answers without extra markdown symbols.
                    5. If asked about the weather, include [WEATHER:CityNameEn].
                    DATA:
                    $locationContext
                    $flightContext
                    KNOWLEDGE BASE:
                    $knowledgeContext"
        ];
        
        return $prompts[$lang] ?? ($prompts['en'] ?? "Airport assistant");
    }

    private function getLocationContext() {
        $ctx = "LOCATIONS:\n";
        foreach ($this->mapPoints as $p) {
            $ctx .= "- {$p['name']} ({$p['type']})\n";
        }
        return $ctx;
    }

    private function getFlightContext($userMessage = '') {
        $flights = $this->flights;
        if (empty($flights)) return "FLIGHTS: No flight data available.\n";
        
        $currentTime = date('H:i');
        $msgLower = mb_strtolower($userMessage, 'UTF-8');

        // 1. Foydalanuvchi qidirayotgan shahar/aeroport kalit so'zlari
        $cityKeywords = [
            'moscow' => ['moscow', 'moskva', 'москва', 'dme', 'vko', 'svo', 'vnukovo', 'domodedovo', 'sheremetyevo'],
            'dubai' => ['dubai', 'dubay', 'дубай', 'dxb', 'dwc'],
            'istanbul' => ['istanbul', 'istambul', 'стамбул', 'истанбул', 'ist', 'saw', 'sabiha'],
            'almaty' => ['almaty', 'olmaota', 'алматы', 'алма-ата', 'ala'],
            'astana' => ['astana', 'ostona', 'nur-sultan', 'астана', 'nqz', 'tse'],
            'grozny' => ['grozny', 'grozniy', 'грозный', 'grv'],
            'novosibirsk' => ['novosibirsk', 'новосибирск', 'ovb', 'tolmachevo'],
            'shanghai' => ['shanghai', 'shanxay', 'шанхай', 'pvg'],
            'tyumen' => ['tyumen', 'tyumen', 'тюмень', 'tjm'],
            'sochi' => ['sochi', 'сочи', 'aer', 'adler'],
            'seoul' => ['seoul', 'seul', 'сеул', 'icn', 'incheon'],
            'tokyo' => ['tokyo', 'tokio', 'токио', 'nrt', 'narita'],
            'beijing' => ['beijing', 'pekin', 'пекин', 'pkx', 'pek'],
            'delhi' => ['delhi', 'deli', 'дели', 'del'],
            'petersburg' => ['petersburg', 'peterburg', 'петербург', 'led', 'pulkovo'],
            'samarkand' => ['samarkand', 'samarqand', 'самарканд', 'skd'],
            'bukhara' => ['bukhara', 'buxoro', 'бухара', 'bhk'],
            'urgench' => ['urgench', 'urganch', 'ургенч', 'ugc'],
            'nukus' => ['nukus', 'нукус', 'ncu'],
            'fergana' => ['fergana', 'fargona', 'farg\'ona', 'фергана', 'feg'],
            'namangan' => ['namangan', 'наманган', 'nma'],
            'andijan' => ['andijan', 'andijon', 'андижан', 'azn']
        ];

        // Qidirilayotgan kalit so'zlarni aniqlash
        $matchedKeywords = [];
        foreach ($cityKeywords as $mainCity => $aliases) {
            foreach ($aliases as $alias) {
                if (mb_strpos($msgLower, $alias) !== false) {
                    $matchedKeywords = array_merge($matchedKeywords, $aliases);
                    break;
                }
            }
        }

        // IATA kod bo'yicha ham tekshirish
        $iataCode = UzbekDictionaryHelper::findCity($userMessage);
        if ($iataCode) {
            $matchedKeywords[] = mb_strtolower($iataCode, 'UTF-8');
        }

        // 2. Reyslarni saralash: mos kelganlar va vaqti yaqinlar
        $matchingFlights = [];
        $upcomingFlights = [];
        $pastFlights = [];

        foreach ($flights as $f) {
            $fromLower = mb_strtolower($f['from'] ?? '', 'UTF-8');
            $toLower = mb_strtolower($f['to'] ?? '', 'UTF-8');
            $flightNoLower = mb_strtolower($f['flight_no'] ?? '', 'UTF-8');
            $fTime = $f['time'] ?? '00:00';

            $isMatch = false;
            if (!empty($matchedKeywords)) {
                foreach ($matchedKeywords as $kw) {
                    if (mb_strpos($fromLower, $kw) !== false || mb_strpos($toLower, $kw) !== false || mb_strpos($flightNoLower, $kw) !== false) {
                        $isMatch = true;
                        break;
                    }
                }
            }

            if ($isMatch) {
                $matchingFlights[] = $f;
            } elseif ($fTime >= $currentTime) {
                $upcomingFlights[] = $f;
            } else {
                $pastFlights[] = $f;
            }
        }

        // Vaqt bo'yicha saralash
        $sortByTime = function($a, $b) {
            return strcmp($a['time'] ?? '00:00', $b['time'] ?? '00:00');
        };
        usort($matchingFlights, $sortByTime);
        usort($upcomingFlights, $sortByTime);
        usort($pastFlights, $sortByTime);

        // Natijaviy ro'yxat: Birinchi mos kelganlar, keyin yaqin kelgusi reyslar
        $finalList = array_merge($matchingFlights, $upcomingFlights, $pastFlights);

        $limit = !empty($matchingFlights) ? 35 : 25;
        $selectedFlights = array_slice($finalList, 0, $limit);

        $ctx = "FLIGHTS (Current Local Time: {$currentTime}):\n";
        $ctx .= "Note: [departure] = TAS -> Destination, [arrival] = Origin -> TAS.\n";
        foreach ($selectedFlights as $f) {
            $ctx .= "- [{$f['type']}] {$f['flight_no']}|{$f['from']} -> {$f['to']}|Time: {$f['time']}|Gate: {$f['gate']}|Status: {$f['status']}\n";
        }
        return $ctx;
    }


    private function finalizeResponse($response, $message, $lang, $backend) {
        $captureId = $this->getLastCaptureId();
        
        $this->pdo->insertOne('chats', [
            'user_message' => $message,
            'ai_response' => $response['reply'],
            'language' => $lang,
            'capture_id' => $captureId,
            'created_at' => date('Y-m-d H:i:s')
        ]);
        
        $response['language'] = $lang;
        $response['ai_backend'] = $backend;
        return $response;
    }

    private function getLastCaptureId() {
        $recentTime = date('Y-m-d H:i:s', time() - 40);
        $capture = $this->pdo->findOne('customer_captures', [
            'captured_at' => ['$gt' => $recentTime]
        ], [
            'sort' => ['captured_at' => -1, '_id' => -1]
        ]);
        return $capture ? ($capture['id'] ?? (string)$capture['_id']) : null;
    }
}

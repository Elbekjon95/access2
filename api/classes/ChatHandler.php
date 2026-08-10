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
        
        // 1. Vaqt bo'yicha saralash
        usort($flights, function($a, $b) {
            return strcmp($a['time'] ?? '00:00', $b['time'] ?? '00:00');
        });

        // Hozirgi vaqtdan keyingi yuz beradigan reyslarni birinchi o'ringa qo'yish
        $currentTime = date('H:i');
        $upcomingFlights = [];
        $pastFlights = [];
        foreach($flights as $f) {
            if (($f['time'] ?? '00:00') >= $currentTime) {
                $upcomingFlights[] = $f;
            } else {
                $pastFlights[] = $f;
            }
        }
        $flights = array_merge($upcomingFlights, $pastFlights);

        // 2. Agar foydalanuvchi ma'lum bir shaharni/kodni so'rayotgan bo'lsa, o'sha shaharni birinchi o'ringa chiqarish
        $cityCode = UzbekDictionaryHelper::findCity($userMessage);
        $relevantsAdded = false;
        if ($cityCode) {
            $relevant = [];
            $others = [];
            foreach ($flights as $f) {
                // 'to' yoki 'from' da shahar/aviakompaniya kodi qatnashganini tekshirish
                if (stripos($f['from'] ?? '', $cityCode) !== false || stripos($f['to'] ?? '', $cityCode) !== false ||
                    stripos($f['flight_no'] ?? '', $cityCode) !== false) {
                    $relevant[] = $f;
                } else {
                    $others[] = $f;
                }
            }
            $flights = array_merge($relevant, $others);
            $relevantsAdded = count($relevant) > 0;
        }

        // Tizim tez ishlashi uchun LLM tokenini tejaymiz.
        // Agar shahar so'ragan bo'lsa, 30 ta ko'rsatamiz. Aks holda faqat keyingi 15 ta reys yetarli.
        $limit = $relevantsAdded ? 30 : 15;

        $ctx = "FLIGHTS (Current Time: " . date('H:i') . "):\n";
        $ctx .= "Note: [departure] means TAS -> City, [arrival] means City -> TAS.\n";
        foreach (array_slice($flights, 0, $limit) as $f) {
            $ctx .= "- [{$f['type']}] {$f['flight_no']}|{$f['from']}->{$f['to']}|{$f['time']}|G:{$f['gate']}|S:{$f['status']}\n";
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

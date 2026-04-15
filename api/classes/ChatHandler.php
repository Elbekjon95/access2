<?php

require_once 'chat_helpers.php';


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

        // QR kodni aniqlash (Cargo, CIP, FASTTRACK, Helicopters, SILK)
        if (preg_match('/\[QR:(.*?)\]/', $answer, $mq)) {
            $response['qr'] = trim($mq[1]);
            $answer = str_replace($mq[0], '', $answer);
        }

        // Reys yo'nalishi (Yer shari 3D) - Barcha teg`larni tozalash
        if (preg_match_all('/\[ROUTE:([A-Z]{3,10})-([A-Z]{3,10})\]/i', $answer, $matches, PREG_SET_ORDER)) {
            $response['show_earth_route'] = true;
            // Birinchisini asosiy yo'nalish sifatida olamiz
            $fromRaw = strtoupper(trim($matches[0][1]));
            $toRaw = strtoupper(trim($matches[0][2]));
            
            // Kodlarni 3 harfga majburlash (agar to'liq nom yozilgan bo'lsa)
            $response['origin'] = UzbekDictionaryHelper::findCity($fromRaw) ?: substr($fromRaw, 0, 3);
            $response['destination'] = UzbekDictionaryHelper::findCity($toRaw) ?: substr($toRaw, 0, 3);
            
            // Barchasini matndan olib tashlaymiz
            foreach ($matches as $m) {
                $answer = str_replace($m[0], '', $answer);
            }
        } else {
            // Fallback: Agar AI yo'nalish tegini yozishni unutgan bo'lsa
            // Avval AI javobidan qidiramiz (chunki AI typo-larni to'g'irlaydi)
            $cityCode = UzbekDictionaryHelper::findCity($answer);
            if (!$cityCode) {
                // Keyin foydalanuvchi xabaridan
                $cityCode = UzbekDictionaryHelper::findCity($userMessage);
            }
            
            if ($cityCode && $cityCode !== 'TAS' && preg_match('/\b(reys|reyslar|parvoz|uchish|uchishni|uchadi|kelish|keladi|qonadi|yo\'nalish|yonalish)\b/ui', $userMessage . ' ' . $answer)) {
                $response['show_earth_route'] = true;
                
                $msgCombined = mb_strtolower($userMessage . ' ' . $answer, 'UTF-8');
                $isArrival = false; // Default: Ketish TAS -> Shahar

                if (preg_match('/\b(toshkentgachan|toshkentdan|tashkentdan)\b/ui', $msgCombined)) {
                    $isArrival = false; 
                } elseif (preg_match('/\b(toshkentga|tashkentga)\b/ui', $msgCombined)) {
                    $isArrival = true;  
                } elseif (preg_match('/\b(keladi|qonadi|kelish|kelgan)\b/ui', $msgCombined)) {
                    $isArrival = true;  
                } elseif (preg_match('/\b(uchadi|uchish|ketish|ketadi)\b/ui', $msgCombined)) {
                    $isArrival = false; 
                }

                $response['origin'] = $isArrival ? $cityCode : 'TAS';
                $response['destination'] = $isArrival ? 'TAS' : $cityCode;
            }
        }

        $response['reply'] = $answer;
        
        // UCHIB KETISH REYSLARI UCHUN OB-HAVO: Agar manzil shahri aniqlangan bo'lsa va bu departure (TAS -> Shahar) bo'lsa,
        // manzil shaharning ob-havosini avtomatik qo'shamiz (faqat AI javobida [WEATHER] tegi BO'LMASA)
        if (!empty($response['destination']) && $response['destination'] !== 'TAS' 
            && (!empty($response['origin']) && $response['origin'] === 'TAS')
            && !preg_match('/\\[WEATHER:/i', $answer)) {
            
            $destCode = $response['destination'];
            $weatherCityEn = UzbekDictionaryHelper::iataToEnglishCity($destCode);
            
            if ($weatherCityEn) {
                require_once __DIR__ . '/WeatherProcessor.php';
                $weatherData = WeatherProcessor::getWeather($weatherCityEn, $lang);
                $weatherText = WeatherProcessor::formatWeather($weatherData, $lang);
                
                if (!isset($weatherData['error'])) {
                    // Ob-havo ma'lumotini javob oxiriga qo'shamiz
                    $cityNameLocal = WeatherProcessor::translateCityToUz($weatherCityEn);
                    if ($lang === 'uz') {
                        $response['reply'] .= "\n\n🌤 {$cityNameLocal} ob-havosi: {$weatherText}.";
                    } elseif ($lang === 'ru') {
                        $response['reply'] .= "\n\n🌤 Погода в {$weatherCityEn}: {$weatherText}.";
                    } else {
                        $response['reply'] .= "\n\n🌤 Weather in {$weatherCityEn}: {$weatherText}.";
                    }
                }
            }
        }

        // Final "mop-up": Faqat maxsus tizim teglari (ROUTE, LOCATION, WEATHER, QR) bo'lsa tozalaymiz
        $response['reply'] = preg_replace('/\[(ROUTE|LOCATION|WEATHER|QR):[^\]]+\]/i', '', $response['reply']);
        // SYSTEM_NOTE va shunga o'xshash tizim izohlarini olib tashlaymiz
        $response['reply'] = preg_replace('/>>?\s*SYSTEM_NOTE\s*:.*$/mi', '', $response['reply']);
        $response['reply'] = preg_replace('/>>?\s*SYSTEM[_\s]?NOTE.*$/mi', '', $response['reply']);
        $response['reply'] = trim($response['reply']);

        // Agar lokatsiya topilmasa, mavjud mapPoints dan qidirish
        if (empty($response['location'])) {
            $nav = AirportNavigator::handle($userMessage, $this->mapPoints, $lang);
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
                    2. HAR BIR REYS UCHUN [ROUTE:Origin-Dest] tegini qo'shish majburiy, LEKIN JAVOBINGIZNING ENG OXIRIGA, yangi qatorda yozing. DIQQAT: Siz audio asistent bo'lganingiz uchun TEGLARNI ASLO OVOZ CHIQARIB O'QIMANG! Qavslarni o'qib yubormang!
                    3. NAVIGATSIYA: Agar joy haqida so'ralsa, eng oxirgi qatorda [LOCATION:ExactPointName] deb teg qoldiring. BUNIXAM O'QIMANG.
                    4. Markdown belgilarini (** , * , #) UMUMAN ishlatmang!
                    5. Savollarga juda QISQA, luqma tashlamasdan, aniq javob bering.
                    6. XIZMATLAR (CIP/VIP, Fast Track, mehmonxona): Avval xizmat haqida BATAFSIL ma'lumot bering (narx, qanday sotib olish, qulayliklar). Keyin agar batafsil ma'lumot uchun QR kod mavjud bo'lsa, eng oxirida [QR:Name] tegini qo'shing va OVOZDA O'QIMANG.
                    7. Statuslarni (SCH, ARR, DEP) 'Jadval bo\'yicha', 'Uchib ketdi' deb bering.
                    8. QR-KODLAR: Agar Cargo, CIP, FASTTRACK, Helicopters so'ralsa eng oxirda [QR:Name] tegini yozing va OVOZDA O'QIMANG.
                    9. UCHIB KETISH OB-HAVOSI: tizim avtomat qo'shadi, siz gapirmang.
                    10. SYSTEM_NOTE, >> yoki boshqa tizim izohlarini ASLO yozmang. Faqat foydalanuvchiga yo'naltirilgan javob yozing.
                    10. REGISTRATSIYA STOYKALAR: Agar reys haqida so'ralsa va stoyka (C:) ma'lumoti mavjud bo'lsa, javobda ALBATTA stoyka raqamini aytib bering. Masalan: \"Registratsiya 12-14 stoykalarida\".
                    DATA:
                    $locationContext
                    $flightContext
                    KNOWLEDGE BASE:
                    $knowledgeContext",
            'ru' => "Вы аудио помощник аэропорта TAS. 
                    ПРАВИЛА:
                    1. Ответ ТОЛЬКО на РУССКОМ.
                    2. ОБЯЗАТЕЛЬНО добавляйте [ROUTE:Origin-Dest] В САМЫЙ КОНЕЦ ответа! ВНИМАНИЕ: НЕ ПРОИЗНОСИТЕ ТЕГИ В АУДИО ОЗВУЧКЕ! Скобки читать запрещено!
                    3. НАВИГАЦИЯ: Добавьте в КОНЕЦ ответа [LOCATION:ExactPointName] если место есть в LOCATIONS. НЕ ОЗВУЧИВАЙТЕ ЕГО.
                    4. Пишите коротко. Без Markdown.
                    7. НЕ пишите SYSTEM_NOTE, >> или любые системные комментарии. Только ответ для пользователя.
                    5. УСЛУГИ (CIP/VIP, Fast Track, отель): Сначала дайте ПОДРОБНУЮ информацию (цена, как купить, удобства). Затем, если есть QR-код для деталей, добавьте [QR:Name] в конце и НЕ ЧИТАЙТЕ ЕГО В АУДИО.
                    6. QR-КОДЫ: Добавьте [QR:Name] в конце и НЕ ЧИТАЙТЕ ЕГО В АУДИО.
                    ДАННЫЕ:
                    $locationContext
                    $flightContext
                    БАЗА ЗНАНИЙ:
                    $knowledgeContext",
            'en' => "You are TAS audio assistant. 
                    STRICT RULES:
                    1. Respond ONLY in ENGLISH.
                    2. Include [ROUTE:Origin-Dest] at the VERY END. Do NOT read tags out loud in the audio!
                    3. NAVIGATION: Append [LOCATION:ExactPointName] to the END. Do NOT speak this tag.
                    4. Provide very short answers. No markdown.
                    5. SERVICES (CIP/VIP, Fast Track, hotel): First provide DETAILED information (price, how to buy, amenities). Then, if QR code available for details, append [QR:Name] at the end. Do NOT read it.
                    6. QR CODES: Append [QR:Name] to the end. Do NOT read it.
                    7. NEVER write SYSTEM_NOTE, >> or any system comments. Only user-facing response.
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

        // 2. Agar foydalanuvchi ma'lum bir shaharni so'rayotgan bo'lsa, o'sha shaharni birinchi o'ringa chiqarish
        $cityCode = UzbekDictionaryHelper::findCity($userMessage);
        if ($cityCode) {
            $relevant = [];
            $others = [];
            $relatedCodes = UzbekDictionaryHelper::getRelatedCodes($cityCode);
            
            foreach ($flights as $f) {
                $isMatch = false;
                foreach ($relatedCodes as $rc) {
                    if (stripos($f['from'], $rc) !== false || stripos($f['to'], $rc) !== false) {
                        $isMatch = true;
                        break;
                    }
                }
                
                if ($isMatch) {
                    $relevant[] = $f;
                } else {
                    $others[] = $f;
                }
            }
            $flights = array_merge($relevant, $others);
        }

        $ctx = "FLIGHTS (Current Time: " . date('H:i') . "):\n";
        $ctx .= "Note: [departure] means TAS -> City, [arrival] means City -> TAS.\n";
        foreach (array_slice($flights, 0, 20) as $f) {
            $checkin = $f['checkin_counters'] ?? 'N/A';
            $ctx .= "- [{$f['type']}] {$f['flight_no']}|{$f['from']}->{$f['to']}|{$f['time']}|G:{$f['gate']}|C:{$checkin}|S:{$f['status']}\n";
        }
        return $ctx;
    }

    private function finalizeResponse($response, $message, $lang, $backend) {
        $captureId = $this->getLastCaptureId();
        
        $stmt = $this->pdo->prepare("INSERT INTO chats (user_message, ai_response, language, capture_id) VALUES (?, ?, ?, ?)");
        $stmt->execute([$message, $response['reply'], $lang, $captureId]);
        
        $response['language'] = $lang;
        $response['ai_backend'] = $backend;
        return $response;
    }

    private function getLastCaptureId() {
        $stmt = $this->pdo->query("SELECT id FROM customer_captures WHERE captured_at > NOW() - INTERVAL 40 SECOND ORDER BY id DESC LIMIT 1");
        $capture = $stmt->fetch();
        return $capture ? $capture['id'] : null;
    }
}

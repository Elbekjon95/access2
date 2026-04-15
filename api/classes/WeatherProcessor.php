<?php

class WeatherProcessor {
    private static $apiKey = OPENWEATHER_API_KEY;
    private static $cacheDir = __DIR__ . '/../../cache/weather/';

    /**
     * Shahar bo'yicha ob-havo ma'lumotlarini oladi.
     */
    public static function getWeather($city = 'Tashkent', $lang = 'uz') {
        if (empty(self::$apiKey) || self::$apiKey === 'YOUR_FREE_API_KEY_HERE') {
            return ['error' => 'API_KEY_NOT_CONFIGURED', 'city' => $city];
        }

        $city = trim($city);
        $cacheFile = self::$cacheDir . md5(strtolower($city) . $lang) . '.json';

        // 1. Keshni tekshirish (15 daqiqa)
        if (file_exists($cacheFile) && (time() - filemtime($cacheFile) < 900)) {
            return json_decode(file_get_contents($cacheFile), true);
        }

        // 2. API dan olish
        $langCode = ($lang === 'uz') ? 'uz' : (($lang === 'ru') ? 'ru' : 'en');
        $url = "https://api.openweathermap.org/data/2.5/weather?q=" . urlencode($city) . "&appid=" . self::$apiKey . "&units=metric&lang=" . $langCode;
        
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 5,
            CURLOPT_SSL_VERIFYPEER => false
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 200) {
            $data = json_decode($response, true);
            $result = [
                'temp' => round($data['main']['temp']),
                'feels_like' => round($data['main']['feels_like']),
                'description' => $data['weather'][0]['description'],
                'icon' => $data['weather'][0]['icon'],
                'humidity' => $data['main']['humidity'],
                'city' => $data['name'],
                'timestamp' => time()
            ];

            // Keshga yozish
            if (!is_dir(self::$cacheDir)) mkdir(self::$cacheDir, 0777, true);
            file_put_contents($cacheFile, json_encode($result));

            return $result;
        }

        return ['error' => 'City not found or API error', 'code' => $httpCode, 'city' => $city];
    }

    /**
     * Ob-havo ma'lumotini matn ko'rinishida formatlaydi.
     */
    public static function formatWeather($w, $lang = 'uz') {
        if (isset($w['error'])) {
            if ($w['error'] === 'API_KEY_NOT_CONFIGURED') {
                return $lang === 'uz' ? "Ob-havo xizmati hali sozlanmagan. Iltimos, .env fayliga OpenWeatherMap API kalitini qo'shing." : "Weather service is not configured. Please add OpenWeatherMap API key to .env file.";
            }
            return $lang === 'uz' ? "Kechirasiz, {$w['city']} shahri uchun ob-havo ma'lumotlarini olib bo'lmadi." : "Sorry, couldn't get weather for {$w['city']}.";
        }

        $desc = $w['description'];
        
        // Majburiy tarjima (Agar API inglizcha qaytarsa)
        if ($lang === 'uz') {
            $translations = [
                'clear sky' => 'musaffo osmon',
                'few clouds' => 'biroz bulutli',
                'scattered clouds' => 'tarqoq bulutli',
                'broken clouds' => 'bulutli',
                'overcast clouds' => 'bulutli (yopiq havo)',
                'shower rain' => 'qisqa muddatli yomg\'ir',
                'rain' => 'yomg\'ir',
                'thunderstorm' => 'momaqaldiroq',
                'snow' => 'qor',
                'mist' => 'tuman',
                'haze' => 'g\'ubor',
                'smoke' => 'tutun',
                'dust' => 'chang',
                'fog' => 'tuman',
                'sand' => 'qum',
                'ash' => 'kul',
                'squall' => 'dovul',
                'tornado' => 'tornado',
                'light rain' => 'yengil yomg\'ir',
                'moderate rain' => 'o\'rtacha yomg\'ir',
                'heavy intensity rain' => 'kuchli yomg\'ir',
                'very heavy rain' => 'juda kuchli yomg\'ir',
                'extreme rain' => 'o\'ta kuchli yomg\'ir',
                'freezing rain' => 'muzli yomg\'ir',
                'light intensity shower rain' => 'yengil yomg\'ir (jala)',
                'heavy intensity shower rain' => 'kuchli yomg\'ir (jala)',
                'ragged shower rain' => 'kuchli yomg\'ir',
                'light snow' => 'yengil qor',
                'heavy snow' => 'kuchli qor',
                'sleet' => 'yomg\'ir aralash qor',
                'light shower sleet' => 'yengil yomg\'ir aralash qor',
                'shower sleet' => 'yomg\'ir aralash qor',
                'light rain and snow' => 'yengil yomg\'ir va qor',
                'rain and snow' => 'yomg\'ir va qor',
                'light shower snow' => 'yengil qor',
                'shower snow' => 'qor (dala)',
                'heavy shower snow' => 'kuchli qor'
            ];
            $lowerDesc = strtolower($desc);
            if (isset($translations[$lowerDesc])) {
                $desc = $translations[$lowerDesc];
            }
        }

        if ($lang === 'uz') {
            return "Hozir harorat {$w['temp']} daraja, {$desc}. Namlik: {$w['humidity']}%";
        } else if ($lang === 'ru') {
            return "Сейчас температура {$w['temp']}°C. {$w['description']}. Влажность: {$w['humidity']}%";
        } else {
            return "Currently temperature is {$w['temp']}°C. {$w['description']}. Humidity is {$w['humidity']}%.";
        }
    }

    public static function translateCityToUz($enCity) {
        $map = [
            'Tashkent' => 'Toshkent',
            'Moscow' => 'Moskva',
            'London' => 'London',
            'Istanbul' => 'Istanbul',
            'Dubai' => 'Dubay',
            'Delhi' => 'Dehli',
            'New York' => 'Nyu-York',
            'Beijing' => 'Pekin',
            'Seoul' => 'Seul',
            'Paris' => 'Parij',
            'Berlin' => 'Berlin',
            'Rome' => 'Rim',
            'Tokyo' => 'Tokio',
            'Samarkand' => 'Samarqand',
            'Bukhara' => 'Buxoro',
            'Khiva' => 'Xiva',
            'Namangan' => 'Namangan',
            'Andijan' => 'Andijon',
            'Fergana' => 'Farg\'ona',
            'Nukus' => 'Nukus',
            'Karshi' => 'Qarshi',
            'Termez' => 'Termiz',
            'Navoi' => 'Navoiy',
            'Urgench' => 'Urganch',
            'Gulistan' => 'Guliston',
            'Jizzakh' => 'Jizzax',
            'Madrid' => 'Madrid',
            'Barcelona' => 'Barselona',
            'Milan' => 'Milan',
            'Prague' => 'Praga',
            'Vienna' => 'Vena',
            'Cairo' => 'Qohira',
            'Doha' => 'Doha',
            'Kuwait' => 'Quvayt',
            'Sharjah' => 'Sharja',
            'Almaty' => 'Olmaota',
            'Astana' => 'Ostona',
            'Bishkek' => 'Bishkek',
            'Dushanbe' => 'Dushanbe',
            'Ashgabat' => 'Ashxobod',
            'Baku' => 'Boku'
        ];
        return $map[$enCity] ?? $enCity;
    }
}

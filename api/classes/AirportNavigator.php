<?php

class AirportNavigator {
    /**
     * Matndagi so'zli va raqamli stoykalarni aniqlaydi.
     * Masalan: "yigirma birinchi stoyka" -> 21 -> "21-30 stoykalar"
     * "beshinchi stoyka" -> 5 -> "1-10 stoykalar"
     */
    public static function extractCounterNumber($msg) {
        $msg = mb_strtolower(trim($msg), 'UTF-8');

        // 1. To'g'ridan-to'g'ri raqam: "21-stoyka", "stoyka 21", "21 stoyka"
        if (preg_match('/(?:stoyka|стойк[а-я]*|counter|check[-\s]?in|registr[а-я]*)\s*(\d{1,2})\b/iu', $msg, $m) ||
            preg_match('/\b(\d{1,2})\s*(?:stoyka|стойк[а-я]*|counter|check[-\s]?in|registr[а-я]*|-\s*stoyka)\b/iu', $msg, $m)) {
            return (int)$m[1];
        }

        // 2. O'zbekcha o'nliklar va birliklar
        $tens = [
            'qirq' => 40, 'qirqinchi' => 40,
            'o\'ttiz' => 30, 'ottiz' => 30, 'oʻttiz' => 30, 'o‘ttiz' => 30, 'o\'ttizinchi' => 30, 'ottizinchi' => 30,
            'yigirma' => 20, 'yigirmanchi' => 20,
            'o\'n' => 10, 'on' => 10, 'oʻn' => 10, 'o‘n' => 10, 'o\'ninchi' => 10, 'oninchi' => 10,
        ];

        $units = [
            'to\'qqiz' => 9, 'toqqiz' => 9, 'toʻqqiz' => 9, 'to‘qqiz' => 9, 'to\'qqizinchi' => 9, 'toqqizinchi' => 9,
            'sakkiz' => 8, 'sakkizinchi' => 8,
            'yetti' => 7, 'yettinchi' => 7,
            'olti' => 6, 'oltinchi' => 6,
            'besh' => 5, 'beshinchi' => 5,
            'to\'rt' => 4, 'tort' => 4, 'toʻrt' => 4, 'to‘rt' => 4, 'to\'rtinchi' => 4, 'tortinchi' => 4,
            'uch' => 3, 'uchinchi' => 3,
            'ikki' => 2, 'ikkinchi' => 2,
            'bir' => 1, 'birinchi' => 1,
        ];

        // 2 ta so'zli: "yigirma birinchi", "o'ttiz beshinchi", "o'n yettinchi"
        foreach ($tens as $tWord => $tVal) {
            foreach ($units as $uWord => $uVal) {
                if (preg_match('/\b' . preg_quote($tWord, '/') . '\s+' . preg_quote($uWord, '/') . '\b/iu', $msg)) {
                    return $tVal + $uVal;
                }
            }
        }

        // Faqat o'nliklar: "yigirmanchi", "o'ttizinchi"
        foreach ($tens as $tWord => $tVal) {
            if (preg_match('/\b' . preg_quote($tWord, '/') . '\b/iu', $msg)) {
                return $tVal;
            }
        }

        // Faqat birliklar: "beshinchi", "birinchi", "uchinchi"
        foreach ($units as $uWord => $uVal) {
            if (preg_match('/\b' . preg_quote($uWord, '/') . '\b/iu', $msg)) {
                return $uVal;
            }
        }

        // 3. Ruscha murakkab sonlar
        $ruTens = [
            'сорок' => 40, 'сороковая' => 40, 'сороковой' => 40,
            'тридцать' => 30, 'тридцатая' => 30, 'тридцатый' => 30,
            'двадцать' => 20, 'двадцатая' => 20, 'двадцатый' => 20,
            'десять' => 10, 'десятая' => 10, 'десятый' => 10,
            'одиннадцать' => 11, 'двенадцать' => 12, 'тринадцать' => 13, 'четырнадцать' => 14,
            'пятнадцать' => 15, 'шестнадцать' => 16, 'семнадцать' => 17, 'восемнадцать' => 18, 'девятнадцать' => 19,
        ];
        $ruUnits = [
            'девять' => 9, 'девятая' => 9, 'девятый' => 9,
            'восемь' => 8, 'восьмая' => 8, 'восьмой' => 8,
            'семь' => 7, 'седьмая' => 7, 'седьмой' => 7,
            'шесть' => 6, 'шестая' => 6, 'шестой' => 6,
            'пять' => 5, 'пятая' => 5, 'пятый' => 5,
            'четыре' => 4, 'четвертая' => 4, 'четвертый' => 4,
            'три' => 3, 'третья' => 3, 'третий' => 3,
            'два' => 2, 'вторая' => 2, 'второй' => 2,
            'один' => 1, 'первая' => 1, 'первый' => 1,
        ];

        foreach ($ruTens as $tWord => $tVal) {
            foreach ($ruUnits as $uWord => $uVal) {
                if (preg_match('/\b' . preg_quote($tWord, '/') . '\s+' . preg_quote($uWord, '/') . '\b/iu', $msg)) {
                    return $tVal + $uVal;
                }
            }
        }
        foreach ($ruTens as $tWord => $tVal) {
            if (preg_match('/\b' . preg_quote($tWord, '/') . '\b/iu', $msg)) {
                return $tVal;
            }
        }
        foreach ($ruUnits as $uWord => $uVal) {
            if (preg_match('/\b' . preg_quote($uWord, '/') . '\b/iu', $msg)) {
                return $uVal;
            }
        }

        // Agar matnda umumiy stoyka so'zi bo'lsa lekin raqam bo'lmasa -> 1-10
        if (preg_match('/\b(stoyka|stoykalar|counter|checkin|ro\'yxatdan o\'tish|регистрация|стойка|стойки)\b/iu', $msg)) {
            return 1;
        }

        return null;
    }

    /**
     * Stoyka raqamiga mos xaritadagi nuqtani topadi (1-10, 11-20, 21-30, 31-40).
     */
    public static function getCounterPointName($counterNum, $mapPoints) {
        $rangeName = "1-10";
        if ($counterNum >= 1 && $counterNum <= 10) {
            $rangeName = "1-10";
        } elseif ($counterNum >= 11 && $counterNum <= 20) {
            $rangeName = "11-20";
        } elseif ($counterNum >= 21 && $counterNum <= 30) {
            $rangeName = "21-30";
        } elseif ($counterNum >= 31 && $counterNum <= 40) {
            $rangeName = "31-40";
        }

        // Bazadagi mapPoints dan mosini qidirish
        foreach ($mapPoints as $p) {
            $name = $p['name'] ?? '';
            if (stripos($name, $rangeName) !== false) {
                return $p;
            }
        }

        // Fallback: birinchi counter yoki gate
        foreach ($mapPoints as $p) {
            if (($p['type'] ?? '') === 'counter' || ($p['type'] ?? '') === 'gate') {
                return $p;
            }
        }

        return null;
    }

    /**
     * Navigatsiya so'rovlarini qayta ishlaydi.
     */
    public static function handle($msg, $mapPoints, $lang = 'uz') {
        $msg = mb_strtolower($msg, 'UTF-8');
        
        $findPointByType = function ($types) use ($mapPoints) {
            foreach ($mapPoints as $p) {
                if (in_array($p['type'] ?? '', $types, true)) return $p;
            }
            return null;
        };

        $findPointByName = function ($keywords) use ($mapPoints) {
            foreach ($mapPoints as $p) {
                $name = mb_strtolower($p['name'] ?? '', 'UTF-8');
                foreach ($keywords as $kw) {
                    if (mb_strpos($name, mb_strtolower($kw, 'UTF-8')) !== false) return $p;
                }
            }
            return null;
        };

        // 1. Stoyka / Ro'yxatdan o'tish (Check-in Counters)
        $counterNum = self::extractCounterNumber($msg);
        if ($counterNum !== null) {
            $point = self::getCounterPointName($counterNum, $mapPoints);
            if ($point) {
                $pName = $point['name'];
                $reply = self::t($lang,
                    "{$counterNum}-stoyka {$pName} zonasida joylashgan. Xaritada yo'nalish ko'rsatildi, marhamat yo'l bo'ylab yuring.",
                    "Counter {$counterNum} is located in {$pName} area. Route is displayed on the map, please follow the path.",
                    "Стойка {$counterNum} находится в зоне {$pName}. Маршрут показан на карте, пожалуйста, следуйте по нему."
                );
                return ['reply' => $reply, 'location' => $pName];
            }
        }

        // 2. Hojatxona / Tualet / WC
        if (preg_match('/(hojatxona|tualet|wc|toilet|restroom|bathroom|туалет|санузел)/iu', $msg)) {
            $p = $findPointByType(['toilet']) ?: $findPointByName(['toilet', 'hojatxona', 'tualet', 'wc']);
            if ($p) return self::formatResponse($p, $lang, "Hojatxona");
        }

        // 3. Masjid / Namozxona
        if (preg_match('/(mosque|masjid|mesjid|prayer|musalla|namoz|namaz|mescit|cami|мечеть|молельная)/iu', $msg)) {
            $p = $findPointByType(['mosque', 'prayer_room', 'prayer', 'masjid']) ?: $findPointByName(['masjid', 'namoz', 'prayer', 'mosque']);
            if ($p) return self::formatResponse($p, $lang, "Namozxona (Masjid)");
        }

        // 4. CIP / VIP / Lounge / Zal
        if (preg_match('/(anor|anjir)/iu', $msg, $m)) {
            $p = $findPointByName([$m[1]]);
            if ($p) return self::formatResponse($p, $lang, strtoupper($m[1]) . " zali");
        }

        if (preg_match('/(cip|vip|lounge|business|бизнес|вип|лаунж)/iu', $msg)) {
            $p = $findPointByType(['cip', 'vip', 'vip_lounge', 'lounge', 'business']) ?: $findPointByName(['cip', 'vip', 'lounge']);
            if ($p) return self::formatResponse($p, $lang, "CIP/VIP zali");
        }

        // 5. Kafe / Restoran / Food
        if (preg_match('/(kafe|cafe|restoran|restaurant|ovqat|food|qahva|coffee|кафе|ресторан)/iu', $msg)) {
            $p = $findPointByType(['cafe', 'restaurant']) ?: $findPointByName(['kafe', 'cafe', 'restoran', 'restaurant']);
            if ($p) return self::formatResponse($p, $lang, "Kafe / Restoran");
        }

        // 6. Duty Free / Magazin / Do'kon
        if (preg_match('/(duty\s*free|dyuti\s*fri|magazin|shop|do\'kon|dokon|дюти\s*фри|магазин)/iu', $msg)) {
            $p = $findPointByType(['shop', 'duty_free']) ?: $findPointByName(['duty free', 'duty', 'shop']);
            if ($p) return self::formatResponse($p, $lang, "Duty Free do'koni");
        }

        // 7. Tibbiyot / Medpunkt / First Aid
        if (preg_match('/(tibbiyot|medpunkt|doktor|vrach|shifokor|first\s*aid|medical|медпункт|врач|аптека)/iu', $msg)) {
            $p = $findPointByType(['medical', 'medpunkt', 'first_aid']) ?: $findPointByName(['tibbiyot', 'medpunkt', 'medical', 'aid']);
            if ($p) return self::formatResponse($p, $lang, "Tibbiyot punkti");
        }

        // 8. Axborot stoli / Info desk / Reception
        if (preg_match('/(info|ma\'lumot|malumot|reception|spravka|справка|ресепшн)/iu', $msg)) {
            $p = $findPointByType(['reception', 'info', 'counter']) ?: $findPointByName(['info', 'reception', 'axborot']);
            if ($p) return self::formatResponse($p, $lang, "Axborot markazi");
        }

        // 9. Bagaj / Yuk topshirish / Yuk olish (Baggage)
        if (preg_match('/(bagaj|yuk|baggage|luggage|багаж)/iu', $msg)) {
            $p = $findPointByType(['baggage', 'luggage']) ?: $findPointByName(['bagaj', 'baggage', 'yuk']);
            if ($p) return self::formatResponse($p, $lang, "Bagaj bo'limi");
        }

        // 10. Gate / Darvoza (Raqam bilan)
        if (preg_match('/(?:darvoza|gate|выход|дарвоза)\s*([A-Za-z]?\d+)/iu', $msg, $m)) {
            $gateCode = strtoupper($m[1]);
            foreach ($mapPoints as $p) {
                if (($p['type'] ?? '') === 'gate' && strpos(strtoupper($p['name'] ?? ''), $gateCode) !== false) {
                    return self::formatResponse($p, $lang, "Darvoza {$gateCode}");
                }
            }
        }

        // 11. Baza nuqtalarining bevosita nomi bo'yicha qidirish
        foreach ($mapPoints as $p) {
            $pName = $p['name'] ?? '';
            if (empty($pName) || $pName === 'kiosk_start') continue;
            if (mb_stripos($msg, mb_strtolower($pName, 'UTF-8')) !== false) {
                return self::formatResponse($p, $lang, $pName);
            }
        }

        return null;
    }

    private static function formatResponse($p, $lang, $displayName = null) {
        $name = $p['name'] ?? 'Manzil';
        $title = $displayName ?: $name;
        $reply = self::t($lang,
            "{$title} joylashuvi xaritada ko'rsatildi. Marhamat, belgilangan yo'nalish bo'ylab yuring.",
            "Location of {$title} is shown on the map. Please follow the route.",
            "Местоположение {$title} показано на карте. Пожалуйста, следуйте по маршруту."
        );
        return ['reply' => $reply, 'location' => $name];
    }

    private static function t($lang, $uz, $en, $ru) {
        if ($lang === 'uz') return $uz;
        if ($lang === 'ru') return $ru;
        return $en;
    }
}

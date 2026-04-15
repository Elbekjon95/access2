<?php

class AirportNavigator {
    /**
     * Navigatsiya so'rovlarini qayta ishlaydi.
     */
    public static function handle($msg, $mapPoints, $lang = 'uz') {
        $msg = mb_strtolower($msg, 'UTF-8');
        
        // Safe Check: Agar xabarda narx, qancha, ma'lumot kabi so'zlar bo'lsa, navigatsiyani rad etamiz
        if (preg_match('/\\b(narxi|qancha|tarif|price|cost|how|what)\\b/u', $msg)) {
            return null;
        }
        $findPointByType = function ($types) use ($mapPoints) {
            foreach ($mapPoints as $p) {
                if (in_array($p['type'], $types, true)) return $p;
            }
            return null;
        };

        $findPointByName = function ($keywords) use ($mapPoints) {
            foreach ($mapPoints as $p) {
                $name = mb_strtolower($p['name'], 'UTF-8');
                foreach ($keywords as $kw) {
                    if (mb_strpos($name, $kw) !== false) return $p;
                }
            }
            return null;
        };

        // 1. Tualet / WC
        if (preg_match('/\\b(hojatxona|tualet|wc|restroom|bathroom)\\b/u', $msg)) {
            $p = $findPointByType(['toilet']);
            if ($p) return self::formatResponse($p, $lang);
        }

        // 2. Masjid / Namozxona
        if (preg_match('/(mosque|masjid|mesjid|prayer|musalla|namoz|namaz|mescit|cami)/iu', $msg)) {
            $p = $findPointByType(['mosque', 'prayer_room', 'prayer', 'masjid']);
            if ($p) return self::formatResponse($p, $lang);
        }

        // 3. CIP / VIP / Lounge
        if (preg_match('/\b(cip|vip|lounge|business|bi\s*ay\s*pi|vi\s*ip|si\s*ay\s*pi)\b/iu', $msg)) {
            $p = $findPointByType(['cip', 'vip', 'vip_lounge', 'lounge', 'business', 'vip_zone', 'cip_zone']);
            if ($p) return self::formatResponse($p, $lang);
        }

        // 4. Gate / Darvoza (Raqam bilan)
        if (preg_match('/\\b(darvoza|gate)\\s*([A-Za-z]?\\d+)\\b/u', $msg, $m)) {
            $gateCode = strtoupper($m[2]);
            foreach ($mapPoints as $p) {
                if ($p['type'] === 'gate' && strpos(strtoupper($p['name']), $gateCode) !== false) {
                    return self::formatResponse($p, $lang);
                }
            }
        }

        // 5. Har qanday nuqta nomi (name) yoki turi (type) bo'yicha universal qidiruv
        foreach ($mapPoints as $p) {
            $name = mb_strtolower($p['name'], 'UTF-8');
            $type = mb_strtolower($p['type'], 'UTF-8');
            
            // Agar nuqta nomi matn ichida bo'lsa (yoki aksincha)
            if (mb_stripos($msg, $name) !== false || mb_stripos($name, $msg) !== false) {
                return self::formatResponse($p, $lang);
            }
            
            // Agar turi matn ichida bo'lsa (masalan, 'cafe', 'toilet')
            if (mb_stripos($msg, $type) !== false) {
                return self::formatResponse($p, $lang);
            }
        }

        return null; // Topilmadi
    }

    private static function formatResponse($p, $lang) {
        $name = $p['name'];
        $reply = self::t($lang,
            "{$name} shu yerda. Marhamat, yo'nalish bo'ylab yuring. [LOCATION:{$name}]",
            "{$name} is here. Please follow the route. [LOCATION:{$name}]",
            "{$name} находится здесь. Пожалуйста, следуйте по маршруту. [LOCATION:{$name}]"
        );
        return ['reply' => $reply, 'location' => $name];
    }

    private static function t($lang, $uz, $en, $ru) {
        if ($lang === 'uz') return $uz;
        if ($lang === 'ru') return $ru;
        return $en;
    }
}

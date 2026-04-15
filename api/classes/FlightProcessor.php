<?php

class FlightProcessor {
    /**
     * Reys kodi va raqami orqali reysni topadi.
     */
    public static function findByCode($flights, $code, $num) {
        $target = strtoupper($code) . $num;
        foreach ($flights as $f) {
            $flightNo = strtoupper(str_replace(' ', '', $f['flight_no'] ?? ''));
            if ($flightNo === $target) return $f;
        }
        return null;
    }

    /**
     * Reys tafsilotlarini matn ko'rinishida formatlaydi.
     */
    public static function formatDetails($flight, $lang = 'uz') {
        $parts = [];
        $time = trim($flight['time'] ?? '');
        if ($time !== '' && $time !== 'N/A') {
            $parts[] = self::t($lang, "soat {$time} da", "at {$time}", "в {$time}");
        }
        
        $gate = trim($flight['gate'] ?? '');
        if ($gate !== '' && $gate !== 'N/A') {
            $parts[] = self::t($lang, "{$gate} darvozasi orqali", "via gate {$gate}", "чеrez гейт {$gate}");
        }
        
        $counterRange = self::extractCounterRange($flight);
        if ($counterRange) {
            $parts[] = self::t($lang, "ro'yxatdan o'tish {$counterRange} stoykalarda", "check-in at counters {$counterRange}", "регистрация на стойках {$counterRange}");
        }
        
        return $parts ? ' ' . implode(' ', $parts) : '';
    }

    /**
     * Reys holatini (status) formatlaydi.
     */
    public static function formatStatus($flight, $lang = 'uz') {
        $status = trim($flight['status'] ?? '');
        if ($status !== '' && $status !== 'N/A' && !self::parseCounterRange($status)) {
            return self::t($lang, " Hozirgi holat: {$status}.", " Current status: {$status}.", " Текущий статус: {$status}.");
        }
        return '';
    }

    /**
     * Ro'yxatdan o'tish stoykalarini aniqlaydi.
     */
    public static function extractCounterRange($flight) {
        $checkin = trim((string)($flight['checkin_counters'] ?? ''));
        $status = trim((string)($flight['status'] ?? ''));

        $fromCheckin = self::parseCounterRange($checkin);
        if ($fromCheckin) return $fromCheckin;

        $fromStatus = self::parseCounterRange($status);
        if ($fromStatus && ($checkin === '' || strtoupper($checkin) === 'N/A' || !preg_match('/\d/', $checkin))) {
            return $fromStatus;
        }
        return null;
    }

    private static function parseCounterRange($text) {
        $s = trim((string)$text);
        if ($s === '' || strtoupper($s) === 'N/A') return null;
        if (preg_match('/\b(\d{1,2})\s*[-–—]\s*(\d{1,2})\b/u', $s, $m)) {
            $lo = min((int)$m[1], (int)$m[2]);
            $hi = max((int)$m[1], (int)$m[2]);
            return "{$lo}-{$hi}";
        }
        return null;
    }

    private static function t($lang, $uz, $en, $ru) {
        if ($lang === 'uz') return $uz;
        if ($lang === 'ru') return $ru;
        return $en;
    }
}

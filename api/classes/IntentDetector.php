<?php

class IntentDetector {
    /**
     * Matndan foydalanuvchining maqsadini (intent) aniqlaydi.
     */
    public static function detect($message, $flights = []) {
        $msg = mb_strtolower(UzbekDictionaryHelper::normalizeText($message), 'UTF-8');

        // 1. Stoyka va Ro'yxatdan o'tish (Check-in counters)
        if (preg_match('/(stoyka|stoykalar|counter|check[-\s]?in|checkin|ro\'yxatdan\s*o\'tish|registr|стойка|стойки|стойку)/iu', $msg)) {
            return 'navigation';
        }

        // 2. Muassasalar va Aeroport ichki nuqtalari
        $facilityKeywords = [
            'hojatxona', 'tualet', 'wc', 'toilet', 'restroom', 'bathroom', 'туалет', 'санузел',
            'masjid', 'mosque', 'mesjid', 'prayer', 'musalla', 'namoz', 'namaz', 'mescit', 'cami', 'мечеть', 'молельная',
            'cip', 'vip', 'lounge', 'business', 'anor', 'anjir', 'бизнес-зал', 'лаунж', 'вип',
            'kafe', 'cafe', 'restoran', 'restaurant', 'ovqat', 'food', 'qahva', 'coffee', 'кафе', 'ресторан',
            'duty free', 'dyuti fri', 'magazin', 'shop', 'do\'kon', 'dokon', 'дюти фри', 'магазин',
            'tibbiyot', 'medpunkt', 'doktor', 'vrach', 'shifokor', 'first aid', 'medical', 'медпункт', 'аптека',
            'bagaj', 'yuk', 'baggage', 'luggage', 'багаж',
            'darvoza', 'gate', 'выход', 'дарвоза',
            'zina', 'eskavator', 'chiqish', 'kirish', 'lift'
        ];
        
        foreach ($facilityKeywords as $kw) {
            if (mb_strpos($msg, $kw) !== false) {
                return 'navigation';
            }
        }

        // 3. Navigatsiya yo'nalish so'zlari
        $navKeywords = [
            'qayerda', 'qaerda', 'qaysi joyda', 'qayerga', 'joylashgan', 
            'boriladi', 'yo\'nalish', 'yo\'l', 'yol', 'ko\'rsating', 'ko\'rsat', 
            'yuring', 'yurish', 'borish', 'borsam', 'bursam', 'bormoqchiman', 'topish',
            'где находится', 'как пройти', 'где', 'как добраться', 'where is', 'how to get'
        ];
        
        foreach ($navKeywords as $kw) {
            if (mb_strpos($msg, $kw) !== false) {
                return 'navigation';
            }
        }

        // 4. Reys raqami (Flight number)
        if (!preg_match('/\\b(darvoza|gate)\\b/u', $msg)) {
            if (preg_match('/\\b([A-Z]{2}|[A-Z]\\d|\\d[A-Z])[-\\s]*(\\d{1,4})\\b/i', $msg)) {
                return 'flight_number';
            }
            if (preg_match('/\\b(\\d{1,4})[-\\s]*(reys|parvoz)\\b/iu', $msg)) {
                return 'flight_number';
            }
        }
        
        // 5. Shahar nomi (City)
        $cityCode = UzbekDictionaryHelper::findCity($msg);
        if ($cityCode) {
            return 'flight_city';
        }
        
        // 6. Barcha reyslar ro'yxati
        if (preg_match('/\\b(reyslar|jadvali?|uchishlar|barcha reys)\\b/iu', $msg)) {
            return 'flight_list';
        }

        // 7. Faqatgina sof salomlashish bo'lsa
        if (preg_match('/^(assalomu\s*alaykum|assalom|salom|hayrli\s*kun|salomlashish|hello|hi|привет|здравствуйте)\b/ui', trim($msg))) {
            return 'greeting';
        }
        
        return 'general';
    }
}

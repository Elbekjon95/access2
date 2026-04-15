<?php

class IntentDetector {
    /**
     * Matndan foydalanuvchining maqsadini (intent) aniqlaydi.
     */
    public static function detect($message, $flights = []) {
        $msg = mb_strtolower(UzbekDictionaryHelper::normalizeText($message), 'UTF-8');
        

        // 0. Axborot yoki Narx so'rovi (Agar bular bo'lsa, navigatsiya (Fast Path) dan voz kechamiz)
        $infoKeywords = ['narxi', 'qancha', 'qanday', 'ma’lumot', 'malumot', 'haqida', 'tarif', 'price', 'cost', 'how', 'what', 'ma’lumot bering'];
        foreach ($infoKeywords as $kw) {
            if (mb_strpos($msg, $kw) !== false) {
                // Agar 'narxi' yoki 'qancha' so'zlari bo'lsa, navigatsiyani chetlab o'tamiz
                return 'general'; 
            }
        }

        // 1. Navigatsiya kalit so'zlari
        $navKeywords = ['qayerda', 'qaerda', 'qaysi joyda', 'qayerga', 'joylashgan', 
                        'boriladi', 'yo\'nalish', 'ko\'rsating', 'yuring', 'yurish', 'borish', 'borsam', 'bursand'];
        
        foreach ($navKeywords as $kw) {
            if (mb_strpos($msg, $kw) !== false) {
                return 'navigation';
            }
        }
        
        // Muassasalar (Facilities)
        $facilityKeywords = [
            'hojatxona', 'tualet', 'wc', 'zina', 'eskavator', 'chiqish', 'kirish',
            'mosque', 'masjid', 'mesjid', 'prayer', 'musalla', 'namoz', 'namaz',
            'mescit', 'cami', 'mezquita', 'mesquita', 'moschee', 'moschea',
            'мечеть', 'мечет', 'مسجد',
            'cip', 'vip', 'lounge', 'business', 'business lounge', 'vip lounge',
            'vip-zal', 'vip zal', 'cip zone', 'cip-zona', 'cip zona',
            'vi ip', 'bi ay pi', 'si ay pi', 'vi-ip', 'cip-zal', 'cip zal',
            'бизнес', 'бизнес-зал', 'бизнес zal', 'лаунж', 'вип', 'вип-зал', 'вип zal',
            'kafe', 'cafe', 'ovqat', 'food', 'oshxona', 'restoran', 'restaurant',
            'duty free', 'dutyfree', 'dyuti fri', 'magazin', 'shop', 'do\'kon', 'dokon',
            'aptek', 'pharmacy', 'dorixona', 'bank', 'atm', 'bankomat', 'valyuta', 'exchange',
            'kiosk', 'arabian', 'burger', 'coffee', 'kofe', 'choyxona'
        ];
        
        foreach ($facilityKeywords as $kw) {
            if (mb_strpos($msg, $kw) !== false) {
                return 'navigation';
            }
        }
        
        // Info desk
        if (preg_match('/\\b(info|ma\'lumot)\\b/u', $msg)) {
            if (!preg_match('/\\b(haqida|bo[’\'‘`ʼʻʹ՚´]?yicha|uchun|kerak|bering|aytib)\\b/u', $msg)) {
                if (preg_match('/\\b(info|ma\'lumot)\\s+(punkt|markaz|stol|desk|qayerda|qaerda)/u', $msg)) {
                    return 'navigation';
                }
            }
        }
        
        if (preg_match('/\\breception\\b/u', $msg)) {
            return 'navigation';
        }
        
        // Reys raqami (Flight number)
        if (!preg_match('/\\b(darvoza|gate)\\b/u', $msg)) {
            if (preg_match('/\\b([A-Z]{2}|[A-Z]\\d|\\d[A-Z])[-\\s]*(\\d{1,4})\\b/i', $msg)) {
                return 'flight_number';
            }
            if (preg_match('/\\b(\\d{1,4})[-\\s]*(reys|parvoz)\\b/iu', $msg)) {
                return 'flight_number';
            }
        }
        
        // Shahar nomi (City)
        $cityCode = UzbekDictionaryHelper::findCity($msg);
        if ($cityCode) {
            return 'flight_city';
        }
        
        // Barcha reyslar ro'yxati
        if (preg_match('/\\b(reyslar|jadvali?|uchishlar|barcha reys)\\b/iu', $msg)) {
            return 'flight_list';
        }
        
        // Salomlashish (eng oxirgi variant sifatida)
        if (preg_match('/\b(assalom|salom|hayrli kun|salomlashish)\b/ui', $message)) {
            return 'greeting';
        }
        
        return 'general';
    }
}

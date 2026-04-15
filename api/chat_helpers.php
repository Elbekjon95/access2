<?php

/**
 * Chat uchun yordamchi funksiyalar
 */

function detectLanguage($text) {
    if (empty($text)) return 'uz';
    $textLower = mb_strtolower($text, 'UTF-8');

    // 1. Script-based detection
    if (preg_match('/[СћТ“Т›ТіК»КјвЂ™]|\w\'/u', $text)) return 'uz';
    if (preg_match('/[Р°-СЏРђ-РЇС‘РЃ]/u', $text) && !preg_match('/[СћТ“Т›Ті]/u', $text)) return 'ru';
    
    // 2. Keyword-based detection
    $uzKeywords = ['qanday', 'nima', 'qachon', 'qayerda', 'qaerda', 'kim', 'kerak', 'bormi', 'salom', 'rahmat', 'reys', 'darvoza'];
    $ruKeywords = ['как', 'что', 'когда', 'где', 'кто', 'нужно', 'есть', 'привет', 'спасибо', 'рейс', 'ворота'];
    $enKeywords = ['what', 'when', 'where', 'how', 'flight', 'gate', 'hello', 'thank', 'airport'];

    $scores = [
        'uz' => scoreKeywords($textLower, $uzKeywords),
        'ru' => scoreKeywords($textLower, $ruKeywords),
        'en' => scoreKeywords($textLower, $enKeywords)
    ];

    arsort($scores);
    reset($scores);
    $bestLang = key($scores);
    
    return $scores[$bestLang] > 0 ? $bestLang : 'uz';
}

function scoreKeywords($text, $keywords) {
    $score = 0;
    foreach ($keywords as $kw) {
        if (mb_strpos($text, $kw) !== false) $score++;
    }
    return $score;
}

function normalizeLangCode($lang) {
    $allowed = ['uz', 'ru', 'en', 'es', 'zh', 'hi', 'ar', 'bn', 'pt', 'ja', 'de', 'fr', 'it', 'ko', 'tr', 'ur', 'tg', 'ky', 'kk', 'tk'];
    return in_array($lang, $allowed) ? $lang : 'uz';
}

function tByLang($lang, $uz, $en, $ru) {
    if ($lang === 'uz') return $uz;
    if ($lang === 'ru') return $ru;
    return $en;
}

function cleanUzbekResponse($text) {
    $text = UzbekDictionaryHelper::normalizeQuotes($text);
    // Masalan, ortiqcha joylarni olib tashlash yoki maxsus belgilarni tozalash
    return trim($text);
}

function normalizeUzText($text) {
    return UzbekDictionaryHelper::normalizeText($text);
}

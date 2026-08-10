<?php

class UzbekDictionaryHelper {
    private static $dictionary = null;
    private static $wordList = null;
    
    public static function loadDictionary() {
        if (self::$dictionary === null) {
            $path = __DIR__ . '/../data/language/uzbek_dictionary.json';
            if (file_exists($path)) {
                $json = file_get_contents($path);
                self::$dictionary = json_decode($json, true);
            } else {
                self::$dictionary = [];
            }
        }
        return self::$dictionary;
    }
    
    private static $wordLookup = null;

    private static function loadWordList() {
        if (self::$wordList === null) {
            $path = __DIR__ . '/../data/language/uzbek_words.txt';
            if (file_exists($path)) {
                $content = file_get_contents($path);
                self::$wordList = array_filter(array_map('trim', explode("\n", $content)));
                self::$wordLookup = array_flip(self::$wordList); // Tezkor qidiruv uchun
            } else {
                self::$wordList = [];
                self::$wordLookup = [];
            }
        }
        return self::$wordList;
    }

    public static function findClosestWord($word, $maxDistance = 2) {
        $wordList = self::loadWordList();
        if (isset(self::$wordLookup[$word])) return $word;

        $bestMatch = null;
        $shortestDistance = $maxDistance + 1;

        foreach ($wordList as $dictWord) {
            if (abs(mb_strlen($word) - mb_strlen($dictWord)) > $maxDistance) continue;

            $dist = levenshtein($word, $dictWord);
            if ($dist === 0) return $dictWord;

            if ($dist < $shortestDistance) {
                $shortestDistance = $dist;
                $bestMatch = $dictWord;
            }
        }

        return ($shortestDistance <= $maxDistance) ? $bestMatch : null;
    }
    
    public static function findWord($word) {
        $dict = self::loadDictionary();
        $word = mb_strtolower(trim($word), 'UTF-8');
        
        $categories = ['common_words', 'airport_terminology', 'location_words', 
                      'facilities', 'question_words', 'time_words', 'action_verbs', 
                      'status_words', 'countries_cities'];
        
        foreach ($categories as $cat) {
            if (isset($dict[$cat][$word])) {
                return [
                    'word' => $word,
                    'translation' => $dict[$cat][$word],
                    'category' => $cat,
                    'found' => true
                ];
            }
        }
        
        return ['found' => false, 'word' => $word];
    }
    
    public static function normalizeQuotes($text) {
        if (empty($text)) return $text;
        return str_replace(
            [
                'вЂ', 'вЂ™', 'К»', 'Кј', '`',
                '‘', '’', 'ʼ', 'ʻ', 'ʹ', '՚', '´'
            ],
            "'",
            $text
        );
    }

    public static function normalizeText($text) {
        $dict = self::loadDictionary();
        
        if (empty($text)) return $text;
        
        $text = trim($text);
        $normalized = mb_strtolower($text, 'UTF-8');
        $normalized = self::normalizeQuotes($normalized);
        $normalized = str_replace([',', '.', '!', '?', ';', ':'], ' ', $normalized);
        $normalized = preg_replace('/\s+/u', ' ', $normalized);
        $normalized = trim($normalized);

        $phrases = [
            'hohjit huni' => 'hojatxona',
            'hojat huni' => 'hojatxona',
            'qayirda joylishke' => 'qayerda joylashgan',
            'qaerda joylishke' => 'qayerda joylashgan'
        ];

        foreach ($phrases as $wrong => $correct) {
            $normalized = str_replace($wrong, $correct, $normalized);
        }
        
        if (isset($dict['synonyms'])) {
            foreach ($dict['synonyms'] as $synonym => $correct) {
                $normalized = preg_replace('/\b' . preg_quote($synonym, '/') . '\b/u', $correct, $normalized);
            }
        }
        
        if (isset($dict['corrections'])) {
            foreach ($dict['corrections'] as $wrong => $correct) {
                $normalized = preg_replace('/\b' . preg_quote($wrong, '/') . '\b/u', $correct, $normalized);
            }
        }

        $words = explode(' ', $normalized);
        $correctedWords = [];
        $wordList = self::loadWordList();

        foreach ($words as $w) {
            if (mb_strlen($w) < 3) {
                $correctedWords[] = $w;
                continue;
            }

            // 1. Lug'atda bormi?
            if (isset(self::$wordLookup[$w])) {
                $correctedWords[] = $w;
                continue;
            }

            // 2. Shahar nomi yoki IATA kodimi? (Bularni o'zgartirmaymiz)
            $isPotentialCity = false;
            if (isset($dict['iata_map'])) {
                foreach ($dict['iata_map'] as $code => $aliases) {
                    if (in_array(mb_strtolower($code), [$w]) || 
                        in_array(mb_strtolower($w), array_map('mb_strtolower', $aliases))) {
                        $isPotentialCity = true;
                        break;
                    }
                }
            }

            if ($isPotentialCity) {
                $correctedWords[] = $w;
                continue;
            }

            // 3. Imlo tuzatish (Spell check) - faqat juda o'xshash so'zlar uchun (maxDistance = 1)
            $closest = self::findClosestWord($w, 1); 
            if ($closest) {
                error_log("💡 SPELL CHECK: '$w' -> '$closest'");
                $correctedWords[] = $closest;
            } else {
                $correctedWords[] = $w;
            }
        }
        $normalized = implode(' ', $correctedWords);

        $normalized = preg_replace('/\bhojat\s+xona\b/u', 'hojatxona', $normalized);
        
        return trim($normalized);
    }
    
    public static function parseUzNumber($text) {
        $numbers = [
            'nol' => 0, 'bir' => 1, 'ikki' => 2, 'uch' => 3, 'tort' => 4, 'turt' => 4, 'tor' => 4, 'besh' => 5, 
            'olti' => 6, 'yetti' => 7, 'yitti' => 7, 'sakkiz' => 8, 'toqqiz' => 9, 'tuqqiz' => 9, 'on' => 10,
            'yigirma' => 20, 'ottiz' => 30, 'uttiz' => 30, 'qirq' => 40, 'ellik' => 50, 'elik' => 50,
            'oltmish' => 60, 'yetmish' => 70, 'yettimish' => 70, 'sakson' => 80, 'sekson' => 80, 'toqson' => 90, 'tuqson' => 90,
            'yuz' => 100, 'yut' => 100, 'ming' => 1000, 'min' => 1000
        ];

        $text = self::normalizeQuotes($text);
        $words = preg_split('/\s+/u', mb_strtolower($text, 'UTF-8'));
        $groups = [];
        $groupTotal = 0;
        $groupCurrent = 0;
        $tokenCount = 0;
        $active = false;

        foreach ($words as $word) {
            if ($word === '') continue;
            $cleanWord = preg_replace('/(inchi|nchi|chi|ta|si|dan|da|ga|ni|ning|inchi)+$/u', '', $word);
            $cleanWord = preg_replace("/['’‘`ʼʻʹ՚´]/u", "", $cleanWord); // Match clean mapping (tort instead of to'rt)
            $cleanWord = preg_replace('/[^\p{L}\p{N}]/u', '', $cleanWord);

            if ($cleanWord !== '' && preg_match('/^\d{1,4}$/', $cleanWord)) {
                if ($active && $tokenCount > 0) {
                    $groups[] = ['value' => $groupTotal + $groupCurrent, 'tokens' => $tokenCount];
                    $groupTotal = 0;
                    $groupCurrent = 0;
                    $tokenCount = 0;
                    $active = false;
                }
                $groups[] = ['value' => (int)$cleanWord, 'tokens' => 1];
                continue;
            }

            if (isset($numbers[$cleanWord])) {
                $val = $numbers[$cleanWord];
                $active = true;

                if ($val == 100) {
                    $groupCurrent = ($groupCurrent == 0 ? 1 : $groupCurrent) * 100;
                } elseif ($val == 1000) {
                    $groupTotal += ($groupCurrent == 0 ? 1 : $groupCurrent) * 1000;
                    $groupCurrent = 0;
                } else {
                    $groupCurrent += $val;
                }
                $tokenCount++;
            } else {
                if ($active && $tokenCount > 0) {
                    $groups[] = ['value' => $groupTotal + $groupCurrent, 'tokens' => $tokenCount];
                    $groupTotal = 0;
                    $groupCurrent = 0;
                    $tokenCount = 0;
                    $active = false;
                }
            }
        }

        if ($active && $tokenCount > 0) {
            $groups[] = ['value' => $groupTotal + $groupCurrent, 'tokens' => $tokenCount];
        }

        if (empty($groups)) {
            return null;
        }

        usort($groups, function ($a, $b) {
            if ($a['tokens'] === $b['tokens']) {
                return $b['value'] <=> $a['value'];
            }
            return $b['tokens'] <=> $a['tokens'];
        });

        return $groups[0]['value'];
    }

    public static function findCity($text) {
        if (empty($text)) return null;
        $dict = self::loadDictionary();
        $text = self::normalizeQuotes(mb_strtolower($text, 'UTF-8'));
        
        if (preg_match('/\((.*?)\)/', $text, $m)) {
            $code = strtoupper(trim($m[1]));
            if (isset($dict['iata_map'][$code])) return $code;
        }

        if (preg_match('/\b([A-Z]{3})\b/', strtoupper($text), $m)) {
            $code = $m[1];
            if (isset($dict['iata_map'][$code])) return $code;
        }

        $words = explode(' ', str_replace([',', '.', '!', '?', '-', '_'], ' ', $text));
        $cities = $dict['iata_map'] ?? [];
        
        $suffixes = ['ga', 'dan', 'da', 'ni', 'ning', 'lik', 'cha', 'gi', 'lari'];
        
        $exclusions = ['qayerda', 'qaerda', 'hojatxona', 'tualet', 'darvoza', 'gate', 'salom', 'rahmat', 'qaysi', 'nima', 'qanday', 'reception', 'info', 'zina', 'eskavator', 'reys', 'bir', 'ikki', 'uch', 'to\'rt', 'besh', 'olti', 'yetti', 'sakkiz', 'to\'qqiz', 'o\'n', 'yuz', 'ming', 'masjid', 'mosque', 'mesjid', 'prayer', 'musalla', 'namoz', 'namaz', 'mescit', 'cami', 'mezquita', 'mesquita', 'moschee', 'moschea', 'мечет', 'мечеть', 'مسجد'];
        
        $bestMatch = null;
        $minDistance = 3;

        foreach ($words as $word) {
            $word = trim($word);
            if (mb_strlen($word) < 3 || in_array($word, $exclusions)) continue;
            
            $stems = [$word];
            foreach ($suffixes as $s) {
                if (mb_substr($word, -mb_strlen($s)) === $s) {
                    $stems[] = mb_substr($word, 0, -mb_strlen($s));
                }
            }
            
            foreach ($stems as $stem) {
                if (in_array($stem, $exclusions)) continue;
                $stemLen = mb_strlen($stem, 'UTF-8');
                
                foreach ($cities as $code => $aliases) {
                    foreach ($aliases as $alias) {
                        $lowerAlias = mb_strtolower($alias, 'UTF-8');
                        if ($lowerAlias === $stem) return $code;
                        
                        if ($stemLen >= 4) { // Only fuzzy match for 4+ letters
                            $allowedDist = ($stemLen <= 5) ? 1 : 2;
                            $dist = levenshtein($stem, $lowerAlias);
                            if ($dist <= $allowedDist && $dist < $minDistance) {
                                $minDistance = $dist;
                                $bestMatch = $code;
                            }
                        }
                    }
                }
            }
        }
        
        if ($bestMatch) return $bestMatch;

        $dbMatch = self::findCityInDb($text, $suffixes, $exclusions);
        return $dbMatch;
    }

    private static function findCityInDb($text, $suffixes, $exclusions) {
        if (!function_exists('getDbConnection')) return null;

        $cacheFile = __DIR__ . '/../data/airports_list_cache.json';
        $airportsList = [];

        if (file_exists($cacheFile)) {
            $airportsList = json_decode(@file_get_contents($cacheFile), true);
        }

        if (empty($airportsList)) {
            try {
                $db = getDbConnection();
                $rawAirports = $db->find('airports', [
                    'iata_code' => ['$exists' => true, '$ne' => '']
                ], [
                    'sort' => ['scheduled_service' => -1]
                ]);
                $airportsList = [];
                foreach ($rawAirports as $ap) {
                    $airportsList[] = [
                        'iata_code' => $ap['iata_code'] ?? '',
                        'city' => mb_strtolower($ap['city'] ?? '', 'UTF-8'),
                        'name' => mb_strtolower($ap['name'] ?? '', 'UTF-8')
                    ];
                }
                @file_put_contents($cacheFile, json_encode($airportsList));
            } catch (Throwable $e) {
                return null;
            }
        }

        if (preg_match('/\b([A-Z]{3})\b/', strtoupper($text), $m)) {
            $code = $m[1];
            foreach ($airportsList as $ap) {
                if (strtoupper($ap['iata_code']) === $code) return $code;
            }
        }

        $words = explode(' ', str_replace([',', '.', '!', '?', '-', '_'], ' ', $text));
        foreach ($words as $word) {
            $word = mb_strtolower(trim($word), 'UTF-8');
            if (mb_strlen($word) < 3 || in_array($word, $exclusions, true)) continue;

            $stems = [$word];
            foreach ($suffixes as $s) {
                if (mb_substr($word, -mb_strlen($s)) === $s) {
                    $stems[] = mb_substr($word, 0, -mb_strlen($s));
                }
            }

            foreach ($stems as $stem) {
                if (mb_strlen($stem) < 3 || in_array($stem, $exclusions, true)) continue;

                foreach ($airportsList as $ap) {
                    if (mb_strpos($ap['city'] ?? '', $stem) !== false || mb_strpos($ap['name'] ?? '', $stem) !== false) {
                        return strtoupper($ap['iata_code']);
                    }
                }
            }
        }

        return null;
    }


    public static function detectUzbek($text, $previousMessages = []) {
        if (empty($text)) return false;
        
        $text = mb_strtolower(trim($text), 'UTF-8');
        $score = 0;
        
        if (preg_match("/(o['К»вЂ™]|g['К»вЂ™]|sh['К»вЂ™]|ch['К»вЂ™])/iu", $text)) {
            $score += 5;
            error_log("рџ”Ќ UZBEK DETECT - Apostrophe found: +5");
        }
        
        if (preg_match('/[Т’Т“ТљТ›РЋСћТІТі]/u', $text)) {
            $score += 5;
            error_log("рџ”Ќ UZBEK DETECT - Cyrillic Uzbek: +5");
        }
        
        $wordList = self::loadWordList();
        $words = preg_split('/\s+/u', $text);
        $uzbekWords = 0;
        $totalWords = 0;
        
        foreach ($words as $word) {
            $word = trim($word, '.,!?;:()[]{}');
            if (mb_strlen($word, 'UTF-8') < 2) continue;
            $totalWords++;
            
            if (in_array($word, $wordList)) {
                $uzbekWords++;
            }
        }
        
        if ($totalWords > 0) {
            $matchRatio = $uzbekWords / $totalWords;
            $matchScore = round($matchRatio * 10); // Max 10 ball
            $score += $matchScore;
            error_log("рџ”Ќ UZBEK DETECT - Dict match: $uzbekWords/$totalWords words (+$matchScore)");
        }
        
        $suffixes = ['ga', 'da', 'dan', 'ni', 'ning', 'lar', 'miz', 'siz', 'man', 'san'];
        $suffixMatches = 0;
        foreach ($suffixes as $suffix) {
            if (preg_match('/\w{2,}' . $suffix . '\b/u', $text)) {
                $suffixMatches++;
            }
        }
        if ($suffixMatches > 0) {
            $suffixScore = min($suffixMatches * 2, 6); // Max 6 ball
            $score += $suffixScore;
            error_log("рџ”Ќ UZBEK DETECT - Suffixes: $suffixMatches (+$suffixScore)");
        }
        
        if (preg_match('/^(qanday|qayerda|qaerda|qachon|nima|kim|qancha)/iu', $text)) {
            $score += 3;
            error_log("рџ”Ќ UZBEK DETECT - Question structure: +3");
        }
        
        $airportTerms = ['reys', 'uchish', 'kelish', 'jonash', 'jo\'nash', 'darvoza', 'gate', 
                         'hojatxona', 'tualet', 'terminal', 'bagaj', 'chipta', 'parvoz'];
        foreach ($airportTerms as $term) {
            if (mb_strpos($text, $term) !== false) {
                $score += 2;
                error_log("рџ”Ќ UZBEK DETECT - Airport term '$term': +2");
                break; // Faqat bitta
            }
        }
        
        if (!empty($previousMessages)) {
            $uzbekCount = 0;
            foreach (array_slice($previousMessages, -3) as $prevMsg) {
                if (preg_match("/(o[''К»]|g[''К»])/iu", $prevMsg) || 
                    preg_match('/\b(qanday|qayerda|salom|rahmat)\b/iu', $prevMsg)) {
                    $uzbekCount++;
                }
            }
            if ($uzbekCount >= 2) {
                $score += 2;
                error_log("рџ”Ќ UZBEK DETECT - Context (previous messages): +2");
            }
        }
        
        $isUzbek = $score >= 5; // 5+ ball => O'zbek
        error_log("рџЋЇ UZBEK DETECT FINAL - Score: $score, Result: " . ($isUzbek ? "O'ZBEK" : "BOSHQA"));
        
        return $isUzbek;
    }
    
    public static function isValidWord($word) {
        $wordList = self::loadWordList();
        $word = mb_strtolower(trim($word), 'UTF-8');
        return in_array($word, $wordList);
    }
    
    public static function extractUzbekWords($text) {
        $wordList = self::loadWordList();
        $words = preg_split('/\s+/u', mb_strtolower($text, 'UTF-8'));
        $uzbekWords = [];
        
        foreach ($words as $word) {
            $word = trim($word, '.,!?;:()[]{}');
            if (in_array($word, $wordList)) {
                $uzbekWords[] = $word;
            }
        }
        
        return $uzbekWords;
    }
    
    public static function categorizeText($text) {
        $dict = self::loadDictionary();
        $words = preg_split('/\s+/u', mb_strtolower($text, 'UTF-8'));
        
        $categories = [
            'question' => false,
            'airport' => false,
            'location' => false,
            'time' => false,
            'facility' => false
        ];
        
        foreach ($words as $word) {
            $word = trim($word, '.,!?;:()[]{}');
            
            if (isset($dict['question_words'][$word])) {
                $categories['question'] = true;
            }
            if (isset($dict['airport_terminology'][$word])) {
                $categories['airport'] = true;
            }
            if (isset($dict['location_words'][$word])) {
                $categories['location'] = true;
            }
            if (isset($dict['time_words'][$word])) {
                $categories['time'] = true;
            }
            if (isset($dict['facilities'][$word])) {
                $categories['facility'] = true;
            }
        }
        
        return $categories;
    }
    
    public static function prepareForAI($text) {
        $normalized = self::normalizeText($text);
        
        $normalized = preg_replace('/\s+/u', ' ', $normalized);
        $normalized = trim($normalized);
        
        $categories = self::categorizeText($normalized);
        
        return [
            'original' => $text,
            'normalized' => $normalized,
            'categories' => $categories,
            'is_uzbek' => self::detectUzbek($text),
            'uzbek_words' => self::extractUzbekWords($text)
        ];
    }
    
    public static function getContextForAI($text) {
        $analysis = self::prepareForAI($text);
        
        $context = "O'ZBEKCHA TARJIMA:\n";
        $uzbekWords = $analysis['uzbek_words'];
        
        if (!empty($uzbekWords)) {
            $context .= "Aniqlangan o'zbek so'zlar: " . implode(', ', array_unique($uzbekWords)) . "\n";
        }
        
        $context .= "\nKATEGORIYALAR:\n";
        foreach ($analysis['categories'] as $cat => $value) {
            if ($value) {
                $context .= "- " . ucfirst($cat) . " mavzusi\n";
            }
        }
        
        return $context;
    }
}

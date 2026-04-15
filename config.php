<?php
/**
 * ACCSESS - Konfiguratsiya fayli (Soddalashtirilgan)
 * Faqat Gemini va Uzbek Voice sozlamalarini ta'minlaydi.
 */

// .env faylini yuklash uchun sodda funksiya
function loadEnv($path) {
    if (!file_exists($path)) return;
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        $parts = explode('=', $line, 2);
        if (count($parts) === 2) {
            $name = trim($parts[0]);
            $value = trim($parts[1]);
            if (!array_key_exists($name, $_SERVER) && !array_key_exists($name, $_ENV)) {
                putenv(sprintf('%s=%s', $name, $value));
                $_ENV[$name] = $value;
                $_SERVER[$name] = $value;
            }
        }
    }
}

loadEnv(__DIR__ . '/.env');

/**
 * Environment o'zgaruvchisini olish uchun yordamchi
 */
function env($key, $default = null) {
    $value = getenv($key);
    return $value !== false ? $value : $default;
}

// Xatoliklar sozlamalari
ini_set('display_errors', 0);
error_reporting(E_ALL);
ini_set('log_errors', 1);

// Ma'lumotlar bazasi sozlamalari
define('DB_HOST', env('DB_HOST', 'localhost'));
define('DB_NAME', env('DB_NAME', 'acsess4'));
define('DB_USER', env('DB_USER', 'root'));
define('DB_PASS', env('DB_PASS', ''));

# Gemini API Sozlamalari
define('GEMINI_API_KEY', env('GEMINI_API_KEY'));
define('GEMINI_MODEL', env('GEMINI_MODEL', 'gemini-2.0-flash-exp'));
define('GEMINI_TTS_MODEL', env('GEMINI_TTS_MODEL', 'gemini-2.0-flash-exp'));

// Uzbek Voice (STT & TTS)
define('UZBEKVOICE_API_KEY', env('UZBEKVOICE_API_KEY'));

// Groq Whisper Large (ko'p tilli STT)
define('GROQ_API_KEY', env('GROQ_API_KEY'));

// Tashqi API va Botlar
define('FLIGHT_API_URL', env('FLIGHT_API_URL'));
define('TELEGRAM_BOT_TOKEN', env('TELEGRAM_BOT_TOKEN'));
define('TELEGRAM_CHAT_ID', env('TELEGRAM_CHAT_ID'));
define('COMPLAINT_EMAIL', env('COMPLAINT_EMAIL', 'admin@example.com'));
define('OPENWEATHER_API_KEY', env('OPENWEATHER_API_KEY', ''));

// OCR va Tizim doimiylari
define('TESSERACT_PATH', env('TESSERACT_PATH', 'C:/Program Files/Tesseract-OCR/tesseract.exe'));
define('USE_AI_ENGINE', 'gemini');

/**
 * Ma'lumotlar bazasiga ulanishni qaytaradi.
 */
function getDbConnection() {
    static $pdo = null;
    if ($pdo !== null) return $pdo;

    try {
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
        $pdo = new PDO($dsn, DB_USER, DB_PASS);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        return $pdo;
    } catch (PDOException $e) {
        if (strpos($_SERVER['REQUEST_URI'] ?? '', '/api/') !== false) {
            header('Content-Type: application/json');
            echo json_encode(['error' => "Baza ulanish xatosi"]);
            exit;
        }
        die("Ma'lumotlar bazasiga ulanib bo'lmadi.");
    }
}

// Global PDO ob'ekti
$pdo = getDbConnection();

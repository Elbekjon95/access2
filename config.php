<?php
/**
 * ACSESS - Konfiguratsiya fayli
 * .env faylidan sozlamalarni yuklaydi va DB ulanishini ta'minlaydi.
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
define('MONGODB_URI', env('MONGODB_URI', 'mongodb://127.0.0.1:27017'));
define('DB_NAME', env('DB_NAME', 'acsess4'));
define('DB_HOST', env('DB_HOST', '127.0.0.1'));

require_once __DIR__ . '/api/classes/MongoDB.php';

// API Kalitlari
define('GROQ_API_KEY', env('GROQ_API_KEY'));
define('OPENROUTER_API_KEY', env('OPENROUTER_API_KEY'));
define('OPENROUTER_MODEL', env('OPENROUTER_MODEL', 'arcee-ai/trinity-large-preview:free'));
define('OPENWEATHER_API_KEY', env('OPENWEATHER_API_KEY', ''));
define('GEMINI_API_KEY', env('GEMINI_API_KEY'));
define('GEMINI_MODEL', env('GEMINI_MODEL', 'gemini-1.5-flash'));
define('IFLYTEK_APPID', env('IFLYTEK_APPID'));
define('IFLYTEK_API_SECRET', env('IFLYTEK_API_SECRET'));
define('IFLYTEK_API_KEY', env('IFLYTEK_API_KEY'));
define('UZBEKVOICE_API_KEY', env('UZBEKVOICE_API_KEY'));

// Tashqi API va Botlar
define('FLIGHT_API_URL', env('FLIGHT_API_URL'));
define('TELEGRAM_BOT_TOKEN', env('TELEGRAM_BOT_TOKEN'));
define('TELEGRAM_CHAT_ID', env('TELEGRAM_CHAT_ID'));
define('COMPLAINT_EMAIL', env('COMPLAINT_EMAIL', 'admin@example.com'));

// AI Sozlamalari
define('OLLAMA_API_URL', env('OLLAMA_API_URL', 'http://localhost:11434/api/generate'));
define('OLLAMA_CHAT_URL', env('OLLAMA_CHAT_URL', 'http://localhost:11434/api/chat'));
define('OLLAMA_MODEL', env('OLLAMA_MODEL', 'qwen2.5:14b'));
define('USE_AI_ENGINE', env('USE_AI_ENGINE', 'openrouter'));

// Boshqa doimiylar
define('TESSERACT_PATH', env('TESSERACT_PATH', 'C:/Program Files/Tesseract-OCR/tesseract.exe'));
define('COMPLAINT_SMTP_MODE', 'direct');
define('COMPLAINT_SMTP_HOSTS', 'post.uzairports.com,mail.uzairports.com');
define('COMPLAINT_SMTP_PORT', 25);
define('COMPLAINT_SMTP_TIMEOUT', 15);
define('COMPLAINT_SMTP_HELO', 'fro.local');
define('COMPLAINT_FROM_EMAIL', 'kiosk@uzairports.com');
define('COMPLAINT_FROM_NAME', 'TAS Kiosk');

/**
 * Ma'lumotlar bazasiga (MongoDB) ulanishni qaytaradi.
 */
function getDbConnection() {
    static $db = null;
    if ($db !== null) return $db;

    try {
        $db = new MongoDBDatabase(MONGODB_URI, DB_NAME);
        return $db;
    } catch (Throwable $e) {
        // API so'rovlar uchun JSON xato qaytaramiz
        if (strpos($_SERVER['REQUEST_URI'] ?? '', '/api/') !== false) {
            header('Content-Type: application/json');
            echo json_encode(['error' => "Baza ulanish xatosi (Database Error): " . $e->getMessage()]);
            exit;
        }
        die("MongoDB ma'lumotlar bazasiga ulanib bo'lmadi: " . $e->getMessage());
    }
}

// Global DB ob'ekti (ham $db, ham moslik uchun $pdo)
$db = getDbConnection();
$pdo = $db;

/**
 * Sessiyalarni xavfsiz boshlash
 */
function secureSessionStart() {
    if (session_status() === PHP_SESSION_NONE) {
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'domain' => '',
            'secure' => isset($_SERVER['HTTPS']),
            'httponly' => true,
            'samesite' => 'Lax'
        ]);
        session_start();
    }
}

/**
 * CSRF token yaratish yoki olish
 */
function getCsrfToken() {
    secureSessionStart();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * CSRF token-ni tekshirish
 */
function verifyCsrfToken($token) {
    secureSessionStart();
    if (empty($token) || empty($_SESSION['csrf_token'])) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

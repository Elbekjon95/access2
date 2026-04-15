<?php
require_once '../config.php';

header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

function normalizeText($value, $fallback = '') {
    $v = trim((string)$value);
    return $v === '' ? $fallback : $v;
}

function detectAudioExtension($mime, $originalName) {
    $map = [
        'audio/wav' => 'wav',
        'audio/x-wav' => 'wav',
        'audio/webm' => 'webm',
        'audio/ogg' => 'ogg',
        'audio/mpeg' => 'mp3',
        'audio/mp3' => 'mp3',
        'application/octet-stream' => pathinfo((string)$originalName, PATHINFO_EXTENSION) ?: 'wav',
    ];
    $m = strtolower(trim((string)$mime));
    return $map[$m] ?? (pathinfo((string)$originalName, PATHINFO_EXTENSION) ?: 'wav');
}

function detectAudioMimeByFilename($name) {
    $ext = strtolower(pathinfo((string)$name, PATHINFO_EXTENSION));
    if ($ext === 'webm') return 'audio/webm';
    if ($ext === 'ogg') return 'audio/ogg';
    if ($ext === 'mp3') return 'audio/mpeg';
    return 'audio/wav';
}

function encodeHeaderValue($text) {
    $text = (string)$text;
    if ($text === '') return '';
    if (!preg_match('/[^\x20-\x7E]/', $text)) return $text;
    return '=?UTF-8?B?' . base64_encode($text) . '?=';
}

function normalizeCrlf($text) {
    return str_replace(["\r\n", "\r", "\n"], "\r\n", (string)$text);
}

function buildMimeMailData($to, $subject, $body, $attachmentPath = null, $attachmentName = null) {
    $fromEmail = defined('COMPLAINT_FROM_EMAIL') ? COMPLAINT_FROM_EMAIL : 'kiosk@uzairports.com';
    $fromName = defined('COMPLAINT_FROM_NAME') ? COMPLAINT_FROM_NAME : 'TAS Kiosk';
    $safeFromName = str_replace(['"', "\r", "\n"], '', $fromName);

    $headers = [];
    $headers[] = 'Date: ' . date('r');
    $headers[] = 'From: ' . encodeHeaderValue($safeFromName) . " <{$fromEmail}>";
    $headers[] = "To: <{$to}>";
    $headers[] = 'Subject: ' . encodeHeaderValue($subject);
    $headers[] = 'MIME-Version: 1.0';
    $headers[] = 'X-Mailer: TAS-Kiosk Complaint Mailer';

    $plainBody = normalizeCrlf($body);

    if ($attachmentPath && is_file($attachmentPath)) {
        $boundary = '=_KIOSK_' . bin2hex(random_bytes(8));
        $headers[] = 'Content-Type: multipart/mixed; boundary="' . $boundary . '"';

        $message = '';
        $message .= '--' . $boundary . "\r\n";
        $message .= "Content-Type: text/plain; charset=UTF-8\r\n";
        $message .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
        $message .= $plainBody . "\r\n\r\n";

        $fileData = @file_get_contents($attachmentPath);
        if ($fileData !== false) {
            $safeName = $attachmentName ?: basename($attachmentPath);
            $safeName = str_replace(["\r", "\n", '"'], '', $safeName);
            $attachmentMime = detectAudioMimeByFilename($safeName);
            $message .= '--' . $boundary . "\r\n";
            $message .= 'Content-Type: ' . $attachmentMime . '; name="' . $safeName . '"' . "\r\n";
            $message .= 'Content-Disposition: attachment; filename="' . $safeName . '"' . "\r\n";
            $message .= "Content-Transfer-Encoding: base64\r\n\r\n";
            $message .= chunk_split(base64_encode($fileData), 76, "\r\n");
            $message .= "\r\n";
        }

        $message .= '--' . $boundary . "--\r\n";
        return implode("\r\n", $headers) . "\r\n\r\n" . $message;
    }

    $headers[] = 'Content-Type: text/plain; charset=UTF-8';
    $headers[] = 'Content-Transfer-Encoding: 8bit';
    return implode("\r\n", $headers) . "\r\n\r\n" . $plainBody . "\r\n";
}

function smtpReadResponse($socket) {
    $response = '';
    while (($line = fgets($socket, 515)) !== false) {
        $response .= $line;
        if (strlen($line) < 4) break;
        if (substr($line, 3, 1) === ' ') break;
    }
    if ($response === '') return [0, 'No response from SMTP server'];
    return [(int)substr($response, 0, 3), trim($response)];
}

function smtpCommand($socket, $command, $allowedCodes) {
    @fwrite($socket, $command . "\r\n");
    [$code, $response] = smtpReadResponse($socket);
    if (!in_array($code, $allowedCodes, true)) return [false, $response];
    return [true, $response];
}

function smtpData($socket, $rawData) {
    $prepared = normalizeCrlf($rawData);
    $prepared = preg_replace('/^\./m', '..', $prepared);
    @fwrite($socket, $prepared . "\r\n.\r\n");
    [$code, $response] = smtpReadResponse($socket);
    if ($code !== 250) return [false, $response];
    return [true, $response];
}

function resolveSmtpHosts($to) {
    $hosts = [];

    $atPos = strrpos($to, '@');
    $domain = $atPos !== false ? substr($to, $atPos + 1) : '';
    if ($domain !== '' && function_exists('dns_get_record')) {
        $mx = @dns_get_record($domain, DNS_MX);
        if (is_array($mx) && $mx) {
            usort($mx, function ($a, $b) {
                return ($a['pri'] ?? 9999) <=> ($b['pri'] ?? 9999);
            });
            foreach ($mx as $row) {
                $target = rtrim((string)($row['target'] ?? ''), '.');
                if ($target !== '') $hosts[] = $target;
            }
        }
    }

    $configured = defined('COMPLAINT_SMTP_HOSTS') ? COMPLAINT_SMTP_HOSTS : '';
    foreach (explode(',', (string)$configured) as $h) {
        $h = trim($h);
        if ($h !== '') $hosts[] = $h;
    }

    $unique = [];
    foreach ($hosts as $host) {
        $key = strtolower($host);
        if (!isset($unique[$key])) {
            $unique[$key] = $host;
        }
    }
    return array_values($unique);
}

function sendViaDirectSmtp($to, $rawMail) {
    $hosts = resolveSmtpHosts($to);
    if (!$hosts) return [false, 'SMTP host topilmadi', 'direct-smtp'];

    $fromEmail = defined('COMPLAINT_FROM_EMAIL') ? COMPLAINT_FROM_EMAIL : 'kiosk@uzairports.com';
    $helo = defined('COMPLAINT_SMTP_HELO') ? COMPLAINT_SMTP_HELO : 'localhost';
    $port = defined('COMPLAINT_SMTP_PORT') ? (int)COMPLAINT_SMTP_PORT : 25;
    $timeout = defined('COMPLAINT_SMTP_TIMEOUT') ? (int)COMPLAINT_SMTP_TIMEOUT : 15;
    $errors = [];

    foreach ($hosts as $host) {
        $errno = 0;
        $errstr = '';
        $socket = @fsockopen($host, $port, $errno, $errstr, $timeout);
        if (!$socket) {
            $errors[] = "{$host}: connect failed ({$errno}) {$errstr}";
            continue;
        }

        stream_set_timeout($socket, $timeout);

        [$code, $greeting] = smtpReadResponse($socket);
        if ($code !== 220) {
            $errors[] = "{$host}: bad greeting: {$greeting}";
            @fclose($socket);
            continue;
        }

        [$okEhlo, $respEhlo] = smtpCommand($socket, "EHLO {$helo}", [250]);
        if (!$okEhlo) {
            [$okHelo, $respHelo] = smtpCommand($socket, "HELO {$helo}", [250]);
            if (!$okHelo) {
                $errors[] = "{$host}: EHLO/HELO failed: {$respEhlo} | {$respHelo}";
                @fclose($socket);
                continue;
            }
        }

        [$okMail, $respMail] = smtpCommand($socket, "MAIL FROM:<{$fromEmail}>", [250]);
        if (!$okMail) {
            $errors[] = "{$host}: MAIL FROM failed: {$respMail}";
            @fclose($socket);
            continue;
        }

        [$okRcpt, $respRcpt] = smtpCommand($socket, "RCPT TO:<{$to}>", [250, 251]);
        if (!$okRcpt) {
            $errors[] = "{$host}: RCPT TO failed: {$respRcpt}";
            @fclose($socket);
            continue;
        }

        [$okData, $respData] = smtpCommand($socket, 'DATA', [354]);
        if (!$okData) {
            $errors[] = "{$host}: DATA failed: {$respData}";
            @fclose($socket);
            continue;
        }

        [$okBody, $respBody] = smtpData($socket, $rawMail);
        if (!$okBody) {
            $errors[] = "{$host}: message rejected: {$respBody}";
            @fclose($socket);
            continue;
        }

        @smtpCommand($socket, 'QUIT', [221, 250]);
        @fclose($socket);
        return [true, "{$host}: accepted ({$respBody})", "direct-smtp:{$host}"];
    }

    return [false, implode(' | ', $errors), 'direct-smtp'];
}

function sendComplaintEmail($to, $subject, $body, $attachmentPath = null, $attachmentName = null) {
    $to = trim((string)$to);
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
        return [false, 'Recipient email invalid', 'none'];
    }

    $mode = defined('COMPLAINT_SMTP_MODE') ? strtolower((string)COMPLAINT_SMTP_MODE) : 'direct';
    $rawMail = buildMimeMailData($to, (string)$subject, (string)$body, $attachmentPath, $attachmentName);

    if ($mode === 'localmail') {
        $ok = @mail($to, (string)$subject, (string)$body, 'From: ' . (defined('COMPLAINT_FROM_EMAIL') ? COMPLAINT_FROM_EMAIL : 'kiosk@uzairports.com'));
        return [(bool)$ok, $ok ? 'local mail() accepted' : 'local mail() failed', 'localmail'];
    }

    return sendViaDirectSmtp($to, $rawMail);
}

function telegramPost($method, $fields) {
    $token = defined('TELEGRAM_BOT_TOKEN') ? trim((string)TELEGRAM_BOT_TOKEN) : '';
    if ($token === '') {
        return [false, 'Telegram token sozlanmagan'];
    }

    $url = "https://api.telegram.org/bot{$token}/{$method}";
    if (!function_exists('curl_init')) {
        if ($method !== 'sendMessage') {
            return [false, 'cURL mavjud emas (fayl yuborish imkonsiz)'];
        }
        $ctx = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
                'content' => http_build_query($fields),
                'timeout' => 20,
            ],
        ]);
        $raw = @file_get_contents($url, false, $ctx);
        if ($raw === false) return [false, 'Telegram HTTP so\'rovi bajarilmadi'];
        $json = json_decode($raw, true);
        if (!is_array($json) || !($json['ok'] ?? false)) {
            return [false, 'Telegram API xatosi: ' . ($json['description'] ?? 'unknown')];
        }
        return [true, 'telegram:' . $method];
    }

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $fields);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    $raw = curl_exec($ch);
    $curlErr = curl_error($ch);
    curl_close($ch);

    if ($raw === false) {
        return [false, 'Telegram cURL xatosi: ' . $curlErr];
    }

    $json = json_decode($raw, true);
    if (!is_array($json) || !($json['ok'] ?? false)) {
        return [false, 'Telegram API xatosi: ' . ($json['description'] ?? 'unknown')];
    }
    return [true, 'telegram:' . $method];
}

function sendComplaintToTelegram($name, $contact, $message, $transcript = '', $audioAbsPath = null, $audioPathRel = null) {
    $chatId = defined('TELEGRAM_CHAT_ID') ? trim((string)TELEGRAM_CHAT_ID) : '';
    if ($chatId === '') {
        return [false, 'Telegram chat_id sozlanmagan', 'telegram:none'];
    }

    $text = "Yangi ovozli e'tiroz\n\n"
        . "Ism: {$name}\n"
        . "Kontakt: {$contact}\n"
        . "Vaqt: " . date('Y-m-d H:i:s') . "\n\n"
        . "Xabar: {$message}";
    if ($transcript !== '') {
        $text .= "\n\nTranskript:\n{$transcript}";
    }
    if ($audioPathRel) {
        $text .= "\n\nAudio: {$audioPathRel}";
    }

    if ($audioAbsPath && is_file($audioAbsPath) && function_exists('curl_file_create')) {
        if (function_exists('mb_substr')) {
            $caption = mb_substr($text, 0, 1000, 'UTF-8');
        } else {
            $caption = substr($text, 0, 1000);
        }
        $fields = [
            'chat_id' => $chatId,
            'caption' => $caption,
            'document' => curl_file_create($audioAbsPath, detectAudioMimeByFilename($audioAbsPath), basename($audioAbsPath)),
        ];
        [$okDoc, $infoDoc] = telegramPost('sendDocument', $fields);
        if ($okDoc) {
            return [true, $infoDoc, 'telegram:sendDocument'];
        }
        // Fayl yuborish ishlamasa hech bo'lmaganda matn yuborib qo'yamiz
        [$okMsg, $infoMsg] = telegramPost('sendMessage', [
            'chat_id' => $chatId,
            'text' => $text . "\n\nAudio yuborilmadi: {$infoDoc}",
        ]);
        return [$okMsg, $okMsg ? 'document-failed-fallback-message-sent' : $infoDoc . ' | ' . $infoMsg, 'telegram:fallback'];
    }

    [$ok, $info] = telegramPost('sendMessage', [
        'chat_id' => $chatId,
        'text' => $text,
    ]);
    return [$ok, $info, 'telegram:sendMessage'];
}

try {
    $isMultipart = isset($_FILES['audio']) || stripos($_SERVER['CONTENT_TYPE'] ?? '', 'multipart/form-data') !== false;
    $input = null;
    if (!$isMultipart) {
        $input = json_decode(file_get_contents('php://input'), true) ?: [];
    }

    $name = normalizeText($isMultipart ? ($_POST['name'] ?? '') : ($input['name'] ?? ''), 'Anonymous');
    $contact = normalizeText($isMultipart ? ($_POST['contact'] ?? '') : ($input['contact'] ?? ''), 'N/A');
    $message = normalizeText($isMultipart ? ($_POST['message'] ?? '') : ($input['message'] ?? ''), '');
    $transcript = normalizeText($isMultipart ? ($_POST['transcript'] ?? '') : ($input['transcript'] ?? ''), '');

    $audioPathRel = null;
    $audioPathAbs = null;
    $attachmentName = null;

    if (isset($_FILES['audio']) && is_array($_FILES['audio']) && (int)($_FILES['audio']['error'] ?? 4) === 0) {
        $tmp = (string)($_FILES['audio']['tmp_name'] ?? '');
        $originalName = (string)($_FILES['audio']['name'] ?? 'complaint_audio.wav');
        $mime = (string)($_FILES['audio']['type'] ?? 'audio/wav');
        $size = (int)($_FILES['audio']['size'] ?? 0);

        if ($tmp === '' || !is_uploaded_file($tmp)) {
            echo json_encode(['error' => 'Audio fayl noto\'g\'ri yuborildi']);
            exit;
        }
        if ($size <= 0 || $size > 25 * 1024 * 1024) {
            echo json_encode(['error' => 'Audio hajmi ruxsat etilgan limitdan oshdi']);
            exit;
        }

        $ext = detectAudioExtension($mime, $originalName);
        $dirRel = 'uploads/complaints/' . date('Ymd');
        $dirAbs = dirname(__DIR__) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $dirRel);
        if (!is_dir($dirAbs)) {
            @mkdir($dirAbs, 0777, true);
        }

        $base = 'complaint_' . date('His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        $targetAbs = $dirAbs . DIRECTORY_SEPARATOR . $base;
        if (!@move_uploaded_file($tmp, $targetAbs)) {
            echo json_encode(['error' => 'Audio faylni saqlab bo\'lmadi']);
            exit;
        }

        $audioPathRel = $dirRel . '/' . $base;
        $audioPathAbs = $targetAbs;
        $attachmentName = $base;
    }

    if ($message === '' && $transcript !== '') {
        $message = $transcript;
    }
    if ($message === '' && $audioPathRel) {
        $message = 'Voice complaint submitted (audio attached)';
    }
    if ($message === '' && !$audioPathRel) {
        echo json_encode(['error' => 'Shikoyat matni yoki audio fayl kerak']);
        exit;
    }

    $pdo = getDbConnection();
    $stmt = $pdo->prepare("INSERT INTO complaints (full_name, contact, message, transcript, audio_path) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$name, $contact, $message, $transcript ?: null, $audioPathRel]);

    $to = defined('COMPLAINT_EMAIL') ? COMPLAINT_EMAIL : 'elbekroxmonov@gmail.com';
    $subject = "Yangi shikoyat (kiosk): {$name}";
    $body = "Yangi shikoyat qabul qilindi.\n\n"
        . "Ism: {$name}\n"
        . "Kontakt: {$contact}\n"
        . "Vaqt: " . date('Y-m-d H:i:s') . "\n\n"
        . "Xabar:\n{$message}\n\n";
    if ($transcript !== '') {
        $body .= "Transkript:\n{$transcript}\n\n";
    }
    if ($audioPathRel) {
        $body .= "Audio fayl: {$audioPathRel}\n";
    }

    [$mailSent, $mailInfo, $mailTransport] = sendComplaintEmail($to, $subject, $body, $audioPathAbs, $attachmentName);
    if (!$mailSent) {
        error_log('Complaint mail send failed for: ' . $to . ' | complaint_id=' . $pdo->lastInsertId() . ' | ' . $mailInfo);
    }
    [$telegramSent, $telegramInfo, $telegramTransport] = sendComplaintToTelegram($name, $contact, $message, $transcript, $audioPathAbs, $audioPathRel);
    if (!$telegramSent) {
        error_log('Complaint telegram send failed | complaint_id=' . $pdo->lastInsertId() . ' | ' . $telegramInfo);
    }

    echo json_encode([
        'success' => true,
        'mail_sent' => (bool)$mailSent,
        'target_email' => $to,
        'mail_transport' => $mailTransport,
        'mail_error' => $mailSent ? null : $mailInfo,
        'telegram_sent' => (bool)$telegramSent,
        'telegram_transport' => $telegramTransport,
        'telegram_error' => $telegramSent ? null : $telegramInfo,
    ]);
} catch (Throwable $e) {
    error_log('Complaint API error: ' . $e->getMessage());
    echo json_encode(['error' => 'Shikoyatni yuborishda xatolik yuz berdi']);
}

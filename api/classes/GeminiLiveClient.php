<?php
/**
 * GeminiLiveClient - PHP WebSocket client for Gemini 3.1 Flash Live Preview
 */
class GeminiLiveClient {
    private $host = 'generativelanguage.googleapis.com';
    private $port = 443;
    private $apiKey;
    private $socket;
    
    private $voiceName = 'Aoede';
    private $model = 'models/gemini-3.1-flash-live-preview'; // Asosiy 3.1 Flash Live
    private $systemInstruction = "Siz faqat matn o'qiydigan professional suhandonsiz. Mutlaqo hech qanday qo'shimcha so'z qo'shmang. Berilgan matnni xuddi o'zingiz yozgandek so'zma-so'z o'qing. Faqat TTS funksiyasini bajaring.";
    
    private $fallbacks = [
        'models/gemini-3.1-flash-live-preview',
        'models/gemini-2.5-flash-native-audio-latest',
        'models/gemini-1.5-flash-002',
        'models/gemini-2.0-flash-exp'
    ];
    
    public function __construct($apiKey, $model = null) {
        $this->apiKey = $apiKey;
        if ($model) {
            $this->model = $model;
        }
    }

    public function setVoice($voiceName) {
        $this->voiceName = $voiceName;
    }

    public function setSystemInstruction($instruction) {
        $this->systemInstruction = $instruction;
    }

    public function setModel($model) {
        $this->model = $model;
    }

    /**
     * Handshake and Connect
     */
    private function connect() {
        $path = "/ws/google.ai.generativelanguage.v1beta.GenerativeService.BidiGenerateContent?key=" . $this->apiKey;
        $context = stream_context_create([
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false
            ]
        ]);
        
        $remote = "ssl://{$this->host}:{$this->port}";
        $this->socket = @stream_socket_client($remote, $errno, $errstr, 30, STREAM_CLIENT_CONNECT, $context);
        
        if (!$this->socket) {
            throw new Exception("Socket ulanishda xato: $errstr ($errno)");
        }

        // Handshake headers
        $key = base64_encode(random_bytes(16));
        $header = "GET $path HTTP/1.1\r\n" .
                  "Host: {$this->host}\r\n" .
                  "Origin: http://localhost\r\n" .
                  "Upgrade: websocket\r\n" .
                  "Connection: Upgrade\r\n" .
                  "Sec-WebSocket-Key: $key\r\n" .
                  "Sec-WebSocket-Version: 13\r\n" .
                  "\r\n";

        fwrite($this->socket, $header);
        
        // Handshake javobini to'liq o'qish (Double-newline gacha)
        $response = "";
        while (!strpos($response, "\r\n\r\n")) {
            $chunk = fread($this->socket, 1024);
            if (!$chunk) break;
            $response .= $chunk;
            if (strlen($response) > 8192) break; // Xavfsizlik xizmati uchun
        }
        
        if (strpos($response, '101 Switching Protocols') === false) {
            preg_match('/HTTP\/1.1 (\d+)/', $response, $matches);
            $code = $matches[1] ?? 'Noma\'lum';
            error_log("❌ Gemini WebSocket Handshake Error (HTTP $code): " . substr($response, 0, 200));
            throw new Exception("WebSocket handshake failed (HTTP $code).");
        }
        
        stream_set_timeout($this->socket, 15);
        return true;
    }

    /**
     * Generate Audio directly (with Fallback support)
     */
    public function generateAudioDirect($text) {
        $lastError = "Hech qanday model ulanmadi.";
        
        // Avval tanlangan modelni sinab ko'ramiz
        try {
            $res = $this->synthesize($text, $this->voiceName);
            if ($res && strlen($res) > 100) return $res;
        } catch (Exception $e) {
            $lastError = $e->getMessage();
            error_log("⚠️ Primary model ({$this->model}) failed: " . $lastError);
        }

        // Agar tanlangan model xato bersa, fallbacklarni sinaymiz
        foreach ($this->fallbacks as $fb) {
            if ($fb === $this->model) continue;
            try {
                error_log("🔄 Fallback modelga o'tilmoqda: $fb");
                $this->model = $fb;
                $res = $this->synthesize($text, $this->voiceName);
                if ($res && strlen($res) > 100) return $res;
            } catch (Exception $e) {
                $lastError = $e->getMessage();
                error_log("❌ Fallback model ($fb) ham xato berdi: " . $lastError);
            }
        }
        
        throw new Exception("Xatolik: " . $lastError);
    }

    /**
     * Send WebSocket Text Frame (Masked)
     */
    private function sendFrame($payload) {
        $length = strlen($payload);
        $header = chr(0x81); // FIN + Text
        
        if ($length <= 125) {
            $header .= chr(0x80 | $length);
        } elseif ($length <= 65535) {
            $header .= chr(0x80 | 126) . pack('n', $length);
        } else {
            $header .= chr(0x80 | 127) . pack('J', $length);
        }
        
        $mask = random_bytes(4);
        $header .= $mask;
        
        $masked = '';
        for ($i = 0; $i < $length; $i++) {
            $masked .= $payload[$i] ^ $mask[$i % 4];
        }
        
        fwrite($this->socket, $header . $masked);
    }

    /**
     * Receive WebSocket Frame (Unmasked from server)
     */
    private function receiveFrame() {
        $data = fread($this->socket, 2);
        if (strlen($data) < 2) return null;
        
        $firstByte = ord($data[0]);
        $secondByte = ord($data[1]);
        $opcode = $firstByte & 0x0F;
        $len = $secondByte & 0x7F;
        
        if ($len == 126) {
            $len = unpack('n', fread($this->socket, 2))[1];
        } elseif ($len == 127) {
            $len = unpack('J', fread($this->socket, 8))[1];
        }
        
        $payload = '';
        while (strlen($payload) < $len) {
            $chunk = fread($this->socket, $len - strlen($payload));
            if (!$chunk) break;
            $payload .= $chunk;
        }
        
        // Opcode 8 is Close, 9 Ping, 10 Pong
        if ($opcode == 8) return ['type' => 'close'];
        return ['type' => 'text', 'payload' => $payload];
    }

    /**
     * Wait for setupComplete message (Loop through frames until found or error)
     */
    private function waitForSetup($maxTries = 5) {
        for ($i = 0; $i < $maxTries; $i++) {
            $frame = $this->receiveFrame();
            if (!$frame) {
                error_log("❌ Gemini WebSocket socket closed during setup.");
                return false;
            }
            
            $res = json_decode($frame['payload'], true);
            if (!$res) continue;

            if (isset($res['setupComplete'])) {
                return true;
            }
            
            // Xatolik xabari bo'lsa
            if (isset($res['error'])) {
                $msg = $res['error']['message'] ?? 'Noma\'lum API xatosi';
                error_log("❌ Gemini Setup API Error: " . $msg);
                throw new Exception("Gemini API Error: " . $msg);
            }
            
            // Agar boshqa kutilmagan javob bo'lsa log qilamiz
            error_log("ℹ️ Gemini Setup intermediate message: " . substr($frame['payload'], 0, 100));
        }
        return false;
    }

    /**
     * Synthesize Text to Audio chunks
     */
    public function synthesize($text, $voiceName, $callback = null) {
        if (!$this->connect()) return false;
        
        // 1. Setup config (Use camelCase as per official Bidi-GenerateContent spec)
        $setup = [
            'setup' => [
                'model' => $this->model,
                'systemInstruction' => [
                    'parts' => [
                        ['text' => $this->systemInstruction]
                    ]
                ],
                'generationConfig' => [
                    'responseModalities' => ['AUDIO'],
                    'speechConfig' => [
                        'voiceConfig' => [
                            'prebuiltVoiceConfig' => [
                                'voiceName' => $voiceName
                            ]
                        ]
                    ]
                ]
            ]
        ];
        $this->sendFrame(json_encode($setup));
        
        // 1.1 Wait for Setup Completion
        if (!$this->waitForSetup()) {
            throw new Exception("Gemini Live Setup tasdig'ini ololmadi. Model nomi yoki keyingizni tekshiring.");
        }
        
        // 2. Send Text Input
        $input = [
            'clientContent' => [
                'turns' => [
                    [
                        'role' => 'user',
                        'parts' => [['text' => $text]]
                    ]
                ],
                'turnComplete' => true
            ]
        ];
        
        $this->sendFrame(json_encode($input));
        
        $fullAudio = '';
        $done = false;
        
        while (!$done) {
            $frame = $this->receiveFrame();
            if (!$frame || $frame['type'] == 'close') break;
            
            $res = json_decode($frame['payload'], true);
            if (!$res) continue;

            // Xatoliklarni va matnli javoblarni log qilish
            if (isset($res['serverContent']['modelTurn']['parts'][0]['text'])) {
                $txt = $res['serverContent']['modelTurn']['parts'][0]['text'];
                error_log("🗨️ Gemini Live Response Text: " . $txt);
            }
            
            if (isset($res['serverContent']['modelTurn']['error'])) {
                $err = $res['serverContent']['modelTurn']['error']['message'] ?? 'Noma\'lum model xatosi';
                throw new Exception("Gemini Model Error: " . $err);
            }
            
            // ServerContent contains the audio
            if (isset($res['serverContent']['modelTurn']['parts'])) {
                foreach ($res['serverContent']['modelTurn']['parts'] as $part) {
                    if (isset($part['inlineData']['data'])) {
                        $audioChunk = base64_decode($part['inlineData']['data']);
                        if ($callback) {
                            $callback($audioChunk);
                        } else {
                            $fullAudio .= $audioChunk;
                        }
                    }
                }
            }
            
            // turnComplete signals the end of the response
            if (isset($res['serverContent']['turnComplete'])) {
                $done = true;
            }
        }
        
        fclose($this->socket);
        return $callback ? true : $fullAudio;
    }
}

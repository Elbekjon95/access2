<?php

class iFlytekHelper {
    public static function generateAuthUrl($host, $apiKey, $apiSecret) {
        $date = gmdate('D, d M Y H:i:s') . ' GMT';
        
        $urlParts = parse_url($host);
        $path = $urlParts['path'];
        $domain = $urlParts['host'];
        
        $signature_origin = "host: " . $domain . "\n";
        $signature_origin .= "date: " . $date . "\n";
        $signature_origin .= "GET " . $path . " HTTP/1.1";
        
        $signature_sha = hash_hmac('sha256', $signature_origin, $apiSecret, true);
        $signature = base64_encode($signature_sha);
        
        $authorization_origin = "api_key=\"$apiKey\", algorithm=\"hmac-sha256\", headers=\"host date request-line\", signature=\"$signature\"";
        $authorization = base64_encode($authorization_origin);
        
        $params = [
            'host' => $domain,
            'date' => $date,
            'authorization' => $authorization
        ];
        
        return $host . '?' . http_build_query($params);
    }

    public static function getSttUrl() {
        $host = "wss://ist-api.xfyun.cn/v2/ist";
        return self::generateAuthUrl($host, IFLYTEK_API_KEY, IFLYTEK_API_SECRET);
    }

    public static function getTtsUrl() {
        $host = "wss://tts-api.xfyun.cn/v2/tts";
        return self::generateAuthUrl($host, IFLYTEK_API_KEY, IFLYTEK_API_SECRET);
    }
}

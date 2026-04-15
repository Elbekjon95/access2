<?php
require_once __DIR__ . '/../config.php';
$url = "https://generativelanguage.googleapis.com/v1beta/models?key=" . GEMINI_API_KEY;
$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
$res = curl_exec($ch);
curl_close($ch);
file_put_contents(__DIR__ . '/gemini_models.json', $res);
echo "Models fetched!";

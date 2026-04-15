<?php
require 'api/config.php';
$pdo = getDbConnection();
$res = $pdo->query("SELECT ai_response FROM chats ORDER BY id DESC LIMIT 5")->fetchAll();
print_r($res);

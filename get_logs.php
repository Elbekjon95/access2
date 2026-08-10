<?php
require_once __DIR__ . '/config.php';
$db = getDbConnection();
$res = $db->find('chats', [], ['limit' => 5, 'sort' => ['_id' => -1]]);
print_r($res);


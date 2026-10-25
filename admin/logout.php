<?php
require_once __DIR__ . '/../config.php';
secureSessionStart();
session_destroy();
header("Location: ../index.php");
exit;

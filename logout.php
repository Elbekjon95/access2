<?php
require_once 'config.php';
secureSessionStart();
session_unset();
session_destroy();
header("Location: index.php");
exit;

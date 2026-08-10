<?php
require_once 'config.php';
secureSessionStart();
session_unset();
session_destroy();
header("Location: admin/login.php");
exit;

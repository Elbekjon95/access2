<?php
require_once '../config.php';
session_start();

if (!isset($_SESSION['admin_auth'])) {
    http_response_code(403);
    die("Ruxsat berilmagan!");
}

$db = getDbConnection();
$action = $_GET['action'] ?? '';

if ($action === 'add' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = $_POST['full_name'];
    $username = $_POST['username'];
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $role = $_POST['role'];

    try {
        $stmt = $db->prepare("INSERT INTO users (full_name, username, password, role) VALUES (?, ?, ?, ?)");
        $stmt->execute([$full_name, $username, $password, $role]);
        header("Location: ../admin_dashboard.php?msg=added");
    } catch (PDOException $e) {
        die("Xatolik: " . $e->getMessage());
    }
}
// Delete va Edit keyinroq qo'shiladi

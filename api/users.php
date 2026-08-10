<?php
require_once __DIR__ . '/../config.php';
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
    $role = $_POST['role'] ?? 'user';

    try {
        $db->insertOne('users', [
            'full_name' => $full_name,
            'username' => $username,
            'password' => $password,
            'role' => $role,
            'created_at' => date('Y-m-d H:i:s')
        ]);
        header("Location: ../admin_dashboard.php?msg=added");
    } catch (Throwable $e) {
        die("Xatolik: " . $e->getMessage());
    }
}
// Delete va Edit keyinroq qo'shiladi

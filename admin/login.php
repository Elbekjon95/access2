<?php
require_once __DIR__ . '/../config.php';
secureSessionStart();

if (isset($_SESSION['admin_id'])) {
    header("Location: index.php");
    exit;
}

$db = getDbConnection();
$userCount = $db->count('users');
$error = "";
$success = "";

// CSRF tekshiruvi (POST so'rovlar uchun)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        die("CSRF xatoligi! So'rov xavfsiz emas.");
    }
}

// Registration handling (Only if no users exist)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['register']) && $userCount == 0) {
    $full_name = $_POST['full_name'];
    $username = $_POST['username'];
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    
    try {
        $db->insertOne('users', [
            'username' => $username,
            'password' => $password,
            'full_name' => $full_name,
            'role' => 'admin',
            'created_at' => date('Y-m-d H:i:s')
        ]);
        $success = "Birinchi admin muvaffaqiyatli yaratildi! Endi kirishingiz mumkin.";
        $userCount = 1; // Update count to show login form
    } catch (Throwable $e) {
        $error = "Xato: " . $e->getMessage();
    }
}

// Login handling
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['login'])) {
    $username = $_POST['username'];
    $password = $_POST['password'];

    $user = $db->findOne('users', [
        'username' => $username,
        'role' => 'admin'
    ]);

    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id'] = $user['id']; // Umumiy user_id
        $_SESSION['user_name'] = $user['full_name'];
        $_SESSION['user_role'] = $user['role'];

        if ($user['role'] == 'admin') {
            $_SESSION['admin_id'] = $user['id']; // Admin panel uchun
            $_SESSION['admin_name'] = $user['full_name'];
            header("Location: index.php");
        } else {
            header("Location: ../index.php");
        }
        exit;
    } else {
        $error = "Login yoki parol xato!";
    }
}
?>
<!DOCTYPE html>
<html lang="uz">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ACSESS Admin - Kirish</title>
    <link rel="manifest" href="/manifest.json">
    <meta name="theme-color" content="#050b14">
    <link rel="icon" type="image/png" sizes="192x192" href="/img/icons/icon-192.png">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;600&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Outfit', sans-serif; background: #050a14; color: white; display: flex; align-items: center; justify-content: center; height: 100vh; margin: 0; }
        .login-card { background: rgba(255,255,255,0.05); padding: 2.5rem; border-radius: 20px; border: 1px solid rgba(255,255,255,0.1); backdrop-filter: blur(10px); width: 350px; text-align: center; }
        h2 { margin-bottom: 2rem; letter-spacing: 2px; color: #00c6ff; }
        input { width: 100%; padding: 12px; margin-bottom: 1.5rem; background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.2); border-radius: 10px; color: white; outline: none; }
        button { width: 100%; padding: 12px; background: #0072ff; border: none; border-radius: 10px; color: white; font-weight: 600; cursor: pointer; transition: 0.3s; }
        button:hover { background: #00c6ff; box-shadow: 0 0 20px rgba(0,198,255,0.5); }
        .error { color: #ff5252; margin-bottom: 1rem; font-size: 0.9rem; }
    </style>
</head>
<body>
    <div class="login-card">
        <h2>ACSESS ADMIN</h2>
        <?php if($error): ?> <div class="error"><?php echo $error; ?></div> <?php endif; ?>
        <?php if($success): ?> <div style="color: #00c6ff; margin-bottom: 1rem;"><?php echo $success; ?></div> <?php endif; ?>
        
        <?php if ($userCount == 0): ?>
            <p style="font-size: 0.8rem; color: #aaa; margin-bottom: 1.5rem;">Bazada foydalanuvchi yo'q. Iltimos, birinchi adminni ro'yxatdan o'tkazing.</p>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo getCsrfToken(); ?>">
                <input type="text" name="full_name" placeholder="To'liq ism" required>
                <input type="text" name="username" placeholder="Login" required>
                <input type="password" name="password" placeholder="Parol" required>
                <button type="submit" name="register">RO'YXATDAN O'TISH</button>
            </form>
        <?php else: ?>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo getCsrfToken(); ?>">
                <input type="text" name="username" placeholder="Login" required>
                <input type="password" name="password" placeholder="Parol" required>
                <button type="submit" name="login">KIRISH</button>
            </form>
        <?php endif; ?>
    </div>
</body>
</html>

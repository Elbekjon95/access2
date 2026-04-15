<?php
require_once '../config.php';
session_start();

if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit;
}

$msg = "";
// User Action handling: ADD
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_user'])) {
    $full_name = $_POST['full_name'];
    $username = $_POST['username'];
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    
    try {
        $stmt = $pdo->prepare("INSERT INTO users (username, password, full_name, role) VALUES (?, ?, ?, 'admin')");
        $stmt->execute([$username, $password, $full_name]);
        $msg = "Foydalanuvchi muvaffaqiyatli qo'shildi!";
    } catch (PDOException $e) {
        $msg = "Xato: " . $e->getMessage();
    }
}

// User Action handling: EDIT
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['edit_user'])) {
    $user_id = $_POST['user_id'];
    $full_name = $_POST['full_name'];
    $username = $_POST['username'];
    
    try {
        if (!empty($_POST['password'])) {
            $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE users SET full_name = ?, username = ?, password = ? WHERE id = ?");
            $stmt->execute([$full_name, $username, $password, $user_id]);
        } else {
            $stmt = $pdo->prepare("UPDATE users SET full_name = ?, username = ? WHERE id = ?");
            $stmt->execute([$full_name, $username, $user_id]);
        }
        $msg = "Foydalanuvchi ma'lumotlari yangilandi!";
    } catch (PDOException $e) {
        $msg = "Xato: " . $e->getMessage();
    }
}

// User Action handling: DELETE
if (isset($_GET['delete'])) {
    $stmt = $pdo->prepare("DELETE FROM users WHERE id = ? AND id != ?");
    $stmt->execute([$_GET['delete'], $_SESSION['admin_id']]);
    header("Location: users.php");
    exit;
}

$edit_user = null;
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$_GET['edit']]);
    $edit_user = $stmt->fetch();
}

$users = $pdo->query("SELECT * FROM users ORDER BY created_at DESC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="uz">
<head>
    <meta charset="UTF-8">
    <title>Adminlar - ACCSESS</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root { --sidebar-w: 250px; --accent: #00c6ff; --bg: #050a14; --panel: rgba(255,255,255,0.05); }
        body { font-family: 'Outfit', sans-serif; background: var(--bg); color: white; margin: 0; display: flex; }
        aside { width: var(--sidebar-w); height: 100vh; background: var(--panel); border-right: 1px solid rgba(255,255,255,0.1); padding: 2rem 1rem; position: fixed; }
        .logo { font-size: 1.5rem; font-weight: 700; color: var(--accent); margin-bottom: 3rem; text-align: center; }
        nav a { display: block; padding: 12px 20px; color: rgba(255,255,255,0.7); text-decoration: none; border-radius: 10px; margin-bottom: 10px; transition: 0.3s; }
        nav a:hover, nav a.active { background: var(--accent); color: white; }
        main { flex: 1; margin-left: var(--sidebar-w); padding: 3rem; }
        .card { background: var(--panel); padding: 2rem; border-radius: 20px; border: 1px solid rgba(255,255,255,0.1); margin-bottom: 2rem; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 15px; text-align: left; border-bottom: 1px solid rgba(255,255,255,0.1); }
        .btn-del { color: #ff5252; cursor: pointer; border: none; background: none; }
        
        input { background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.2); padding: 10px; border-radius: 8px; color: white; margin-right: 10px; }
        .btn-add { background: var(--accent); color: black; font-weight: 600; border: none; padding: 10px 20px; border-radius: 8px; cursor: pointer; }
    </style>
</head>
<body>
    <aside>
        <div class="logo">ACCSESS ADMIN</div>
        <nav>
            <a href="index.php"><i class="fas fa-chart-line"></i> Dashboard</a>
            <a href="chats.php"><i class="fas fa-comments"></i> Chat Tarixi</a>
            <a href="complaints.php"><i class="fas fa-exclamation-circle"></i> Shikoyatlar</a>
            <a href="map.php"><i class="fas fa-map-marked-alt"></i> Xarita</a>
            <a href="users.php"><i class="fas fa-users"></i> Adminlar</a>
            <hr style="border: 0; border-top: 1px solid rgba(255,255,255,0.1); margin: 15px 0;">
            <a href="../index.php" style="color: var(--accent);"><i class="fas fa-external-link-alt"></i> Kioskga o'tish</a>
            <a href="logout.php" style="margin-top: 20px; color: #ff5252;"><i class="fas fa-sign-out-alt"></i> Chiqish</a>
        </nav>
    </aside>

    <main>
        <h1>Admin Foydalanuvchilar</h1>
        
        <?php if($msg): ?> <div style="color:var(--accent); margin-bottom: 1rem;"><?php echo $msg; ?></div> <?php endif; ?>

        <?php if ($edit_user): ?>
        <div class="card" style="border-color: var(--accent);">
            <h3>Foydalanuvchini tahrirlash</h3>
            <form method="POST" style="display: flex; align-items: center; flex-wrap: wrap; gap: 10px;">
                <input type="hidden" name="user_id" value="<?php echo $edit_user['id']; ?>">
                <input type="text" name="full_name" value="<?php echo htmlspecialchars($edit_user['full_name']); ?>" placeholder="To'liq ismi" required>
                <input type="text" name="username" value="<?php echo htmlspecialchars($edit_user['username']); ?>" placeholder="Login" required>
                <input type="password" name="password" placeholder="Yangi parol (ixtiyoriy)">
                <button type="submit" name="edit_user" class="btn-add">SAQLASH</button>
                <a href="users.php" style="color: white; text-decoration: none; margin-left: 10px;">Bekor qilish</a>
            </form>
        </div>
        <?php else: ?>
        <div class="card">
            <h3>Yangi Admin qo'shish</h3>
            <form method="POST" style="display: flex; align-items: center; flex-wrap: wrap; gap: 10px;">
                <input type="text" name="full_name" placeholder="To'liq ismi" required>
                <input type="text" name="username" placeholder="Login" required>
                <input type="password" name="password" placeholder="Parol" required>
                <button type="submit" name="add_user" class="btn-add">QO'SHISH</button>
            </form>
        </div>
        <?php endif; ?>

        <div class="card">
            <h3>Mavjud Adminlar</h3>
            <table>
                <thead>
                    <tr>
                        <th>Ism</th>
                        <th>Login</th>
                        <th>Boshqaruv</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $u): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($u['full_name']); ?></td>
                        <td><?php echo htmlspecialchars($u['username']); ?></td>
                        <td>
                            <a href="?edit=<?php echo $u['id']; ?>" style="color: var(--accent); margin-right: 15px;"><i class="fas fa-edit"></i></a>
                            <?php if ($u['id'] != $_SESSION['admin_id']): ?>
                                <a href="?delete=<?php echo $u['id']; ?>" class="btn-del" onclick="return confirm('O\'chirishni tasdiqlaysizmi?')"><i class="fas fa-trash"></i></a>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </main>
</body>
</html>

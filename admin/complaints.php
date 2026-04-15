<?php
require_once '../config.php';
session_start();

if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit;
}

// Shikoyatlar ro'yxatini olish
$complaints = $pdo->query("SELECT * FROM complaints ORDER BY created_at DESC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="uz">
<head>
    <meta charset="UTF-8">
    <title>Shikoyatlar - ACCSESS</title>
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
        .card { background: var(--panel); padding: 2rem; border-radius: 20px; border: 1px solid rgba(255,255,255,0.1); }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 15px; text-align: left; border-bottom: 1px solid rgba(255,255,255,0.1); }
        .status-badge { padding: 4px 12px; border-radius: 4px; font-size: 0.75rem; }
        .new { background: #ff5252; color: white; }
        .seen { background: #00c6ff; color: white; }
    </style>
</head>
<body>
    <aside>
        <div class="logo">ACCSESS ADMIN</div>
        <nav>
            <a href="index.php"><i class="fas fa-chart-line"></i> Dashboard</a>
            <a href="chats.php"><i class="fas fa-comments"></i> Chat Tarixi</a>
            <a href="complaints.php" class="active"><i class="fas fa-exclamation-circle"></i> Shikoyatlar</a>
            <a href="map.php"><i class="fas fa-map-marked-alt"></i> Xarita</a>
            <a href="users.php"><i class="fas fa-users"></i> Adminlar</a>
            <hr style="border: 0; border-top: 1px solid rgba(255,255,255,0.1); margin: 15px 0;">
            <a href="../index.php" style="color: var(--accent);"><i class="fas fa-external-link-alt"></i> Kioskga o'tish</a>
            <a href="logout.php" style="margin-top: 20px; color: #ff5252;"><i class="fas fa-sign-out-alt"></i> Chiqish</a>
        </nav>
    </aside>

    <main>
        <h1>Mijozlar shikoyat va e'tirozlari</h1>
        <div class="card">
            <table>
                <thead>
                    <tr>
                        <th>Ism</th>
                        <th>Kontakt</th>
                        <th>Xabar</th>
                        <th>Holat</th>
                        <th>Vaqt</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($complaints as $c): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($c['full_name']); ?></td>
                        <td><?php echo htmlspecialchars($c['contact']); ?></td>
                        <td><?php echo htmlspecialchars($c['message']); ?></td>
                        <td><span class="status-badge <?php echo $c['status']; ?>"><?php echo strtoupper($c['status']); ?></span></td>
                        <td><?php echo $c['created_at']; ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </main>
</body>
</html>

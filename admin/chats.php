<?php
require_once '../config.php';
session_start();

if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit;
}

$stmt = $pdo->query("SELECT c.*, cp.image_path FROM chats c LEFT JOIN customer_captures cp ON c.capture_id = cp.id ORDER BY c.created_at DESC");
$chats = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="uz">
<head>
    <meta charset="UTF-8">
    <title>Chat Tarixi - ACCSESS</title>
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
        .chat-card { background: var(--panel); border-radius: 15px; padding: 1.5rem; margin-bottom: 1.5rem; border: 1px solid rgba(255,255,255,0.1); }
        .chat-header { display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid rgba(255,255,255,0.1); padding-bottom: 10px; margin-bottom: 15px; }
        .lang-tag { background: #d5a107; color: black; padding: 2px 8px; border-radius: 4px; font-weight: bold; font-size: 0.8rem; }
        .customer-img { width: 80px; height: 80px; border-radius: 10px; object-fit: cover; border: 2px solid var(--accent); cursor: zoom-in; transition: 0.3s; }
        .customer-img:hover { transform: scale(1.05); box-shadow: 0 0 15px var(--accent); }
        .content { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        .msg { font-size: 0.95rem; color: #aaa; background: rgba(255,255,255,0.02); padding: 15px; border-radius: 10px; }
        .msg strong { color: var(--accent); display: block; margin-bottom: 8px; font-size: 0.8rem; text-transform: uppercase; }
        
        /* Lightbox */
        #lightbox { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.9); z-index: 1000; justify-content: center; align-items: center; }
        #lightbox img { max-width: 90%; max-height: 90%; border: 3px solid white; border-radius: 10px; }
        #lightbox.active { display: flex; }
    </style>
</head>
<body>
    <div id="lightbox" onclick="this.classList.remove('active')">
        <img id="lightbox-img" src="">
    </div>

    <aside>
        <div class="logo">ACCSESS ADMIN</div>
        <nav>
            <a href="index.php"><i class="fas fa-chart-line"></i> Dashboard</a>
            <a href="chats.php" class="active"><i class="fas fa-comments"></i> Chat Tarixi</a>
            <a href="complaints.php"><i class="fas fa-exclamation-circle"></i> Shikoyatlar</a>
            <a href="map.php"><i class="fas fa-map-marked-alt"></i> Xarita Sozlamalari</a>
            <a href="json_import.php"><i class="fas fa-file-import"></i> JSON Import</a>
            <a href="earth.php"><i class="fas fa-globe"></i> 3D Yer</a>
            <a href="users.php"><i class="fas fa-users"></i> Adminlar</a>
            <hr style="border: 0; border-top: 1px solid rgba(255,255,255,0.1); margin: 15px 0;">
            <a href="../index.php" style="color: var(--accent);"><i class="fas fa-external-link-alt"></i> Kioskga o'tish</a>
            <a href="logout.php" style="margin-top: 20px; color: #ff5252;"><i class="fas fa-sign-out-alt"></i> Chiqish</a>
        </nav>
    </aside>

    <main>
        <h1>Mijozlar bilan muloqot tarixi</h1>
        <?php foreach ($chats as $chat): ?>
        <div class="chat-card">
            <div class="chat-header">
                <div style="display: flex; align-items: center; gap: 20px;">
                    <?php if ($chat['image_path']): ?>
                        <img src="../<?php echo $chat['image_path']; ?>" class="customer-img" onclick="showLightbox(this.src)">
                    <?php else: ?>
                        <div class="customer-img" style="display: flex; align-items: center; justify-content: center; background: #333;"><i class="fas fa-user fa-2x"></i></div>
                    <?php endif; ?>
                    <div>
                        <div style="font-weight: 600; font-size: 1.1rem; color: var(--accent);">Seans ID: <?php echo $chat['id']; ?></div>
                        <div style="font-size: 0.85rem; color: rgba(255,255,255,0.5);"><?php echo $chat['created_at']; ?></div>
                    </div>
                </div>
                <span class="lang-tag"><?php echo strtoupper($chat['language']); ?></span>
            </div>
            <div class="content">
                <div class="msg">
                    <strong>Mijoz:</strong>
                    <?php echo htmlspecialchars($chat['user_message']); ?>
                </div>
                <div class="msg">
                    <strong>AI Assistent:</strong>
                    <?php echo htmlspecialchars($chat['ai_response']); ?>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </main>

    <script>
        function showLightbox(src) {
            document.getElementById('lightbox-img').src = src;
            document.getElementById('lightbox').classList.add('active');
        }
    </script>
</body>
</html>

<?php
require_once '../config.php';
session_start();

if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit;
}

// Map nuqtalarini olish
$pdo = getDbConnection();
$stmt = $pdo->query("SELECT * FROM map_points ORDER BY type, name");
$points = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="uz">
<head>
    <meta charset="UTF-8">
    <title>JSON Import - ACCSESS</title>
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
        .btn { background: var(--accent); color: black; font-weight: 600; border: none; padding: 12px 24px; border-radius: 8px; cursor: pointer; font-size: 1rem; }
        .btn:hover { opacity: 0.8; }
        .btn-danger { background: #ff5252; color: white; }
        textarea { width: 100%; min-height: 300px; background: rgba(0,0,0,0.3); border: 1px solid rgba(255,255,255,0.2); color: white; padding: 15px; border-radius: 8px; font-family: 'Courier New', monospace; font-size: 0.9rem; }
        .status { padding: 10px 15px; border-radius: 8px; margin: 10px 0; }
        .status.success { background: rgba(0,255,136,0.1); color: #00ff88; border: 1px solid #00ff88; }
        .status.error { background: rgba(255,82,82,0.1); color: #ff5252; border: 1px solid #ff5252; }
        table { width: 100%; border-collapse: collapse; margin-top: 1rem; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid rgba(255,255,255,0.1); }
        th { color: var(--accent); font-size: 0.8rem; text-transform: uppercase; }
        .badge { padding: 4px 10px; border-radius: 5px; font-size: 0.75rem; }
        .badge-gate { background: #0072ff; }
        .badge-fids { background: #d5a107; }
        .badge-toilet { background: #00c853; }
        .badge-reception { background: #ff5252; }
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
            <a href="json_import.php" class="active"><i class="fas fa-file-import"></i> JSON Import</a>
            <a href="users.php"><i class="fas fa-users"></i> Adminlar</a>
            <hr style="border: 0; border-top: 1px solid rgba(255,255,255,0.1); margin: 15px 0;">
            <a href="../index.php" style="color: var(--accent);"><i class="fas fa-external-link-alt"></i> Kioskga o'tish</a>
            <a href="logout.php" style="margin-top: 20px; color: #ff5252;"><i class="fas fa-sign-out-alt"></i> Chiqish</a>
        </nav>
    </aside>

    <main>
        <h1>📥 JSON Import - Xarita Nuqtalari</h1>
        
        <div class="card">
            <h3>📝 JSON Fayl Ko'rinishi</h3>
            <p style="color: rgba(255,255,255,0.7);">
                <code>data/map_points.json</code> faylini tahrirlang va quyidagi tugmani bosing.
            </p>
            <p style="color: rgba(255,255,255,0.6); font-size: 0.85rem;">
                * Nomlar faqat inglizcha bo'lishi shart (A-Z, 0-9, bo'shliq va .,#()-_/).
            </p>
            
            <textarea id="jsonPreview" readonly>{
  "airport_name": "TAS Aerovokzal",
  "map_points": [
    {"name": "Gate B2", "type": "gate", "pos_x": 550, "pos_y": 300},
    {"name": "FIDS 1", "type": "fids", "pos_x": 300, "pos_y": 150},
    {"name": "Toilet", "type": "toilet", "pos_x": 200, "pos_y": 400}
  ]
}</textarea>
            
            <button class="btn" onclick="importJSON()">
                <i class="fas fa-upload"></i> JSON FAYLNI IMPORT QILISH
            </button>
            
            <div id="importStatus"></div>
        </div>

        <div class="card">
            <h3>📊 Mavjud Nuqtalar (<?php echo count($points); ?> ta)</h3>
            <?php if (count($points) > 0): ?>
            <table>
                <thead>
                    <tr>
                        <th>Nom</th>
                        <th>Turi</th>
                        <th>X</th>
                        <th>Y</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($points as $p): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($p['name']); ?></td>
                        <td><span class="badge badge-<?php echo $p['type']; ?>"><?php echo strtoupper($p['type']); ?></span></td>
                        <td><?php echo $p['pos_x']; ?></td>
                        <td><?php echo $p['pos_y']; ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
            <p style="color: rgba(255,255,255,0.5);">Hali nuqtalar qo'shilmagan.</p>
            <?php endif; ?>
        </div>
    </main>

    <script>
        async function importJSON() {
            const statusDiv = document.getElementById('importStatus');
            statusDiv.innerHTML = '<div class="status">Yuklanmoqda...</div>';
            
            try {
                const response = await fetch('../api/import_json.php', {
                    method: 'POST'
                });
                
                const result = await response.json();
                
                if (result.success) {
                    statusDiv.innerHTML = `
                        <div class="status success">
                            ✅ Muvaffaqiyatli!<br>
                            Import qilindi: ${result.imported} ta<br>
                            O'tkazib yuborildi (dublikat): ${result.skipped} ta<br>
                            Jami: ${result.total} ta
                        </div>
                    `;
                    setTimeout(() => location.reload(), 2000);
                } else {
                    statusDiv.innerHTML = `
                        <div class="status error">
                            ❌ Xato: ${result.error}<br>
                            ${result.hint || ''}
                        </div>
                    `;
                }
            } catch (err) {
                statusDiv.innerHTML = `<div class="status error">❌ Xato: ${err.message}</div>`;
            }
        }
    </script>
</body>
</html>

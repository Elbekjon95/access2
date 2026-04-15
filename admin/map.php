<?php
require_once '../config.php';
session_start();

if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit;
}

$msg = "";
$map_id = 1; // Hozircha bitta xarita bilan ishlaymiz
$mapWidth = 1000;
$mapHeight = 800;
$mapImagePath = __DIR__ . '/../img/airport_map.jpg';
$imagePathForDb = 'img/airport_map.jpg';
$imgSize = @getimagesize($mapImagePath);
if ($imgSize) {
    $mapWidth = (int)$imgSize[0];
    $mapHeight = (int)$imgSize[1];
}

function isEnglishLabel($text) {
    return preg_match('/^[A-Za-z0-9][A-Za-z0-9 .,#()\\-_\\/]*$/', $text) === 1;
}

// Tekshirish: Agar xarita mavjud bo'lmasa, yaratish
$stmt = $pdo->query("SELECT id, image_path, width, height FROM maps LIMIT 1");
$map = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$map) {
    $pdo->exec("INSERT INTO maps (floor_name, image_path, width, height) VALUES ('1-qavat', '$imagePathForDb', $mapWidth, $mapHeight)");
    $map_id = $pdo->lastInsertId();
} else {
    $map_id = $map['id'];
    // Auto-fix: Agar eski map.png bo'lsa, airport_map.jpg ga o'zgartirish
    $pdo->exec("UPDATE maps SET image_path = 'img/airport_map.jpg' WHERE id = $map_id AND image_path = 'img/map.png'");
    if ((int)$map['width'] !== $mapWidth || (int)$map['height'] !== $mapHeight) {
        $pdo->exec("UPDATE maps SET width = $mapWidth, height = $mapHeight WHERE id = $map_id");
    }
}

// Action: ADD point
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_point'])) {
    $name = $_POST['name'];
    $type = $_POST['type'];
    $pos_x = $_POST['pos_x'];
    $pos_y = $_POST['pos_y'];
    
    if (!isEnglishLabel($name)) {
        $msg = "Xato: Nom faqat inglizcha bo'lishi kerak (A-Z, 0-9, bo'shliq va .,#()-_/).";
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO map_points (map_id, name, type, pos_x, pos_y) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$map_id, $name, $type, $pos_x, $pos_y]);
            $msg = "Nuqta qo'shildi!";
        } catch (PDOException $e) {
            $msg = "Xato: " . $e->getMessage();
        }
    }
}

// Action: DELETE point
if (isset($_GET['delete_point'])) {
    $stmt = $pdo->prepare("DELETE FROM map_points WHERE id = ?");
    $stmt->execute([$_GET['delete_point']]);
    header("Location: map.php");
    exit;
}

// Action: ADD barrier
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_barrier'])) {
    $bx = $_POST['bx'];
    $by = $_POST['by'];
    $bw = $_POST['bw'];
    $bh = $_POST['bh'];
    $data = json_encode(['x' => (int)$bx, 'y' => (int)$by, 'w' => (int)$bw, 'h' => (int)$bh]);
    
    try {
        $stmt = $pdo->prepare("INSERT INTO map_barriers (map_id, barrier_data) VALUES (?, ?)");
        $stmt->execute([$map_id, $data]);
        $msg = "To'siq qo'shildi!";
    } catch (PDOException $e) {
        $msg = "Xato: " . $e->getMessage();
    }
}

// Action: DELETE barrier
if (isset($_GET['delete_barrier'])) {
    $stmt = $pdo->prepare("DELETE FROM map_barriers WHERE id = ?");
    $stmt->execute([$_GET['delete_barrier']]);
    header("Location: map.php");
    exit;
}

$points = $pdo->prepare("SELECT * FROM map_points WHERE map_id = ? ORDER BY id DESC");
$points->execute([$map_id]);
$all_points = $points->fetchAll();

$barriers_stmt = $pdo->prepare("SELECT * FROM map_barriers WHERE map_id = ? ORDER BY id DESC");
$barriers_stmt->execute([$map_id]);
$all_barriers = $barriers_stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="uz">
<head>
    <meta charset="UTF-8">
    <title>Xarita Boshqaruvi - ACCSESS</title>
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
        input, select { background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.2); padding: 10px; border-radius: 8px; color: white; margin-right: 10px; }
        .btn-add { background: var(--accent); color: black; font-weight: 600; border: none; padding: 10px 20px; border-radius: 8px; cursor: pointer; }
        .map-tools { display: flex; flex-wrap: wrap; gap: 10px; align-items: center; margin-bottom: 12px; }
        .tool-btn { background: rgba(255,255,255,0.08); border: 1px solid rgba(255,255,255,0.2); color: white; padding: 8px 14px; border-radius: 8px; cursor: pointer; font-size: 0.85rem; }
        .tool-btn.active { background: var(--accent); color: black; font-weight: 600; }
        .coord-readout { font-size: 0.85rem; opacity: 0.8; margin-left: auto; }
        .map-preview { position: relative; width: 100%; height: 70vh; max-height: 800px; border: 1px solid rgba(255,255,255,0.1); border-radius: 10px; overflow: hidden; background: rgba(0,0,0,0.35); touch-action: none; }
        .map-preview.mode-pan { cursor: grab; }
        .map-preview.mode-draw { cursor: crosshair; }
        .map-stage { position: absolute; top: 0; left: 0; transform-origin: 0 0; }
        .map-stage img { display: block; user-select: none; -webkit-user-drag: none; }
        .dot { position: absolute; width: 10px; height: 10px; background: var(--accent); border-radius: 50%; transform: translate(-50%, -50%); cursor: help; z-index: 5; }
        .barrier-rect { position: absolute; background: rgba(255, 82, 82, 0.4); border: 1px solid #ff5252; pointer-events: none; z-index: 4; }
        .selection-rect { position: absolute; border: 2px dashed #ff5252; background: rgba(255,82,82,0.15); z-index: 6; pointer-events: none; }
        
        /* Custom Confirm Modal */
        .confirm-modal {
            position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0,0,0,0.8); backdrop-filter: blur(5px);
            display: flex; align-items: center; justify-content: center;
            z-index: 10000; opacity: 0; pointer-events: none; transition: 0.3s;
        }
        .confirm-modal.active { opacity: 1; pointer-events: auto; }
        .confirm-box {
            background: var(--bg); border: 1px solid rgba(255,255,255,0.1);
            padding: 30px; border-radius: 20px; text-align: center;
            max-width: 400px; width: 90%; transform: translateY(-20px); transition: 0.3s;
        }
        .confirm-modal.active .confirm-box { transform: translateY(0); }
        .confirm-icon { font-size: 3rem; color: #ff5252; margin-bottom: 15px; }
        .confirm-text { margin-bottom: 25px; font-size: 1.1rem; line-height: 1.5; }
        .confirm-actions { display: flex; gap: 15px; justify-content: center; }
        .btn-cancel { background: rgba(255,255,255,0.1); color: white; border: none; padding: 10px 20px; border-radius: 8px; cursor: pointer; transition: 0.2s; font-family: 'Outfit', sans-serif;}
        .btn-cancel:hover { background: rgba(255,255,255,0.2); }
        .btn-confirm { background: #ff5252; color: white; text-decoration: none; display: inline-block; padding: 10px 20px; border-radius: 8px; cursor: pointer; transition: 0.2s; font-family: 'Outfit', sans-serif;}
        .btn-confirm:hover { background: #ff1f1f; }
    </style>
</head>
<body>
    <aside>
        <div class="logo">ACCSESS ADMIN</div>
        <nav>
            <a href="index.php"><i class="fas fa-chart-line"></i> Dashboard</a>
            <a href="chats.php"><i class="fas fa-comments"></i> Chat Tarixi</a>
            <a href="complaints.php"><i class="fas fa-exclamation-circle"></i> Shikoyatlar</a>
            <a href="map.php" class="active"><i class="fas fa-map-marked-alt"></i> Xarita Sozlamalari</a>
            <a href="json_import.php"><i class="fas fa-file-import"></i> JSON Import</a>
            <a href="earth.php"><i class="fas fa-globe"></i> 3D Yer</a>
            <a href="users.php"><i class="fas fa-users"></i> Adminlar</a>
            <hr style="border: 0; border-top: 1px solid rgba(255,255,255,0.1); margin: 15px 0;">
            <a href="../index.php" style="color: var(--accent);"><i class="fas fa-external-link-alt"></i> Kioskga o'tish</a>
            <a href="logout.php" style="margin-top: 20px; color: #ff5252;"><i class="fas fa-sign-out-alt"></i> Chiqish</a>
        </nav>
    </aside>

    <main>
        <h1>Xarita Boshqaruvi</h1>
        
        <?php if($msg): ?> <div style="color:var(--accent); margin-bottom: 1rem;"><?php echo $msg; ?></div> <?php endif; ?>

        <div class="card">
            <h3>Yangi Nuqta qo'shish</h3>
            <form method="POST" style="display: flex; flex-wrap: wrap; gap: 10px;">
                <input type="text" name="name" placeholder="Name (English only, e.g., Gate B2)" required pattern="[A-Za-z0-9][A-Za-z0-9 .,#()\\-_/]*" title="English only: A-Z, 0-9, space and .,#()-_/" autocomplete="off">
                <select name="type">
                    <option value="gate">Gate (Darvoza)</option>
                    <option value="fids">FIDS (Tablo)</option>
                    <option value="toilet">Tualet</option>
                    <option value="reception">Reception</option>
                    <option value="door">Eshik</option>
                    <option value="entrance">Kirish</option>
                    <option value="exit">Chiqish</option>
                    <option value="mosque">Mosque (Masjid)</option>
                    <option value="cip">CIP Zone</option>
                    <option value="vip_lounge">VIP Lounge</option>
                    <option value="counter">Stoyka (Counter)</option>
                    <option value="kiosk_start">Kiosk Start</option>
                    <option value="other">Boshqa</option>
                </select>
                <input type="number" name="pos_x" placeholder="X (0-<?php echo $mapWidth; ?>)" required>
                <input type="number" name="pos_y" placeholder="Y (0-<?php echo $mapHeight; ?>)" required>
                <button type="submit" name="add_point" class="btn-add">QO'SHISH</button>
            </form>
            <p style="font-size: 0.8rem; color: rgba(255,255,255,0.5); margin-top: 10px;">
                * Nomlar faqat inglizcha bo'lishi shart.
            </p>
        </div>

        <div class="card">
            <h3>Yangi To'siq (Barrier) qo'shish</h3>
            <form method="POST" style="display: flex; flex-wrap: wrap; gap: 10px;">
                <input type="number" name="bx" placeholder="X" required>
                <input type="number" name="by" placeholder="Y" required>
                <input type="number" name="bw" placeholder="Eni (Width)" required>
                <input type="number" name="bh" placeholder="Bo'yi (Height)" required>
                <button type="submit" name="add_barrier" class="btn-add" style="background: #ff5252; color: white;">TO'SIQ QO'SHISH</button>
            </form>
            <p style="font-size: 0.8rem; color: rgba(255,255,255,0.5); margin-top: 10px;">
                * To'siqlar navigatsiya algoritmi uchun o'tib bo'lmaydigan joylar hisoblanadi.
            </p>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem;">
            <div class="card">
                <h3>Xaritadagi Nuqtalar</h3>
                <table>
                    <thead>
                        <tr>
                            <th>Nomi</th>
                            <th>X/Y</th>
                            <th>Boshqaruv</th>
                        </tr>
                    </thead>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem;">
            <div class="card">
                <h3>Xaritadagi Nuqtalar</h3>
                <table>
                    <thead>
                        <tr>
                            <th>Nomi</th>
                            <th>X/Y</th>
                            <th>Boshqaruv</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($all_points as $p): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($p['name']); ?></td>
                            <td><?php echo "{$p['pos_x']}, {$p['pos_y']}"; ?></td>
                            <td>
                                <a href="?delete_point=<?php echo $p['id']; ?>" class="btn-del" onclick="return confirmDelete(this.href)"><i class="fas fa-trash"></i></a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="card">
                <h3>To'siqlar Ro'yxati</h3>
                <table>
                    <thead>
                        <tr>
                            <th>Joylashuv (X, Y, W, H)</th>
                            <th>Boshqaruv</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($all_barriers as $b): 
                            $bd = json_decode($b['barrier_data'], true);
                        ?>
                        <tr>
                            <td><?php echo "{$bd['x']}, {$bd['y']}, {$bd['w']}, {$bd['h']}"; ?></td>
                            <td>
                                <a href="?delete_barrier=<?php echo $b['id']; ?>" class="btn-del" onclick="return confirmDelete(this.href)"><i class="fas fa-trash"></i></a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card">
            <h3>Vizual Ko'rinish</h3>
            <div class="map-tools">
                <button type="button" class="tool-btn active" id="modeSelect">Nuqta</button>
                <button type="button" class="tool-btn" id="modePan">Pan</button>
                <button type="button" class="tool-btn" id="modeDraw">To'siq chizish</button>
                <button type="button" class="tool-btn" id="zoomIn">Zoom +</button>
                <button type="button" class="tool-btn" id="zoomOut">Zoom -</button>
                <button type="button" class="tool-btn" id="zoomReset">Reset</button>
                <div class="coord-readout" id="coordReadout">x: -, y: -</div>
            </div>
            <div class="map-preview mode-select" id="mapPreview" data-map-width="<?php echo $mapWidth; ?>" data-map-height="<?php echo $mapHeight; ?>">
                <div class="map-stage" id="mapStage" style="width: <?php echo $mapWidth; ?>px; height: <?php echo $mapHeight; ?>px;">
                    <img id="mapImage" src="../img/airport_map.jpg" alt="Map" width="<?php echo $mapWidth; ?>" height="<?php echo $mapHeight; ?>" draggable="false">
                    <?php foreach ($all_points as $p): 
                        $left = ($p['pos_x'] / $mapWidth) * 100;
                        $top = ($p['pos_y'] / $mapHeight) * 100;
                    ?>
                        <div class="dot" style="left: <?php echo $left; ?>%; top: <?php echo $top; ?>%;" title="<?php echo htmlspecialchars($p['name']); ?>"></div>
                    <?php endforeach; ?>

                    <?php foreach ($all_barriers as $b): 
                        $bd = json_decode($b['barrier_data'], true);
                        $bl = ($bd['x'] / $mapWidth) * 100;
                        $bt = ($bd['y'] / $mapHeight) * 100;
                        $bw = ($bd['w'] / $mapWidth) * 100;
                        $bh = ($bd['h'] / $mapHeight) * 100;
                    ?>
                        <div class="barrier-rect" style="left: <?php echo $bl; ?>%; top: <?php echo $bt; ?>%; width: <?php echo $bw; ?>%; height: <?php echo $bh; ?>%;"></div>
                    <?php endforeach; ?>
                    <div class="selection-rect" id="selectionRect" style="display:none;"></div>
                </div>
            </div>
            <p style="font-size: 0.8rem; color: rgba(255,255,255,0.5); margin-top: 10px;">
                * Nuqtalar qo'lda belgilanadi va navigatsiya uchun ishlatiladi. To'siqlar esa o'tib bo'lmaydigan zonalar.
            </p>
        </div>

        <!-- Custom Confirm Modal -->
        <div class="confirm-modal" id="confirmModal">
            <div class="confirm-box">
                <div class="confirm-icon"><i class="fas fa-exclamation-circle" style="text-shadow: 0 0 20px rgba(255,82,82,0.5);"></i></div>
                <div class="confirm-text">Tanlangan ma'lumotni haqiqatan ham o'chirmoqchimisiz? <br><small style="opacity: 0.5;">Bu amalni bekor qilib bo'lmaydi.</small></div>
                <div class="confirm-actions">
                    <button class="btn-cancel" onclick="closeConfirmModal()">Yo'q, adashdim</button>
                    <a href="#" class="btn-confirm" id="confirmBtn">Ha, o'chirish</a>
                </div>
            </div>
        </div>
    </main>

    <script>
        function confirmDelete(url) {
            document.getElementById('confirmBtn').href = url;
            document.getElementById('confirmModal').classList.add('active');
            return false;
        }

        function closeConfirmModal() {
            document.getElementById('confirmModal').classList.remove('active');
            setTimeout(() => {
                document.getElementById('confirmBtn').href = "#";
            }, 300);
        }

        const mapPreview = document.getElementById('mapPreview');
        const mapStage = document.getElementById('mapStage');
        const selectionRect = document.getElementById('selectionRect');
        const coordReadout = document.getElementById('coordReadout');
        const mapWidth = parseInt(mapPreview.dataset.mapWidth, 10) || 1000;
        const mapHeight = parseInt(mapPreview.dataset.mapHeight, 10) || 800;

        const modeSelectBtn = document.getElementById('modeSelect');
        const modePanBtn = document.getElementById('modePan');
        const modeDrawBtn = document.getElementById('modeDraw');
        const zoomInBtn = document.getElementById('zoomIn');
        const zoomOutBtn = document.getElementById('zoomOut');
        const zoomResetBtn = document.getElementById('zoomReset');

        let scale = 1;
        let panX = 0;
        let panY = 0;
        let mode = 'select';
        let isPanning = false;
        let isDrawing = false;
        let drawStart = null;
        let lastPointer = null;

        function setMode(nextMode) {
            mode = nextMode;
            modeSelectBtn.classList.toggle('active', mode === 'select');
            modePanBtn.classList.toggle('active', mode === 'pan');
            modeDrawBtn.classList.toggle('active', mode === 'draw');
            mapPreview.classList.toggle('mode-pan', mode === 'pan');
            mapPreview.classList.toggle('mode-draw', mode === 'draw');
        }

        function applyTransform() {
            clampPan();
            mapStage.style.transform = `translate(${panX}px, ${panY}px) scale(${scale})`;
        }

        function clampPan() {
            const rect = mapPreview.getBoundingClientRect();
            const stageW = mapWidth * scale;
            const stageH = mapHeight * scale;

            if (stageW <= rect.width) {
                panX = (rect.width - stageW) / 2;
            } else {
                const minX = rect.width - stageW;
                panX = Math.min(0, Math.max(minX, panX));
            }

            if (stageH <= rect.height) {
                panY = (rect.height - stageH) / 2;
            } else {
                const minY = rect.height - stageH;
                panY = Math.min(0, Math.max(minY, panY));
            }
        }

        function fitToView() {
            const rect = mapPreview.getBoundingClientRect();
            const scaleX = rect.width / mapWidth;
            const scaleY = rect.height / mapHeight;
            scale = Math.min(scaleX, scaleY, 1);
            panX = (rect.width - mapWidth * scale) / 2;
            panY = (rect.height - mapHeight * scale) / 2;
            applyTransform();
        }

        function eventToMapCoords(e) {
            const rect = mapPreview.getBoundingClientRect();
            const x = (e.clientX - rect.left - panX) / scale;
            const y = (e.clientY - rect.top - panY) / scale;
            const clampedX = Math.max(0, Math.min(mapWidth, x));
            const clampedY = Math.max(0, Math.min(mapHeight, y));
            return { x: clampedX, y: clampedY };
        }

        modeSelectBtn.addEventListener('click', () => setMode('select'));
        modePanBtn.addEventListener('click', () => setMode('pan'));
        modeDrawBtn.addEventListener('click', () => setMode('draw'));

        zoomInBtn.addEventListener('click', () => {
            scale = Math.min(8, scale * 1.3);
            applyTransform();
        });
        zoomOutBtn.addEventListener('click', () => {
            scale = Math.max(0.1, scale / 1.3);
            applyTransform();
        });
        zoomResetBtn.addEventListener('click', fitToView);

        mapPreview.addEventListener('wheel', (e) => {
            e.preventDefault();
            const rect = mapPreview.getBoundingClientRect();
            const cx = e.clientX - rect.left;
            const cy = e.clientY - rect.top;
            const mx = (cx - panX) / scale;
            const my = (cy - panY) / scale;

            const zoomFactor = e.deltaY < 0 ? 1.25 : 0.8;
            scale = Math.max(0.1, Math.min(8, scale * zoomFactor));
            panX = cx - mx * scale;
            panY = cy - my * scale;
            applyTransform();
        }, { passive: false });

        mapPreview.addEventListener('pointermove', (e) => {
            const coords = eventToMapCoords(e);
            coordReadout.textContent = `x: ${Math.round(coords.x)}, y: ${Math.round(coords.y)}`;

            if (isPanning && lastPointer) {
                panX += e.clientX - lastPointer.x;
                panY += e.clientY - lastPointer.y;
                lastPointer = { x: e.clientX, y: e.clientY };
                applyTransform();
            }

            if (isDrawing && drawStart) {
                const current = coords;
                const x = Math.min(drawStart.x, current.x);
                const y = Math.min(drawStart.y, current.y);
                const w = Math.abs(drawStart.x - current.x);
                const h = Math.abs(drawStart.y - current.y);
                selectionRect.style.display = 'block';
                selectionRect.style.left = `${x}px`;
                selectionRect.style.top = `${y}px`;
                selectionRect.style.width = `${w}px`;
                selectionRect.style.height = `${h}px`;
            }
        });

        mapPreview.addEventListener('pointerdown', (e) => {
            if (e.button !== 0) return;
            mapPreview.setPointerCapture(e.pointerId);

            if (mode === 'pan') {
                isPanning = true;
                lastPointer = { x: e.clientX, y: e.clientY };
            } else if (mode === 'draw') {
                isDrawing = true;
                drawStart = eventToMapCoords(e);
                selectionRect.style.display = 'block';
                selectionRect.style.left = `${drawStart.x}px`;
                selectionRect.style.top = `${drawStart.y}px`;
                selectionRect.style.width = '0px';
                selectionRect.style.height = '0px';
            }
        });

        mapPreview.addEventListener('pointerup', (e) => {
            if (isPanning) {
                isPanning = false;
                lastPointer = null;
            }

            if (isDrawing) {
                const end = eventToMapCoords(e);
                const x = Math.min(drawStart.x, end.x);
                const y = Math.min(drawStart.y, end.y);
                const w = Math.abs(drawStart.x - end.x);
                const h = Math.abs(drawStart.y - end.y);

                if (w > 2 && h > 2) {
                    document.getElementsByName('bx')[0].value = Math.round(x);
                    document.getElementsByName('by')[0].value = Math.round(y);
                    document.getElementsByName('bw')[0].value = Math.round(w);
                    document.getElementsByName('bh')[0].value = Math.round(h);
                }

                selectionRect.style.display = 'none';
                isDrawing = false;
                drawStart = null;
            }

            mapPreview.releasePointerCapture(e.pointerId);
        });

        mapPreview.addEventListener('pointerleave', () => {
            isPanning = false;
            lastPointer = null;
            if (isDrawing) {
                selectionRect.style.display = 'none';
                isDrawing = false;
                drawStart = null;
            }
        });

        mapPreview.addEventListener('pointercancel', () => {
            isPanning = false;
            lastPointer = null;
            if (isDrawing) {
                selectionRect.style.display = 'none';
                isDrawing = false;
                drawStart = null;
            }
        });

        mapPreview.addEventListener('click', (e) => {
            if (mode !== 'select') return;
            const coords = eventToMapCoords(e);
            document.getElementsByName('pos_x')[0].value = Math.round(coords.x);
            document.getElementsByName('pos_y')[0].value = Math.round(coords.y);
        });

        window.addEventListener('resize', fitToView);
        window.addEventListener('load', fitToView);
        setMode('select');
        fitToView();
    </script>
</body>
</html>

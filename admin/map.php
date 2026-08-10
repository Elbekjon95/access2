<?php
require_once 'classes/AdminPage.php';
$page = new AdminPage("Xarita", "map");

$msg = "";
$map_id = 1; 
$mapWidth = 16700;
$mapHeight = 11813;

function isEnglishLabel($text) {
    return preg_match('/^[A-Za-z0-9][A-Za-z0-9 .,#()\\-_\\/]*$/', $text) === 1;
}

$db = $page->getDb();

// Action: ADD point
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_point'])) {
    $name = $_POST['name'];
    $type = $_POST['type'];
    $pos_x = (float)$_POST['pos_x'];
    $pos_y = (float)$_POST['pos_y'];
    
    if (!isEnglishLabel($name)) {
        $msg = "Xato: Nom faqat inglizcha bo'lishi kerak (A-Z, 0-9, bo'shliq va .,#()-_/).";
    } else {
        try {
            $db->insertOne('map_points', [
                'map_id' => $map_id,
                'name' => $name,
                'type' => $type,
                'pos_x' => $pos_x,
                'pos_y' => $pos_y,
                'created_at' => date('Y-m-d H:i:s')
            ]);
            $msg = "Nuqta qo'shildi!";
        } catch (Throwable $e) { $msg = "Xato: " . $e->getMessage(); }
    }
}

// Action: DELETE point
if (isset($_GET['delete_point'])) {
    if (!verifyCsrfToken($_GET['csrf_token'] ?? '')) die("CSRF Xato");
    $db->deleteOne('map_points', ['id' => $_GET['delete_point']]);
    header("Location: map.php");
    exit;
}

// Action: ADD barrier
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_barrier'])) {
    $bx = (int)$_POST['bx']; $by = (int)$_POST['by']; $bw = (int)$_POST['bw']; $bh = (int)$_POST['bh'];
    $data = json_encode(['x' => $bx, 'y' => $by, 'w' => $bw, 'h' => $bh]);
    try {
        $db->insertOne('map_barriers', [
            'map_id' => $map_id,
            'barrier_data' => $data,
            'created_at' => date('Y-m-d H:i:s')
        ]);
        $msg = "To'siq qo'shildi!";
    } catch (Throwable $e) { $msg = "Xato: " . $e->getMessage(); }
}

// Action: DELETE barrier
if (isset($_GET['delete_barrier'])) {
    if (!verifyCsrfToken($_GET['csrf_token'] ?? '')) die("CSRF Xato");
    $db->deleteOne('map_barriers', ['id' => $_GET['delete_barrier']]);
    header("Location: map.php");
    exit;
}

$all_points = $db->find('map_points', [
    '$or' => [['map_id' => (int)$map_id], ['map_id' => (string)$map_id]]
], ['sort' => ['created_at' => -1, '_id' => -1]]);

$all_barriers = $db->find('map_barriers', [
    '$or' => [['map_id' => (int)$map_id], ['map_id' => (string)$map_id]]
], ['sort' => ['created_at' => -1, '_id' => -1]]);

$extra_head = '
    <style>
        .map-tools { display: flex; flex-wrap: wrap; gap: 10px; align-items: center; margin-bottom: 15px; background: rgba(255,255,255,0.03); padding: 15px; border-radius: 12px; }
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
        .btn-cancel { background: rgba(255,255,255,0.1); color: white; border: none; padding: 10px 20px; border-radius: 8px; cursor: pointer; transition: 0.2s; font-family: \'Outfit\', sans-serif;}
        .btn-cancel:hover { background: rgba(255,255,255,0.2); }
        .btn-confirm { background: #ff5252; color: white; text-decoration: none; display: inline-block; padding: 10px 20px; border-radius: 8px; cursor: pointer; transition: 0.2s; font-family: \'Outfit\', sans-serif;}
        .btn-confirm:hover { background: #ff1f1f; }
    </style>
';

$page->renderHeader($extra_head);
$page->renderSidebar();
?>

<h1>Xarita Ma'lumotlarini Boshqarish</h1>

<?php if ($msg): ?>
    <div style="background: rgba(0,198,255,0.1); border: 1px solid var(--accent); color: var(--accent); padding: 15px; border-radius: 12px; margin-bottom: 20px;">
        <?php echo $msg; ?>
    </div>
<?php endif; ?>

<div class="card">
    <h3>Yangi Nuqta qo'shish</h3>
    <form method="POST" style="display: flex; flex-wrap: wrap; gap: 10px;">
        <?php $page->csrfField(); ?>
        <input type="text" name="name" placeholder="Nuqta nomi (inglizcha)" required style="background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.2); padding: 10px; border-radius: 8px; color: white;">
        <select name="type" style="background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.2); padding: 10px; border-radius: 8px; color: white;">
            <option value="gate">Geyt (Gate)</option>
            <option value="fids">FIDS (Tablo)</option>
            <option value="toilet">Hojatxona (Toilet)</option>
            <option value="reception">Reception / Information</option>
            <option value="luggage">Bagaj (Luggage)</option>
            <option value="exit">Chiqish</option>
            <option value="mosque">Mosque (Masjid)</option>
            <option value="cip">CIP Zone</option>
            <option value="vip_lounge">VIP Lounge</option>
            <option value="counter">Stoyka (Counter)</option>
            <option value="kiosk_start">Kiosk Start</option>
            <option value="other">Boshqa</option>
        </select>
        <input type="number" name="pos_x" placeholder="X" required style="width: 100px; background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.2); padding: 10px; border-radius: 8px; color: white;">
        <input type="number" name="pos_y" placeholder="Y" required style="width: 100px; background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.2); padding: 10px; border-radius: 8px; color: white;">
        <button type="submit" name="add_point" class="btn">QO'SHISH</button>
    </form>
</div>

<div class="card">
    <h3>Yangi To'siq (Barrier) qo'shish</h3>
    <form method="POST" style="display: flex; flex-wrap: wrap; gap: 10px;">
        <?php $page->csrfField(); ?>
        <input type="number" name="bx" placeholder="X" required style="width: 100px; background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.2); padding: 10px; border-radius: 8px; color: white;">
        <input type="number" name="by" placeholder="Y" required style="width: 100px; background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.2); padding: 10px; border-radius: 8px; color: white;">
        <input type="number" name="bw" placeholder="Eni" required style="width: 100px; background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.2); padding: 10px; border-radius: 8px; color: white;">
        <input type="number" name="bh" placeholder="Bo'yi" required style="width: 100px; background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.2); padding: 10px; border-radius: 8px; color: white;">
        <button type="submit" name="add_barrier" class="btn" style="background: #ff5252; color: white;">TO'SIQ QO'SHISH</button>
    </form>
</div>

<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem;">
    <div class="card">
        <h3>Xaritadagi Nuqtalar</h3>
        <table>
            <thead><tr><th>Nomi</th><th>X/Y</th><th>Amallar</th></tr></thead>
            <tbody>
                <?php foreach ($all_points as $p): ?>
                <tr>
                    <td><?php echo htmlspecialchars($p['name']); ?></td>
                    <td><?php echo "{$p['pos_x']}, {$p['pos_y']}"; ?></td>
                    <td>
                        <a href="?delete_point=<?php echo $p['id']; ?>&csrf_token=<?php echo getCsrfToken(); ?>" style="color: #ff5252;" onclick="return confirmDelete(this.href)"><i class="fas fa-trash"></i></a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <div class="card">
        <h3>To'siqlar Ro'yxati</h3>
        <table>
            <thead><tr><th>X, Y, W, H</th><th>Amallar</th></tr></thead>
            <tbody>
                <?php foreach ($all_barriers as $b): $bd = json_decode($b['barrier_data'], true); ?>
                <tr>
                    <td><?php echo "{$bd['x']}, {$bd['y']}, {$bd['w']}, {$bd['h']}"; ?></td>
                    <td>
                        <a href="?delete_barrier=<?php echo $b['id']; ?>&csrf_token=<?php echo getCsrfToken(); ?>" style="color: #ff5252;" onclick="return confirmDelete(this.href)"><i class="fas fa-trash"></i></a>
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
        <button type="button" class="tool-btn" id="modeDraw">To'siq</button>
        <button type="button" class="tool-btn" id="zoomIn">+</button>
        <button type="button" class="tool-btn" id="zoomOut">-</button>
        <button type="button" class="tool-btn" id="zoomReset">Reset</button>
        <div class="coord-readout" id="coordReadout">x: -, y: -</div>
    </div>
    <div class="map-preview mode-select" id="mapPreview" data-map-width="<?php echo $mapWidth; ?>" data-map-height="<?php echo $mapHeight; ?>">
        <div class="map-stage" id="mapStage" style="width: <?php echo $mapWidth; ?>px; height: <?php echo $mapHeight; ?>px;">
            <picture>
                <source srcset="../img/airport_map_opt.webp" type="image/webp">
                <img id="mapImage" src="../img/airport_map_opt.jpg" alt="Map" width="<?php echo $mapWidth; ?>" height="<?php echo $mapHeight; ?>" draggable="false">
            </picture>
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
</div>

<div class="confirm-modal" id="confirmModal">
    <div class="confirm-box">
        <div class="confirm-icon"><i class="fas fa-exclamation-circle"></i></div>
        <div class="confirm-text">Muvaffaqiyatli o'chirilsinmi?</div>
        <div class="confirm-actions">
            <button class="btn-cancel" onclick="closeConfirmModal()">Bekor qilish</button>
            <a href="#" class="btn-confirm" id="confirmBtn">Ha, o'chirish</a>
        </div>
    </div>
</div>

<script>
    function confirmDelete(url) {
        document.getElementById('confirmBtn').href = url;
        document.getElementById('confirmModal').classList.add('active');
        return false;
    }
    function closeConfirmModal() {
        document.getElementById('confirmModal').classList.remove('active');
    }

    const mapPreview = document.getElementById('mapPreview');
    const mapStage = document.getElementById('mapStage');
    const selectionRect = document.getElementById('selectionRect');
    const coordReadout = document.getElementById('coordReadout');
    const mapWidth = <?php echo $mapWidth; ?>;
    const mapHeight = <?php echo $mapHeight; ?>;

    let scale = 1; let panX = 0; let panY = 0; let mode = 'select';
    let isPanning = false; let isDrawing = false; let drawStart = null; let lastPointer = null;

    function applyTransform() {
        clampPan();
        mapStage.style.transform = `translate(${panX}px, ${panY}px) scale(${scale})`;
    }
    function clampPan() {
        const rect = mapPreview.getBoundingClientRect();
        const stageW = mapWidth * scale; const stageH = mapHeight * scale;
        if (stageW <= rect.width) panX = (rect.width - stageW) / 2;
        else panX = Math.min(0, Math.max(rect.width - stageW, panX));
        if (stageH <= rect.height) panY = (rect.height - stageH) / 2;
        else panY = Math.min(0, Math.max(rect.height - stageH, panY));
    }
    function fitToView() {
        const rect = mapPreview.getBoundingClientRect();
        scale = Math.min(rect.width / mapWidth, rect.height / mapHeight, 1);
        panX = (rect.width - mapWidth * scale) / 2;
        panY = (rect.height - mapHeight * scale) / 2;
        applyTransform();
    }
    function eventToMapCoords(e) {
        const rect = mapPreview.getBoundingClientRect();
        const x = (e.clientX - rect.left - panX) / scale;
        const y = (e.clientY - rect.top - panY) / scale;
        return { x: Math.max(0, Math.min(mapWidth, x)), y: Math.max(0, Math.min(mapHeight, y)) };
    }

    document.getElementById('modeSelect').onclick = () => { mode = 'select'; updateModeUI(); };
    document.getElementById('modePan').onclick = () => { mode = 'pan'; updateModeUI(); };
    document.getElementById('modeDraw').onclick = () => { mode = 'draw'; updateModeUI(); };
    
    function updateModeUI() {
        document.querySelectorAll('.tool-btn').forEach(b => b.classList.remove('active'));
        document.getElementById('mode' + mode.charAt(0).toUpperCase() + mode.slice(1)).classList.add('active');
        mapPreview.className = 'map-preview mode-' + mode;
    }

    document.getElementById('zoomIn').onclick = () => { scale *= 1.3; applyTransform(); };
    document.getElementById('zoomOut').onclick = () => { scale /= 1.3; applyTransform(); };
    document.getElementById('zoomReset').onclick = fitToView;

    mapPreview.onpointerdown = (e) => {
        if (e.button !== 0) return;
        mapPreview.setPointerCapture(e.pointerId);
        if (mode === 'pan') { isPanning = true; lastPointer = {x: e.clientX, y: e.clientY}; }
        else if (mode === 'draw') { isDrawing = true; drawStart = eventToMapCoords(e); selectionRect.style.display = 'block'; }
    };
    mapPreview.onpointermove = (e) => {
        const coords = eventToMapCoords(e);
        coordReadout.textContent = `x: ${Math.round(coords.x)}, y: ${Math.round(coords.y)}`;
        if (isPanning) { panX += e.clientX - lastPointer.x; panY += e.clientY - lastPointer.y; lastPointer = {x: e.clientX, y: e.clientY}; applyTransform(); }
        if (isDrawing) {
            const x = Math.min(drawStart.x, coords.x); const y = Math.min(drawStart.y, coords.y);
            const w = Math.abs(drawStart.x - coords.x); const h = Math.abs(drawStart.y - coords.y);
            selectionRect.style.left = x + 'px'; selectionRect.style.top = y + 'px';
            selectionRect.style.width = w + 'px'; selectionRect.style.height = h + 'px';
        }
    };
    mapPreview.onpointerup = (e) => {
        if (isDrawing) {
            const end = eventToMapCoords(e);
            document.getElementsByName('bx')[0].value = Math.round(Math.min(drawStart.x, end.x));
            document.getElementsByName('by')[0].value = Math.round(Math.min(drawStart.y, end.y));
            document.getElementsByName('bw')[0].value = Math.round(Math.abs(drawStart.x - end.x));
            document.getElementsByName('bh')[0].value = Math.round(Math.abs(drawStart.y - end.y));
            selectionRect.style.display = 'none';
        }
        isPanning = false; isDrawing = false;
        mapPreview.releasePointerCapture(e.pointerId);
    };
    mapPreview.onclick = (e) => {
        if (mode !== 'select') return;
        const coords = eventToMapCoords(e);
        document.getElementsByName('pos_x')[0].value = Math.round(coords.x);
        document.getElementsByName('pos_y')[0].value = Math.round(coords.y);
    };
    window.onresize = fitToView;
    window.onload = fitToView;
</script>

<?php $page->renderFooter(); ?>

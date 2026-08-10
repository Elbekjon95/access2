<?php
require_once 'classes/AdminPage.php';
$page = new AdminPage("JSON Import", "json_import");

$db = $page->getDb();
$points = $db->find('map_points', [], ['sort' => ['type' => 1, 'name' => 1]]);

$page->renderHeader('
    <style>
        textarea { width: 100%; min-height: 300px; background: rgba(0,0,0,0.3); border: 1px solid rgba(255,255,255,0.2); color: white; padding: 15px; border-radius: 8px; font-family: \'Courier New\', monospace; font-size: 0.9rem; }
        .status { padding: 10px 15px; border-radius: 8px; margin: 10px 0; }
        .status.success { background: rgba(0,255,136,0.1); color: #00ff88; border: 1px solid #00ff88; }
        .status.error { background: rgba(255,82,82,0.1); color: #ff5252; border: 1px solid #ff5252; }
        th { color: var(--accent); font-size: 0.8rem; text-transform: uppercase; }
        .badge { padding: 4px 10px; border-radius: 5px; font-size: 0.75rem; }
        .badge-gate { background: #0072ff; }
        .badge-fids { background: #d5a107; }
        .badge-toilet { background: #00c853; }
        .badge-reception { background: #ff5252; }
    </style>
');
$page->renderSidebar();
?>

<h1>📥 JSON Import - Xarita Nuqtalari</h1>

<div class="card">
    <h3>📝 JSON Fayl Ko'rinishi</h3>
    <p style="color: rgba(255,255,255,0.7);">
        <code>data/map_points.json</code> faylini tahrirlang va quyidagi tugmani bosing.
    </p>
    
    <textarea id="jsonPreview" readonly>{
  "airport_name": "TAS Aerovokzal",
  "map_points": [
    {"name": "Gate B2", "type": "gate", "pos_x": 550, "pos_y": 300},
    {"name": "FIDS 1", "type": "fids", "pos_x": 300, "pos_y": 150},
    {"name": "Toilet", "type": "toilet", "pos_x": 200, "pos_y": 400}
  ]
}</textarea>
    
    <button class="btn" onclick="importJSON()" style="margin-top: 20px;">
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

<script>
async function importJSON() {
    const statusDiv = document.getElementById('importStatus');
    statusDiv.innerHTML = '<div class="status">Yuklanmoqda...</div>';
    
    try {
        const response = await fetch('../api/import_json.php', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': '<?php echo getCsrfToken(); ?>'
            }
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
                    ❌ Xato: ${result.error}
                </div>
            `;
        }
    } catch (err) {
        statusDiv.innerHTML = `<div class="status error">❌ Xato: ${err.message}</div>`;
    }
}
</script>

<?php $page->renderFooter(); ?>

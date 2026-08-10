<?php
require_once 'classes/AdminPage.php';
$page = new AdminPage("Chat Tarixi", "chats");

$db = $page->getDb();
$chats = $db->find('chats', [], [
    'sort' => ['created_at' => -1, '_id' => -1]
]);

foreach ($chats as &$chat) {
    $chat['image_path'] = null;
    if (!empty($chat['capture_id'])) {
        $capture = $db->findOne('customer_captures', ['id' => $chat['capture_id']]);
        if ($capture && !empty($capture['image_path'])) {
            $chat['image_path'] = $capture['image_path'];
        }
    }
}

$page->renderHeader();
$page->renderSidebar();
?>
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
<?php $page->renderFooter(); ?>

<?php
require_once 'classes/AdminPage.php';
$page = new AdminPage("Shikoyatlar", "complaints");

$db = $page->getDb();
$complaints = $db->find('complaints', [], [
    'sort' => ['created_at' => -1, '_id' => -1]
]);

$page->renderHeader();
$page->renderSidebar();
?>
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
<?php $page->renderFooter(); ?>

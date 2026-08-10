<?php
require_once 'classes/AdminPage.php';
$page = new AdminPage("Adminlar", "users");

$msg = "";
$db = $page->getDb();

// User Action handling: ADD
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_user'])) {
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
        $msg = "Foydalanuvchi muvaffaqiyatli qo'shildi!";
    } catch (Throwable $e) {
        $msg = "Xato: " . $e->getMessage();
    }
}

// User Action handling: EDIT
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['edit_user'])) {
    $user_id = $_POST['user_id'];
    $full_name = $_POST['full_name'];
    $username = $_POST['username'];
    
    try {
        $updateData = [
            'full_name' => $full_name,
            'username' => $username
        ];
        if (!empty($_POST['password'])) {
            $updateData['password'] = password_hash($_POST['password'], PASSWORD_DEFAULT);
        }
        $db->updateOne('users', ['id' => $user_id], $updateData);
        $msg = "Foydalanuvchi ma'lumotlari yangilandi!";
    } catch (Throwable $e) {
        $msg = "Xato: " . $e->getMessage();
    }
}

// User Action handling: DELETE
if (isset($_GET['delete'])) {
    if (!verifyCsrfToken($_GET['csrf_token'] ?? '')) {
        die("CSRF xatoligi! So'rov xavfsiz emas.");
    }
    $user_id = $_GET['delete'];
    // O'zini o'zi o'chira olmasligi kerak
    if ($user_id == $_SESSION['admin_id']) {
        $msg = "Xato: O'zingizni o'chira olmaysiz!";
    } else {
        $db->deleteOne('users', ['id' => $user_id]);
        $msg = "Foydalanuvchi o'chirildi!";
    }
}

$edit_user = null;
if (isset($_GET['edit'])) {
    $edit_user = $db->findOne('users', ['id' => $_GET['edit']]);
}

// Get all users
$users = $db->find('users', [], ['sort' => ['created_at' => -1, '_id' => -1]]);

$page->renderHeader('
<style>
    form { margin-bottom: 2rem; background: rgba(255,255,255,0.02); padding: 1.5rem; border-radius: 15px; border: 1px solid rgba(255,255,255,0.1); }
    input { background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.2); padding: 12px; border-radius: 8px; color: white; margin-right: 10px; margin-bottom: 10px; }
    .btn-del { color: #ff5252; text-decoration: none; font-size: 1.2rem; }
</style>
');
$page->renderSidebar();
?>

<h1>Adminlarni boshqarish</h1>

<?php if ($msg): ?>
    <div style="background: rgba(0,255,136,0.1); color: #00ff88; padding: 15px; border-radius: 10px; margin-bottom: 20px; border: 1px solid #00ff88;">
        <?php echo $msg; ?>
    </div>
<?php endif; ?>

<div class="card">
    <h3><?php echo $edit_user ? 'Tahrirlash' : 'Yangi admin qo\'shish'; ?></h3>
    <form action="users.php" method="POST">
        <?php $page->csrfField(); ?>
        <?php if ($edit_user): ?>
            <input type="hidden" name="user_id" value="<?php echo $edit_user['id']; ?>">
            <input type="hidden" name="edit_user" value="1">
        <?php else: ?>
            <input type="hidden" name="add_user" value="1">
        <?php endif; ?>
        
        <input type="text" name="full_name" placeholder="To'liq ism" value="<?php echo $edit_user ? htmlspecialchars($edit_user['full_name']) : ''; ?>" required>
        <input type="text" name="username" placeholder="Login" value="<?php echo $edit_user ? htmlspecialchars($edit_user['username']) : ''; ?>" required>
        <input type="password" name="password" placeholder="<?php echo $edit_user ? 'Parolni o\'zgartirmaslik uchun bo\'sh qoldiring' : 'Parol'; ?>" <?php echo $edit_user ? '' : 'required'; ?>>
        
        <button type="submit" class="btn"><?php echo $edit_user ? 'Saqlash' : 'Qo\'shish'; ?></button>
        <?php if ($edit_user): ?>
            <a href="users.php" style="color: #aaa; margin-left: 10px;">Bekor qilish</a>
        <?php endif; ?>
    </form>
</div>

<div class="card">
    <h3>Mavjud adminlar</h3>
    <table>
        <thead>
            <tr>
                <th>F.I.SH</th>
                <th>Login</th>
                <th>Roli</th>
                <th>Amallar</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($users as $u): ?>
            <tr>
                <td><?php echo htmlspecialchars($u['full_name']); ?></td>
                <td><?php echo htmlspecialchars($u['username']); ?></td>
                <td><span class="status-badge" style="background: rgba(0,198,255,0.2); color: #00c6ff;"><?php echo strtoupper($u['role']); ?></span></td>
                <td>
                    <a href="?edit=<?php echo $u['id']; ?>" style="color: #00c6ff; margin-right: 15px;"><i class="fas fa-edit"></i></a>
                    <?php if ($u['id'] != $_SESSION['admin_id']): ?>
                        <a href="?delete=<?php echo $u['id']; ?>&csrf_token=<?php echo getCsrfToken(); ?>" class="btn-del" onclick="return confirm('Haqiqatan ham o\'chirmoqchimisiz?')"><i class="fas fa-trash"></i></a>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php $page->renderFooter(); ?>

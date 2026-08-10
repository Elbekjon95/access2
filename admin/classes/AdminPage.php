<?php

class AdminPage {
    protected $pdo;
    protected $title = "ACSESS Admin";
    protected $active_nav = "dashboard";

    public function __construct($title = "ACSESS Admin", $active_nav = "dashboard") {
        require_once __DIR__ . '/../../config.php';
        secureSessionStart();
        
        $this->pdo = getDbConnection();
        $this->title = $title;
        $this->active_nav = $active_nav;

        $this->checkAuth();
        $this->handleCsrf();
    }

    public function getDb() {
        return $this->pdo;
    }

    protected function checkAuth() {
        if (!isset($_SESSION['admin_id'])) {
            header("Location: login.php");
            exit;
        }
    }

    protected function handleCsrf() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
                die("CSRF xatoligi! So'rov xavfsiz emas.");
            }
        }
    }

    public function renderHeader($extra_head = "") {
        ?>
<!DOCTYPE html>
<html lang="uz">
<head>
    <meta charset="UTF-8">
    <title><?php echo htmlspecialchars($this->title); ?> - ACSESS</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <?php echo $extra_head; ?>
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
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 15px; text-align: left; border-bottom: 1px solid rgba(255,255,255,0.1); }
        .status-badge { padding: 4px 12px; border-radius: 4px; font-size: 0.75rem; }
    </style>
</head>
<body>
        <?php
    }

    public function renderSidebar() {
        $navs = [
            'dashboard' => ['url' => 'index.php', 'icon' => 'fas fa-chart-line', 'label' => 'Dashboard'],
            'chats' => ['url' => 'chats.php', 'icon' => 'fas fa-comments', 'label' => 'Chat Tarixi'],
            'complaints' => ['url' => 'complaints.php', 'icon' => 'fas fa-exclamation-circle', 'label' => 'Shikoyatlar'],
            'map' => ['url' => 'map.php', 'icon' => 'fas fa-map-marked-alt', 'label' => 'Xarita'],
            'json_import' => ['url' => 'json_import.php', 'icon' => 'fas fa-file-import', 'label' => 'JSON Import'],
            'earth' => ['url' => 'earth.php', 'icon' => 'fas fa-globe', 'label' => '3D Yer'],
            'users' => ['url' => 'users.php', 'icon' => 'fas fa-users', 'label' => 'Adminlar'],
        ];
        ?>
    <aside>
        <div class="logo">ACSESS ADMIN</div>
        <nav>
            <?php foreach ($navs as $key => $nav): ?>
                <a href="<?php echo $nav['url']; ?>" class="<?php echo $this->active_nav == $key ? 'active' : ''; ?>">
                    <i class="<?php echo $nav['icon']; ?>"></i> <?php echo $nav['label']; ?>
                </a>
            <?php endforeach; ?>
            <hr style="border: 0; border-top: 1px solid rgba(255,255,255,0.1); margin: 15px 0;">
            <a href="../index.php" style="color: var(--accent);"><i class="fas fa-external-link-alt"></i> Kioskga o'tish</a>
            <a href="logout.php" style="margin-top: 20px; color: #ff5252;"><i class="fas fa-sign-out-alt"></i> Chiqish</a>
        </nav>
    </aside>
    <main>
        <?php
    }

    public function renderFooter() {
        ?>
    </main>
</body>
</html>
        <?php
    }

    public function csrfField() {
        echo '<input type="hidden" name="csrf_token" value="' . getCsrfToken() . '">';
    }
}

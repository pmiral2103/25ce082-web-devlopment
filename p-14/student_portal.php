<?php
require_once "auth_helper.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php?error=unauthorized");
    exit();
}

$role = $_SESSION['role'];
$menu_items = get_role_menu($role);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>StudentHub - Portal (<?php echo htmlspecialchars($role); ?>)</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <header id="header">
        <img src="image/charusatlogo.png" alt="CHARUSAT Logo">
        <div>StudentHub Portal - Role: <?php echo htmlspecialchars($role); ?></div>
        <nav>
            <?php foreach ($menu_items as $item): ?>
                <a href="<?php echo htmlspecialchars($item['url']); ?>">
                    <?php echo htmlspecialchars($item['label']); ?>
                </a>
            <?php endforeach; ?>
            <span class="user-badge-header" style="background-color: <?php echo $role === 'Faculty' ? '#059669' : '#2563eb'; ?>;">
                <?php echo htmlspecialchars($_SESSION['full_name']); ?> (<?php echo htmlspecialchars($role); ?>)
            </span>
            <a href="logout.php" class="btn-danger" style="margin-left: 10px; padding: 4px 10px; font-size: 13px;">Logout</a>
        </nav>
    </header>

    <main class="main-content">
        <section class="container">
            <?php if (isset($_GET['error']) && $_GET['error'] === 'forbidden'): ?>
                <div class="alert-error">
                    &cross; Access Denied by RBAC: Your current role is <strong><?php echo htmlspecialchars($role); ?></strong>. You do NOT have Administrator permissions to access the Admin Dashboard. This unauthorized access attempt was logged.
                </div>
            <?php endif; ?>

            <?php if (isset($_GET['login']) && $_GET['login'] === 'success'): ?>
                <div class="alert-success">
                    &check; Welcome, <?php echo htmlspecialchars($_SESSION['full_name']); ?>! Logged in as <strong><?php echo htmlspecialchars($role); ?></strong>.
                </div>
            <?php endif; ?>

            <div class="demo-role-bar">
                <span>Current Role: <strong><?php echo htmlspecialchars($role); ?></strong> | Notice the dynamic navigation menu above adjusted specifically for your role.</span>
                <div style="display: flex; gap: 8px;">
                    <a href="index.php">Try Accessing Admin Dashboard &rarr;</a>
                    <a href="login.php?quick_login=Admin">Switch to ADMIN Account</a>
                </div>
            </div>

            <h2>User Portal Overview</h2>
            <div class="info-box">
                This portal demonstrates Dynamic Menu Generation and RBAC enforcement. As a <code><?php echo htmlspecialchars($role); ?></code>, you see restricted navigation options. If you click "Try Accessing Admin Dashboard" above, the system blocks access and triggers an audit log entry.
            </div>

            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-label">Your Username</div>
                    <div class="stat-number" style="font-size: 20px;"><?php echo htmlspecialchars($_SESSION['username']); ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Assigned Role</div>
                    <div class="stat-number" style="font-size: 20px; color: #2563eb;"><?php echo htmlspecialchars($role); ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Access Level</div>
                    <div class="stat-number" style="font-size: 20px; color: #d97706;">Restricted</div>
                </div>
            </div>
        </section>
    </main>

    <footer>
        MIRAL PATEL||25CE082||STUDENT HUB PORTAL
    </footer>
</body>
</html>

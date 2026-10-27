<?php
require_once "auth_helper.php";

require_login($conn);

$uid = $_SESSION['user_id'];
$user_info = null;

$stmt = $conn->prepare("SELECT id, username, email, full_name, mobile, role, last_login, created_at FROM users WHERE id = ?");
if ($stmt) {
    $stmt->bind_param("i", $uid);
    $stmt->execute();
    $res = $stmt->get_result();
    $user_info = $res->fetch_assoc();
    $stmt->close();
}

$events_count = 0;
$reg_res = $conn->query("SELECT COUNT(*) as total FROM registrations r JOIN students s ON r.student_id = s.id WHERE s.student_id_no = '25CE082'");
if ($reg_res) {
    $events_count = $reg_res->fetch_assoc()['total'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>StudentHub - Student Portal Dashboard</title>
    <link rel="stylesheet" href="style.css">
    <script>
        var remainingSeconds = 900;
        function updateTimer() {
            var m = Math.floor(remainingSeconds / 60);
            var s = remainingSeconds % 60;
            var display = (m < 10 ? "0" + m : m) + ":" + (s < 10 ? "0" + s : s);
            var elem = document.getElementById("timeout-counter");
            if (elem) {
                elem.innerText = display;
            }
            if (remainingSeconds <= 0) {
                window.location.href = "test_timeout.php";
            } else {
                remainingSeconds--;
            }
        }
        setInterval(updateTimer, 1000);
    </script>
</head>
<body>
    <header id="header">
        <img src="image/charusatlogo.png" alt="CHARUSAT Logo">
        <div>StudentHub Portal - Student Dashboard</div>
        <nav>
            <a href="student_dashboard.php" class="active">Student Portal</a>
            <?php if ($_SESSION['role'] === 'Admin'): ?>
                <a href="admin_dashboard.php">Admin Portal</a>
            <?php else: ?>
                <a href="admin_dashboard.php" title="Click to test unauthorized access blocking">Admin Portal (Restricted)</a>
            <?php endif; ?>
            <a href="setup_database.php">Setup DB</a>
            <span class="user-badge-header"><?php echo htmlspecialchars($_SESSION['full_name']); ?> (<?php echo htmlspecialchars($_SESSION['role']); ?>)</span>
            <a href="logout.php" class="btn-danger" style="margin-left: 10px; padding: 4px 10px; font-size: 13px;">Logout</a>
        </nav>
    </header>

    <main class="main-content">
        <section class="container">
            <?php if (isset($_GET['login']) && $_GET['login'] === 'success'): ?>
                <div class="alert-success">
                    &check; Authentication Successful! Password verified via <code>password_verify()</code> and session ID regenerated.
                </div>
            <?php endif; ?>

            <?php if (isset($_GET['error']) && $_GET['error'] === 'forbidden'): ?>
                <div class="alert-error">
                    &cross; Access Denied: Administrator privileges required to access Admin Dashboard! This unauthorized attempt was recorded in the security audit log.
                </div>
            <?php endif; ?>

            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; flex-wrap: wrap; gap: 10px;">
                <div>
                    <h2 style="margin: 0;">Welcome, <?php echo htmlspecialchars($user_info['full_name'] ? $user_info['full_name'] : $_SESSION['username']); ?>!</h2>
                    <span style="font-size: 13px; color: #475569;">Role: <strong class="badge badge-student"><?php echo htmlspecialchars($_SESSION['role']); ?></strong></span>
                </div>
                <div style="display: flex; gap: 8px;">
                    <a href="test_timeout.php" class="btn-warning" title="Simulate 15 min session timeout immediately for lab screenshot">Simulate Session Timeout</a>
                    <a href="logout.php" class="btn-danger">Logout Securely</a>
                </div>
            </div>

            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-label">Account Role</div>
                    <div class="stat-number" style="font-size: 20px;"><?php echo htmlspecialchars($_SESSION['role']); ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Session Inactivity Timeout</div>
                    <div class="stat-number" id="timeout-counter" style="color: #d97706;">15:00</div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Registered Events</div>
                    <div class="stat-number"><?php echo $events_count; ?></div>
                </div>
            </div>

            <div class="profile-card">
                <h3 style="color: #1E3A8A; margin-top: 0;">Profile Information</h3>
                <div class="profile-grid">
                    <div class="profile-item">
                        <div class="profile-label">Student Name</div>
                        <div class="profile-value"><?php echo htmlspecialchars($user_info['full_name']); ?></div>
                    </div>
                    <div class="profile-item">
                        <div class="profile-label">Username</div>
                        <div class="profile-value"><?php echo htmlspecialchars($user_info['username']); ?></div>
                    </div>
                    <div class="profile-item">
                        <div class="profile-label">Institutional Email</div>
                        <div class="profile-value"><?php echo htmlspecialchars($user_info['email']); ?></div>
                    </div>
                    <div class="profile-item">
                        <div class="profile-label">Mobile Number</div>
                        <div class="profile-value"><?php echo htmlspecialchars($user_info['mobile']); ?></div>
                    </div>
                    <div class="profile-item">
                        <div class="profile-label">Last Login Timestamp (Intermediate)</div>
                        <div class="profile-value" style="color: #10b981;"><?php echo htmlspecialchars($user_info['last_login'] ? $user_info['last_login'] : 'First Login'); ?></div>
                    </div>
                    <div class="profile-item">
                        <div class="profile-label">Member Since</div>
                        <div class="profile-value"><?php echo htmlspecialchars($user_info['created_at']); ?></div>
                    </div>
                </div>
            </div>

            <div class="info-box">
                <strong>Session Security Inspection for Viva:</strong>
                <ul style="margin: 5px 0 0 20px; padding: 0;">
                    <li><strong>Active Session ID:</strong> <code><?php echo htmlspecialchars(session_id()); ?></code> (Regenerated upon login via <code>session_regenerate_id(true)</code>)</li>
                    <li><strong>Session Inactivity Limit:</strong> 900 seconds (15 minutes of inactivity triggers automatic logout).</li>
                    <li><strong>Persistent Authentication:</strong> <?php echo !empty($_COOKIE['remember_token']) ? '<span style="color: green; font-weight: bold;">Remember-Me Token Active (SHA-256 Hash stored in DB)</span>' : '<span style="color: gray;">Standard Session Cookie</span>'; ?></li>
                    <li><strong>Role-Based Access:</strong> Only <code>Admin</code> accounts can access Admin Dashboard. Clicking "Admin Portal (Restricted)" above will demonstrate access block.</li>
                </ul>
            </div>
        </section>
    </main>

    <footer>
        MIRAL PATEL||25CE082||STUDENT HUB PORTAL
    </footer>
</body>
</html>

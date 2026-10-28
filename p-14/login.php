<?php
require_once "auth_helper.php";

$error = "";
$message = "";

if (isset($_GET['error'])) {
    if ($_GET['error'] === 'unauthorized') $error = "Access Restricted: Please login as Administrator to access the Admin Dashboard.";
    elseif ($_GET['error'] === 'forbidden') $error = "Permission Denied: Administrator role required. Your non-admin role was blocked and logged.";
}

if (isset($_GET['message'])) {
    if ($_GET['message'] === 'logged_out') $message = "You have been logged out securely.";
}

if (isset($_GET['quick_login'])) {
    $q_role = $_GET['quick_login'];
    $target_user = null;
    if ($q_role === 'Admin') $target_user = 'admin_hub';
    elseif ($q_role === 'Faculty') $target_user = 'prof_sharma';
    elseif ($q_role === 'Student') $target_user = 'miral082';

    if ($target_user && $conn !== null) {
        $stmt = $conn->prepare("SELECT id, username, email, full_name, role, status FROM users WHERE username = ?");
        $stmt->bind_param("s", $target_user);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($u = $res->fetch_assoc()) {
            $_SESSION['user_id'] = $u['id'];
            $_SESSION['username'] = $u['username'];
            $_SESSION['email'] = $u['email'];
            $_SESSION['full_name'] = $u['full_name'];
            $_SESSION['role'] = $u['role'];

            log_audit($conn, $u['id'], $u['username'], 'QUICK_LOGIN_DEMO', 'SUCCESS');

            if ($u['role'] === 'Admin') {
                header("Location: index.php?login=success");
            } else {
                header("Location: student_portal.php?login=success");
            }
            exit();
        }
        $stmt->close();
    }
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $username_input = isset($_POST['username']) ? trim($_POST['username']) : '';
    $password_input = isset($_POST['password']) ? $_POST['password'] : '';

    if (empty($username_input) || empty($password_input)) {
        $error = "Please enter both username and password.";
    } elseif ($conn === null) {
        $error = "Database connection failed.";
    } else {
        $stmt = $conn->prepare("SELECT id, username, email, password, full_name, role, status FROM users WHERE username = ? OR email = ?");
        $stmt->bind_param("ss", $username_input, $username_input);
        $stmt->execute();
        $res = $stmt->get_result();

        if ($user = $res->fetch_assoc()) {
            if ($user['status'] === 'Inactive') {
                log_audit($conn, $user['id'], $user['username'], 'LOGIN_ATTEMPT', 'BLOCKED_INACTIVE_USER');
                $error = "Account Disabled: Your account has been set to Inactive by the Administrator.";
            } elseif (password_verify($password_input, $user['password'])) {
                session_regenerate_id(true);

                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['full_name'] = $user['full_name'];
                $_SESSION['role'] = $user['role'];

                $upd = $conn->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
                $upd->bind_param("i", $user['id']);
                $upd->execute();
                $upd->close();

                log_audit($conn, $user['id'], $user['username'], 'ADMIN_LOGIN', 'SUCCESS');

                if ($user['role'] === 'Admin') {
                    header("Location: index.php?login=success");
                } else {
                    header("Location: student_portal.php?login=success");
                }
                exit();
            } else {
                log_audit($conn, $user['id'], $user['username'], 'LOGIN_ATTEMPT', 'FAILED_PASSWORD');
                $error = "Invalid password. Please check your credentials.";
            }
        } else {
            $error = "No user found with this username or email.";
        }
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>StudentHub - RBAC Login (Practical 14)</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <header id="header">
        <img src="image/charusatlogo.png" alt="CHARUSAT Logo">
        <div>StudentHub - Authentication &amp; RBAC Portal</div>
        <nav>
            <a href="login.php" class="active">Login</a>
            <a href="index.php">Admin Dashboard</a>
            <a href="setup_database.php">Setup DB</a>
        </nav>
    </header>

    <main class="main-content" style="max-width: 520px;">
        <section class="container">
            <h2>Practical 14: Secure RBAC Login</h2>
            <div class="info-box">
                Demonstrates Role-Based Access Control (RBAC): Only users with the <code>Admin</code> role can access the full Admin Dashboard. Non-admin logins are automatically routed to the student portal with restricted dynamic menus.
            </div>

            <?php if (!empty($message)): ?>
                <div class="alert-success">
                    &check; <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($error)): ?>
                <div class="alert-error">
                    &cross; <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <form action="login.php" method="POST">
                <div class="form-group">
                    <label for="username">Username or Email</label>
                    <input type="text" id="username" name="username" required placeholder="e.g. admin_hub or miral082">
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" required placeholder="Enter password">
                </div>

                <button type="submit" class="btn-submit" style="margin-top: 10px;">Login with Role Verification</button>
            </form>

            <div style="margin-top: 25px; padding: 15px; background: #f8fafc; border: 1px dashed #1E3A8A; border-radius: 6px;">
                <strong style="color: #1E3A8A; display: block; margin-bottom: 8px;">1-Click Role Switcher for Viva Demo:</strong>
                <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                    <a href="login.php?quick_login=Admin" class="btn-toggle-active" style="text-decoration: none;">Login as ADMIN</a>
                    <a href="login.php?quick_login=Faculty" class="badge-faculty" style="text-decoration: none; padding: 6px 12px; border-radius: 4px; font-weight: bold;">Login as FACULTY</a>
                    <a href="login.php?quick_login=Student" class="badge-student" style="text-decoration: none; padding: 6px 12px; border-radius: 4px; font-weight: bold;">Login as STUDENT</a>
                </div>
            </div>
        </section>
    </main>

    <footer>
        MIRAL PATEL||25CE082||STUDENT HUB PORTAL
    </footer>
</body>
</html>

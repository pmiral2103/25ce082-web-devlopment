<?php
require_once "auth_helper.php";

check_remember_me($conn);

if (isset($_SESSION['user_id'])) {
    if ($_SESSION['role'] === 'Admin') {
        header("Location: admin_dashboard.php");
        exit();
    } else {
        header("Location: student_dashboard.php");
        exit();
    }
}

$error_msg = "";
$success_msg = "";

if (isset($_GET['error'])) {
    if ($_GET['error'] === 'unauthorized') {
        $error_msg = "Access Denied: Please log in first to access the dashboard.";
    } elseif ($_GET['error'] === 'timeout') {
        $error_msg = "Session Expired: You were logged out due to inactivity. Please log in again.";
    }
}

if (isset($_GET['message'])) {
    if ($_GET['message'] === 'logged_out') {
        $success_msg = "You have been logged out securely. Session destroyed.";
    }
}

$login_input = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $token = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '';
    if (!verify_csrf_token($token)) {
        $error_msg = "Security Error: Invalid or expired CSRF token.";
    } else {
        $login_input = isset($_POST['login_input']) ? trim($_POST['login_input']) : '';
        $password = isset($_POST['password']) ? $_POST['password'] : '';
        $remember_me = isset($_POST['remember_me']);

        if (empty($login_input) || empty($password)) {
            $error_msg = "Please enter both username/email and password.";
        } else {
            $stmt = $conn->prepare("SELECT id, username, email, password, full_name, mobile, role, last_login FROM users WHERE username = ? OR email = ? LIMIT 1");
            if ($stmt) {
                $stmt->bind_param("ss", $login_input, $login_input);
                $stmt->execute();
                $res = $stmt->get_result();

                if ($user = $res->fetch_assoc()) {
                    if (password_verify($password, $user['password'])) {
                        session_regenerate_id(true);

                        $_SESSION['user_id'] = $user['id'];
                        $_SESSION['username'] = $user['username'];
                        $_SESSION['email'] = $user['email'];
                        $_SESSION['full_name'] = $user['full_name'];
                        $_SESSION['role'] = $user['role'];
                        $_SESSION['last_login'] = $user['last_login'];
                        $_SESSION['last_activity'] = time();

                        $upd_stmt = $conn->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
                        if ($upd_stmt) {
                            $upd_stmt->bind_param("i", $user['id']);
                            $upd_stmt->execute();
                            $upd_stmt->close();
                        }

                        if ($remember_me) {
                            $raw_token = bin2hex(random_bytes(32));
                            $token_hash = hash('sha256', $raw_token);
                            $expiry_time = date('Y-m-d H:i:s', time() + (86400 * 30));

                            $tok_stmt = $conn->prepare("INSERT INTO remember_tokens (user_id, token_hash, expires_at) VALUES (?, ?, ?)");
                            if ($tok_stmt) {
                                $tok_stmt->bind_param("iss", $user['id'], $token_hash, $expiry_time);
                                $tok_stmt->execute();
                                $tok_stmt->close();
                            }

                            setcookie('remember_token', $raw_token, time() + (86400 * 30), '/', '', false, true);
                        }

                        log_audit_event($conn, $user['id'], $user['username'], 'USER_LOGIN', 'SUCCESS');

                        if ($user['role'] === 'Admin') {
                            header("Location: admin_dashboard.php?login=success");
                        } else {
                            header("Location: student_dashboard.php?login=success");
                        }
                        exit();
                    } else {
                        log_audit_event($conn, $user['id'], $user['username'], 'USER_LOGIN', 'FAILED_PASSWORD');
                        $error_msg = "Invalid password. Please check your credentials.";
                    }
                } else {
                    log_audit_event($conn, null, $login_input, 'USER_LOGIN', 'FAILED_USER_NOT_FOUND');
                    $error_msg = "No account found with this username or email.";
                }
                $stmt->close();
            } else {
                $error_msg = "Database query preparation failed.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>StudentHub - Secure Login (Practical 10)</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <header id="header">
        <img src="image/charusatlogo.png" alt="CHARUSAT Logo">
        <div>StudentHub Authentication Portal</div>
        <nav>
            <a href="index.php" class="active">Login</a>
            <a href="student_dashboard.php">Student Portal</a>
            <a href="admin_dashboard.php">Admin Portal</a>
            <a href="setup_database.php">Setup DB</a>
        </nav>
    </header>

    <main class="main-content">
        <section class="container login-card">
            <h2>Practical 10: Secure User Login</h2>
            <div class="info-box">
                Features: <code>password_verify()</code> authentication, <code>session_regenerate_id()</code>, Role-Based Access Control (RBAC), 15-minute inactivity session timeout, and secure SHA-256 hashed Remember-Me cookie tokens.
            </div>

            <?php if (!empty($success_msg)): ?>
                <div class="alert-success">
                    &check; <?php echo htmlspecialchars($success_msg); ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($error_msg)): ?>
                <div class="alert-error">
                    &cross; <?php echo htmlspecialchars($error_msg); ?>
                </div>
            <?php endif; ?>

            <form action="index.php" method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(get_csrf_token()); ?>">

                <div class="form-group">
                    <label for="login_input">Username or Email <span class="req">*</span></label>
                    <input type="text" id="login_input" name="login_input" required placeholder="e.g. miral082 or 25ce082@charusat.edu.in" value="<?php echo htmlspecialchars($login_input); ?>">
                </div>

                <div class="form-group">
                    <label for="password">Password <span class="req">*</span></label>
                    <input type="password" id="password" name="password" required placeholder="Enter your password">
                </div>

                <div class="checkbox-group">
                    <input type="checkbox" id="remember_me" name="remember_me" value="1">
                    <label for="remember_me">Remember Me on this device (Persistent 30-day SHA-256 hashed token)</label>
                </div>

                <button type="submit" class="btn-submit">Secure Login</button>
            </form>

            <div style="margin-top: 20px; padding: 12px; background: #f8fafc; border: 1px dashed #1E3A8A; border-radius: 6px; font-size: 12px;">
                <strong>Demo Test Credentials for Viva:</strong>
                <div style="margin-top: 6px; display: flex; flex-direction: column; gap: 4px;">
                    <div><strong>Student:</strong> <code>miral082</code> / <code>Password@123</code> &rarr; Redirects to Student Dashboard</div>
                    <div><strong>Admin:</strong> <code>admin_hub</code> / <code>Admin@123</code> &rarr; Redirects to Admin Dashboard</div>
                    <div><strong>Faculty:</strong> <code>prof_sharma</code> / <code>Faculty@123</code> &rarr; Redirects to Student Dashboard</div>
                </div>
            </div>
        </section>
    </main>

    <footer>
        MIRAL PATEL||25CE082||STUDENT HUB PORTAL
    </footer>
</body>
</html>

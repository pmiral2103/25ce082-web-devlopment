<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth_helper.php';

check_remember_token($conn);

if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit();
}

$error = '';
$success = '';

if (isset($_GET['error'])) {
    if ($_GET['error'] === 'unauthorized') {
        $error = "Please login to access that page.";
    } elseif ($_GET['error'] === 'timeout') {
        $error = "Your session expired due to inactivity. Please log in again.";
    } elseif ($_GET['error'] === 'forbidden') {
        $error = "Access denied: You do not have permission for that area.";
    }
}

if (isset($_GET['msg']) && $_GET['msg'] === 'logged_out') {
    $success = "You have been logged out securely.";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($token)) {
        $error = "Security validation failed. Please reload and try again.";
    } else {
        $login_input = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $remember_me = isset($_POST['remember_me']);

        if (empty($login_input) || empty($password)) {
            $error = "Please enter both username/email and password.";
        } elseif (!$conn) {
            $error = "Database connection error. Please visit Setup to initialize.";
        } else {
            $stmt = $conn->prepare("SELECT id, username, email, password, full_name, role, status FROM users WHERE username = ? OR email = ? LIMIT 1");
            if ($stmt) {
                $stmt->bind_param("ss", $login_input, $login_input);
                $stmt->execute();
                $res = $stmt->get_result();
                if ($user = $res->fetch_assoc()) {
                    if ($user['status'] !== 'Active') {
                        $error = "Your account is deactivated. Please contact the administrator.";
                        log_audit($conn, $user['id'], $user['username'], 'USER_LOGIN', 'FAILED_DEACTIVATED');
                    } elseif (password_verify($password, $user['password'])) {
                        session_regenerate_id(true);
                        $_SESSION['user_id'] = $user['id'];
                        $_SESSION['username'] = $user['username'];
                        $_SESSION['email'] = $user['email'];
                        $_SESSION['full_name'] = $user['full_name'];
                        $_SESSION['role'] = $user['role'];
                        $_SESSION['last_activity'] = time();

                        $upd_login = $conn->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
                        if ($upd_login) {
                            $upd_login->bind_param("i", $user['id']);
                            $upd_login->execute();
                            $upd_login->close();
                        }

                        if ($remember_me) {
                            $raw_token = bin2hex(random_bytes(32));
                            $token_hash = hash('sha256', $raw_token);
                            $expires = date('Y-m-d H:i:s', time() + (86400 * 30));

                            $token_stmt = $conn->prepare("INSERT INTO remember_tokens (user_id, token_hash, expires_at) VALUES (?, ?, ?)");
                            if ($token_stmt) {
                                $token_stmt->bind_param("iss", $user['id'], $token_hash, $expires);
                                $token_stmt->execute();
                                $token_stmt->close();
                            }

                            setcookie('remember_token', $raw_token, [
                                'expires' => time() + (86400 * 30),
                                'path' => '/',
                                'httponly' => true,
                                'samesite' => 'Lax'
                            ]);
                        }

                        log_audit($conn, $user['id'], $user['username'], 'USER_LOGIN', 'SUCCESS');
                        $stmt->close();
                        header("Location: dashboard.php");
                        exit();
                    } else {
                        $error = "Incorrect password. Please try again.";
                        log_audit($conn, $user['id'], $user['username'], 'USER_LOGIN', 'FAILED_WRONG_PASSWORD');
                    }
                } else {
                    $error = "No user found matching that username or email.";
                    log_audit($conn, null, $login_input, 'USER_LOGIN', 'FAILED_USER_NOT_FOUND');
                }
                $stmt->close();
            } else {
                $error = "Query preparation failed: " . $conn->error;
            }
        }
    }
}

$csrf_token = get_csrf_token();
$nav_items = get_dynamic_navigation('login.php');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Secure Portal Login - StudentHub</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <a href="#main-content" class="skip-link">Skip to main content</a>
    <header class="app-header shadow-sm">
        <div class="container py-2">
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                <div class="d-flex align-items-center gap-3">
                    <img src="image/charusatlogo.png" alt="CHARUSAT Logo" class="brand-logo" onerror="this.style.display='none'">
                    <div>
                        <h1 class="brand-title mb-0">StudentHub Portal</h1>
                        <p class="brand-subtitle mb-0">CSPIT / CHARUSAT - 25CE082 Final Integration</p>
                    </div>
                </div>
                <div>
                    <a href="register.php" class="btn btn-sm btn-outline-light me-2"><i class="bi bi-person-plus"></i> Register</a>
                    <a href="setup.php" class="btn btn-sm btn-light text-primary"><i class="bi bi-wrench"></i> Setup</a>
                </div>
            </div>
        </div>
    </header>

    <nav class="app-nav" aria-label="Main Navigation">
        <div class="container d-flex flex-wrap gap-2 py-1">
            <?php foreach ($nav_items as $item): ?>
                <a href="<?= htmlspecialchars($item['url']) ?>" class="nav-item <?= $item['url'] === 'login.php' ? 'active' : '' ?>">
                    <i class="bi bi-<?= htmlspecialchars($item['icon']) ?>"></i> <?= htmlspecialchars($item['label']) ?>
                </a>
            <?php endforeach; ?>
        </div>
    </nav>

    <main id="main-content" class="container py-5 flex-grow-1">
        <div class="row justify-content-center">
            <div class="col-md-8 col-lg-5">
                <div class="card shadow-sm border-0 auth-card">
                    <div class="card-header bg-primary text-white text-center py-4">
                        <i class="bi bi-shield-lock-fill display-5 mb-2"></i>
                        <h4 class="mb-0 fw-bold">Sign In to StudentHub</h4>
                        <p class="small text-white-50 mb-0">Role-based access authentication (P-10)</p>
                    </div>
                    <div class="card-body p-4">
                        <?php if (!empty($error)): ?>
                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                <i class="bi bi-exclamation-triangle-fill me-2"></i><?= htmlspecialchars($error) ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($success)): ?>
                            <div class="alert alert-success alert-dismissible fade show" role="alert">
                                <i class="bi bi-check-circle-fill me-2"></i><?= htmlspecialchars($success) ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>

                        <form method="POST" action="login.php" id="loginForm" novalidate>
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">

                            <div class="mb-3">
                                <label for="username" class="form-label fw-semibold">Username or Email</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-person"></i></span>
                                    <input type="text" class="form-control" id="username" name="username" placeholder="e.g. admin or student1" required autofocus>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="password" class="form-label fw-semibold">Password</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-key"></i></span>
                                    <input type="password" class="form-control" id="password" name="password" placeholder="Enter your password" required>
                                </div>
                            </div>

                            <div class="d-flex justify-content-between align-items-center mb-4">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="remember_me" name="remember_me">
                                    <label class="form-check-label small" for="remember_me">Remember Me (30 Days)</label>
                                </div>
                                <a href="setup.php" class="small text-muted">Forgot / Reset?</a>
                            </div>

                            <div class="d-grid mb-3">
                                <button type="submit" class="btn btn-primary btn-lg fw-bold">
                                    <i class="bi bi-box-arrow-in-right me-2"></i>Login Now
                                </button>
                            </div>
                        </form>

                        <div class="border-top pt-3 mt-3 text-center">
                            <p class="small text-muted mb-2">Need an account? <a href="register.php" class="fw-bold">Register as a Student</a></p>
                        </div>
                    </div>

                    <div class="card-footer bg-light p-3">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <span class="small fw-bold text-muted"><i class="bi bi-lightning-charge-fill text-warning"></i> Quick Demo Logins:</span>
                            <span class="badge bg-secondary">Auto-Fill</span>
                        </div>
                        <div class="btn-group w-100" role="group">
                            <button type="button" class="btn btn-outline-danger btn-sm" onclick="fillCredentials('admin_hub', 'Admin@123')">
                                <i class="bi bi-person-gear"></i> Admin
                            </button>
                            <button type="button" class="btn btn-outline-success btn-sm" onclick="fillCredentials('miral082', 'Password@123')">
                                <i class="bi bi-mortarboard"></i> Student
                            </button>
                            <button type="button" class="btn btn-outline-info btn-sm" onclick="fillCredentials('prof_sharma', 'Faculty@123')">
                                <i class="bi bi-briefcase"></i> Faculty
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <footer class="app-footer text-center py-3">
        <div class="container">
            <p class="mb-1 text-light fw-bold">MIRAL PATEL || 25CE082 || STUDENT HUB PORTAL</p>
            <p class="mb-0 text-white-50 small">&copy; <?= date('Y') ?> CSPIT / CHARUSAT. Practical 15 Final Integration & Deployment.</p>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    function fillCredentials(u, p) {
        document.getElementById('username').value = u;
        document.getElementById('password').value = p;
    }
    </script>
</body>
</html>

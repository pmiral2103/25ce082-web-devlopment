<?php
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    session_start();
}

require_once __DIR__ . '/db.php';

function log_audit($conn, $user_id, $username, $action, $status) {
    if ($conn === null) return;
    $ip = !empty($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '127.0.0.1';
    $ua = !empty($_SERVER['HTTP_USER_AGENT']) ? substr($_SERVER['HTTP_USER_AGENT'], 0, 255) : 'Unknown';
    $stmt = $conn->prepare("INSERT INTO audit_logs (user_id, username, action, status, ip_address, user_agent) VALUES (?, ?, ?, ?, ?, ?)");
    if ($stmt) {
        $stmt->bind_param("isssss", $user_id, $username, $action, $status, $ip, $ua);
        $stmt->execute();
        $stmt->close();
    }
}

function check_remember_token($conn) {
    if (isset($_SESSION['user_id']) || empty($_COOKIE['remember_token']) || $conn === null) {
        return;
    }
    $raw_token = $_COOKIE['remember_token'];
    $token_hash = hash('sha256', $raw_token);
    $stmt = $conn->prepare("SELECT r.user_id, u.username, u.email, u.full_name, u.role, u.status FROM remember_tokens r JOIN users u ON r.user_id = u.id WHERE r.token_hash = ? AND r.expires_at > NOW() AND u.status = 'Active' LIMIT 1");
    if ($stmt) {
        $stmt->bind_param("s", $token_hash);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($row = $res->fetch_assoc()) {
            session_regenerate_id(true);
            $_SESSION['user_id'] = $row['user_id'];
            $_SESSION['username'] = $row['username'];
            $_SESSION['email'] = $row['email'];
            $_SESSION['full_name'] = $row['full_name'];
            $_SESSION['role'] = $row['role'];
            $_SESSION['last_activity'] = time();

            log_audit($conn, $row['user_id'], $row['username'], 'AUTO_LOGIN_REMEMBER_ME', 'SUCCESS');
        } else {
            setcookie('remember_token', '', time() - 3600, '/');
        }
        $stmt->close();
    }
}

function require_login($conn) {
    check_remember_token($conn);
    if (!isset($_SESSION['user_id'])) {
        header("Location: login.php?error=unauthorized");
        exit();
    }
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > 1800)) {
        $uid = $_SESSION['user_id'];
        $uname = $_SESSION['username'];
        log_audit($conn, $uid, $uname, 'SESSION_TIMEOUT', 'LOGOUT');
        session_unset();
        session_destroy();
        setcookie('remember_token', '', time() - 3600, '/');
        header("Location: login.php?error=timeout");
        exit();
    }
    $_SESSION['last_activity'] = time();
}

function require_admin($conn) {
    require_login($conn);
    if ($_SESSION['role'] !== 'Admin') {
        $uid = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
        $uname = isset($_SESSION['username']) ? $_SESSION['username'] : 'guest';
        log_audit($conn, $uid, $uname, 'UNAUTHORIZED_ADMIN_ACCESS', 'BLOCKED');
        header("Location: dashboard.php?error=forbidden");
        exit();
    }
}

function get_dynamic_navigation($current_page = '') {
    $is_logged_in = isset($_SESSION['user_id']);
    $role = $is_logged_in ? $_SESSION['role'] : 'Guest';

    $items = [
        ['label' => 'Home', 'url' => 'index.php', 'icon' => 'home']
    ];

    if ($is_logged_in) {
        $items[] = ['label' => 'Dashboard', 'url' => 'dashboard.php', 'icon' => 'dashboard'];
        $items[] = ['label' => 'Events', 'url' => 'events.php', 'icon' => 'calendar'];
        $items[] = ['label' => 'Students', 'url' => 'students.php', 'icon' => 'users'];
        $items[] = ['label' => 'Registrations', 'url' => 'registrations.php', 'icon' => 'list'];
    } else {
        $items[] = ['label' => 'Browse Events', 'url' => 'events.php', 'icon' => 'calendar'];
        $items[] = ['label' => 'Login', 'url' => 'login.php', 'icon' => 'login'];
        $items[] = ['label' => 'Register', 'url' => 'register.php', 'icon' => 'user-plus'];
    }

    $items[] = ['label' => 'System Setup', 'url' => 'setup.php', 'icon' => 'wrench'];

    return $items;
}

function get_csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verify_csrf_token($token) {
    return !empty($token) && !empty($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

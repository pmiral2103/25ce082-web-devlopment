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

function log_audit_event($conn, $user_id, $username, $action, $status) {
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

function check_remember_me($conn) {
    if (isset($_SESSION['user_id']) || empty($_COOKIE['remember_token']) || $conn === null) {
        return;
    }
    $raw_token = $_COOKIE['remember_token'];
    $token_hash = hash('sha256', $raw_token);
    $stmt = $conn->prepare("SELECT r.id as token_id, r.user_id, u.username, u.email, u.full_name, u.role FROM remember_tokens r JOIN users u ON r.user_id = u.id WHERE r.token_hash = ? AND r.expires_at > NOW() LIMIT 1");
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

            $update_stmt = $conn->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
            if ($update_stmt) {
                $update_stmt->bind_param("i", $row['user_id']);
                $update_stmt->execute();
                $update_stmt->close();
            }

            log_audit_event($conn, $row['user_id'], $row['username'], 'AUTO_LOGIN_REMEMBER_ME', 'SUCCESS');
        } else {
            setcookie('remember_token', '', time() - 3600, '/');
        }
        $stmt->close();
    }
}

function check_session_timeout($conn, $timeout_seconds = 900) {
    if (isset($_SESSION['user_id'])) {
        if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $timeout_seconds)) {
            $uid = $_SESSION['user_id'];
            $uname = $_SESSION['username'];
            log_audit_event($conn, $uid, $uname, 'SESSION_TIMEOUT', 'EXPIRED');

            if (!empty($_COOKIE['remember_token'])) {
                $token_hash = hash('sha256', $_COOKIE['remember_token']);
                if ($conn !== null) {
                    $del_stmt = $conn->prepare("DELETE FROM remember_tokens WHERE token_hash = ?");
                    if ($del_stmt) {
                        $del_stmt->bind_param("s", $token_hash);
                        $del_stmt->execute();
                        $del_stmt->close();
                    }
                }
                setcookie('remember_token', '', time() - 3600, '/');
            }

            $_SESSION = array();
            if (ini_get("session.use_cookies")) {
                $params = session_get_cookie_params();
                setcookie(session_name(), '', time() - 42000,
                    $params["path"], $params["domain"],
                    $params["secure"], $params["httponly"]
                );
            }
            session_destroy();
            header("Location: index.php?error=timeout");
            exit();
        }
        $_SESSION['last_activity'] = time();
    }
}

function require_login($conn) {
    check_remember_me($conn);
    check_session_timeout($conn);
    if (!isset($_SESSION['user_id'])) {
        header("Location: index.php?error=unauthorized");
        exit();
    }
}

function require_role($conn, $required_role) {
    require_login($conn);
    if ($_SESSION['role'] !== $required_role) {
        log_audit_event($conn, $_SESSION['user_id'], $_SESSION['username'], 'UNAUTHORIZED_ACCESS_ATTEMPT', 'BLOCKED');
        header("Location: student_dashboard.php?error=forbidden");
        exit();
    }
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

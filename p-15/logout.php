<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth_helper.php';

if (isset($_SESSION['user_id'])) {
    $uid = $_SESSION['user_id'];
    $uname = $_SESSION['username'] ?? 'unknown';
    log_audit($conn, $uid, $uname, 'USER_LOGOUT', 'SUCCESS');

    if (!empty($_COOKIE['remember_token']) && $conn) {
        $raw_token = $_COOKIE['remember_token'];
        $token_hash = hash('sha256', $raw_token);
        $stmt = $conn->prepare("DELETE FROM remember_tokens WHERE user_id = ? OR token_hash = ?");
        if ($stmt) {
            $stmt->bind_param("is", $uid, $token_hash);
            $stmt->execute();
            $stmt->close();
        }
    }
}

if (!empty($_COOKIE['remember_token'])) {
    setcookie('remember_token', '', time() - 3600, '/');
}

$_SESSION = [];
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}
session_destroy();

header("Location: login.php?msg=logged_out");
exit();

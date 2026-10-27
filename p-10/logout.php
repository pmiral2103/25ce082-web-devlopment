<?php
require_once "auth_helper.php";

if (isset($_SESSION['user_id'])) {
    log_audit_event($conn, $_SESSION['user_id'], $_SESSION['username'], 'USER_LOGOUT', 'SUCCESS');
}

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

header("Location: index.php?message=logged_out");
exit();

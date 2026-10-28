<?php
require_once "auth_helper.php";

if (isset($_SESSION['user_id'])) {
    log_audit($conn, $_SESSION['user_id'], $_SESSION['username'], 'USER_LOGOUT', 'SUCCESS');
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

header("Location: login.php?message=logged_out");
exit();

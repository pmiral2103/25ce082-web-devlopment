<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth_helper.php';

require_admin($conn);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        header("Location: dashboard.php?error=invalid_csrf");
        exit();
    }

    $target_id = isset($_POST['user_id']) ? (int)$_POST['user_id'] : 0;
    if ($target_id <= 0 || $target_id === (int)$_SESSION['user_id']) {
        header("Location: dashboard.php?error=cannot_modify_self");
        exit();
    }

    $stmt = $conn->prepare("SELECT id, username, status FROM users WHERE id = ? LIMIT 1");
    if ($stmt) {
        $stmt->bind_param("i", $target_id);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($user) {
            $new_status = ($user['status'] === 'Active') ? 'Inactive' : 'Active';
            $upd = $conn->prepare("UPDATE users SET status = ? WHERE id = ?");
            if ($upd) {
                $upd->bind_param("si", $new_status, $target_id);
                $upd->execute();
                $upd->close();

                log_audit($conn, $_SESSION['user_id'], $_SESSION['username'], 'USER_STATUS_TOGGLE_' . $new_status . '_' . $user['username'], 'SUCCESS');
            }
        }
    }
}

header("Location: dashboard.php?msg=user_toggled");
exit();

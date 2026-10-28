<?php
require_once "auth_helper.php";

require_admin($conn);

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($id <= 0 || $conn === null) {
    header("Location: index.php?err=invalid_id");
    exit();
}

if ($id === intval($_SESSION['user_id'])) {
    header("Location: index.php?err=self_deactivation_blocked");
    exit();
}

$stmt = $conn->prepare("SELECT username, status FROM users WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$res = $stmt->get_result();

if ($row = $res->fetch_assoc()) {
    $new_status = ($row['status'] === 'Active') ? 'Inactive' : 'Active';
    $target_user = $row['username'];
    $stmt->close();

    $upd = $conn->prepare("UPDATE users SET status = ? WHERE id = ?");
    $upd->bind_param("si", $new_status, $id);
    if ($upd->execute()) {
        $audit_detail = "Changed user '{$target_user}' status to '{$new_status}'";
        log_audit($conn, $_SESSION['user_id'], $_SESSION['username'], 'USER_STATUS_TOGGLED', $audit_detail);
        header("Location: index.php?msg=status_toggled");
    } else {
        header("Location: index.php?err=update_failed");
    }
    $upd->close();
} else {
    $stmt->close();
    header("Location: index.php?err=not_found");
}

$conn->close();
exit();

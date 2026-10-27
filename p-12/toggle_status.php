<?php
require_once "db.php";

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($id <= 0 || $conn === null) {
    header("Location: index.php?err=invalid_id");
    exit();
}

$stmt = $conn->prepare("SELECT name, status FROM events WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$res = $stmt->get_result();

if ($row = $res->fetch_assoc()) {
    $new_status = ($row['status'] === 'Open') ? 'Closed' : 'Open';
    $stmt->close();

    $upd = $conn->prepare("UPDATE events SET status = ? WHERE id = ?");
    $upd->bind_param("si", $new_status, $id);
    if ($upd->execute()) {
        $ip = !empty($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '127.0.0.1';
        $ua = !empty($_SERVER['HTTP_USER_AGENT']) ? substr($_SERVER['HTTP_USER_AGENT'], 0, 255) : 'Unknown';
        $act = "EVENT_STATUS_TOGGLED";
        $st = "SUCCESS";
        $un = "admin_hub";
        $log_stmt = $conn->prepare("INSERT INTO audit_logs (username, action, status, ip_address, user_agent) VALUES (?, ?, ?, ?, ?)");
        if ($log_stmt) {
            $log_stmt->bind_param("sssss", $un, $act, $st, $ip, $ua);
            $log_stmt->execute();
            $log_stmt->close();
        }

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

<?php
require_once "db.php";

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($id <= 0 || $conn === null) {
    header("Location: index.php?err=invalid_id");
    exit();
}

$stmt = $conn->prepare("SELECT name, poster FROM events WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$res = $stmt->get_result();

if ($row = $res->fetch_assoc()) {
    $poster = $row['poster'];
    $event_name = $row['name'];
    $stmt->close();

    if ($poster !== 'default_poster.png' && !empty($poster)) {
        $file_path = __DIR__ . '/uploads/' . $poster;
        if (file_exists($file_path)) {
            @unlink($file_path);
        }
    }

    $del = $conn->prepare("DELETE FROM events WHERE id = ?");
    $del->bind_param("i", $id);
    if ($del->execute()) {
        $ip = !empty($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '127.0.0.1';
        $ua = !empty($_SERVER['HTTP_USER_AGENT']) ? substr($_SERVER['HTTP_USER_AGENT'], 0, 255) : 'Unknown';
        $act = "EVENT_DELETED";
        $st = "SUCCESS";
        $un = "admin_hub";
        $log_stmt = $conn->prepare("INSERT INTO audit_logs (username, action, status, ip_address, user_agent) VALUES (?, ?, ?, ?, ?)");
        if ($log_stmt) {
            $log_stmt->bind_param("sssss", $un, $act, $st, $ip, $ua);
            $log_stmt->execute();
            $log_stmt->close();
        }

        header("Location: index.php?msg=deleted");
    } else {
        header("Location: index.php?err=delete_failed");
    }
    $del->close();
} else {
    $stmt->close();
    header("Location: index.php?err=not_found");
}

$conn->close();
exit();

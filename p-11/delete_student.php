<?php
require_once "db.php";

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$action = isset($_GET['action']) ? $_GET['action'] : 'soft_delete';

if ($id <= 0 || $conn === null) {
    header("Location: index.php?err=invalid_request");
    exit();
}

if ($action === 'soft_delete') {
    $stmt = $conn->prepare("UPDATE students SET status = 'Inactive' WHERE id = ?");
    $stmt->bind_param("i", $id);
    if ($stmt->execute()) {
        header("Location: index.php?msg=soft_deleted");
    } else {
        header("Location: index.php?err=delete_failed");
    }
    $stmt->close();
} elseif ($action === 'restore') {
    $stmt = $conn->prepare("UPDATE students SET status = 'Active' WHERE id = ?");
    $stmt->bind_param("i", $id);
    if ($stmt->execute()) {
        header("Location: index.php?msg=restored");
    } else {
        header("Location: index.php?err=restore_failed");
    }
    $stmt->close();
} elseif ($action === 'permanent') {
    $stmt = $conn->prepare("DELETE FROM students WHERE id = ?");
    $stmt->bind_param("i", $id);
    if ($stmt->execute()) {
        header("Location: index.php?msg=permanently_deleted");
    } else {
        header("Location: index.php?err=delete_failed");
    }
    $stmt->close();
} else {
    header("Location: index.php");
}

$conn->close();
exit();

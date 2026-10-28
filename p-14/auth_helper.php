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

function require_admin($conn) {
    if (!isset($_SESSION['user_id'])) {
        header("Location: login.php?error=unauthorized");
        exit();
    }
    if ($_SESSION['role'] !== 'Admin') {
        $uid = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
        $uname = isset($_SESSION['username']) ? $_SESSION['username'] : 'guest';
        log_audit($conn, $uid, $uname, 'UNAUTHORIZED_ADMIN_ACCESS', 'BLOCKED');
        header("Location: student_portal.php?error=forbidden");
        exit();
    }
}

function get_role_menu($role) {
    $menus = [
        'Admin' => [
            ['label' => 'Admin Dashboard', 'url' => 'index.php', 'badge' => 'Admin'],
            ['label' => 'Analytics Chart', 'url' => '#analytics-section', 'badge' => 'New'],
            ['label' => 'User Management', 'url' => '#users-section', 'badge' => null],
            ['label' => 'Event Registrations', 'url' => '#registrations-section', 'badge' => null],
            ['label' => 'Security Audit Logs', 'url' => '#audit-section', 'badge' => null]
        ],
        'Faculty' => [
            ['label' => 'Faculty Dashboard', 'url' => 'index.php', 'badge' => 'Faculty'],
            ['label' => 'Browse Events', 'url' => '#registrations-section', 'badge' => null],
            ['label' => 'Student Records', 'url' => '#users-section', 'badge' => null]
        ],
        'Student' => [
            ['label' => 'Student Portal', 'url' => 'student_portal.php', 'badge' => 'Student'],
            ['label' => 'My Registered Events', 'url' => '#registrations-section', 'badge' => null]
        ]
    ];
    return isset($menus[$role]) ? $menus[$role] : $menus['Student'];
}

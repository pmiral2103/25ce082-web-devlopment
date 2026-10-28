<?php
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../auth_helper.php';

function json_response($status_code, $payload) {
    http_response_code($status_code);
    echo json_encode($payload);
    exit();
}

if (!$conn) {
    json_response(500, ['success' => false, 'error' => 'Database connection unavailable']);
}

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    if (isset($_GET['id'])) {
        $id = (int)$_GET['id'];
        $stmt = $conn->prepare("SELECT id, name, category, description, event_date, event_time, venue, coordinator, poster, status FROM events WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($row = $res->fetch_assoc()) {
            json_response(200, ['success' => true, 'data' => $row]);
        } else {
            json_response(404, ['success' => false, 'error' => 'Event not found']);
        }
        $stmt->close();
    } else {
        $search = trim($_GET['q'] ?? '');
        if (!empty($search)) {
            $like = "%$search%";
            $stmt = $conn->prepare("SELECT id, name, category, description, event_date, event_time, venue, coordinator, poster, status FROM events WHERE name LIKE ? OR category LIKE ? OR venue LIKE ? ORDER BY event_date ASC");
            $stmt->bind_param("sss", $like, $like, $like);
            $stmt->execute();
            $res = $stmt->get_result();
        } else {
            $res = $conn->query("SELECT id, name, category, description, event_date, event_time, venue, coordinator, poster, status FROM events ORDER BY event_date ASC");
        }

        $list = [];
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $list[] = $row;
            }
        }
        json_response(200, ['success' => true, 'count' => count($list), 'data' => $list]);
    }
} elseif ($method === 'POST') {
    if (!isset($_SESSION['user_id'])) {
        json_response(401, ['success' => false, 'error' => 'Authentication required']);
    }

    if ($_SESSION['role'] !== 'Admin') {
        json_response(403, ['success' => false, 'error' => 'Admin privileges required']);
    }

    $raw_input = file_get_contents('php://input');
    $data = json_decode($raw_input, true);
    if (!is_array($data)) {
        $data = $_POST;
    }

    $action = $data['action'] ?? 'create';

    if ($action === 'create') {
        $name = trim($data['name'] ?? '');
        $category = trim($data['category'] ?? 'Technical');
        $desc = trim($data['description'] ?? '');
        $date = trim($data['event_date'] ?? '');
        $time = trim($data['event_time'] ?? '10:00 AM');
        $venue = trim($data['venue'] ?? '');
        $coordinator = trim($data['coordinator'] ?? 'Faculty Coordinator');
        $poster = trim($data['poster'] ?? 'default_poster.png');
        $status = trim($data['status'] ?? 'Open');

        if (empty($name) || empty($date) || empty($venue)) {
            json_response(400, ['success' => false, 'error' => 'Missing required fields: name, event_date, venue']);
        }

        $stmt = $conn->prepare("INSERT INTO events (name, category, description, event_date, event_time, venue, coordinator, poster, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("sssssssss", $name, $category, $desc, $date, $time, $venue, $coordinator, $poster, $status);
        if ($stmt->execute()) {
            $new_id = $stmt->insert_id;
            log_audit($conn, $_SESSION['user_id'], $_SESSION['username'], "API_EVENT_CREATE_$new_id", 'SUCCESS');
            json_response(201, ['success' => true, 'id' => $new_id, 'message' => 'Event created successfully']);
        } else {
            json_response(500, ['success' => false, 'error' => $stmt->error]);
        }
        $stmt->close();
    } elseif ($action === 'delete') {
        $id = (int)($data['id'] ?? 0);
        if ($id <= 0) {
            json_response(400, ['success' => false, 'error' => 'Invalid event ID']);
        }
        $stmt = $conn->prepare("DELETE FROM events WHERE id = ?");
        $stmt->bind_param("i", $id);
        if ($stmt->execute() && $stmt->affected_rows > 0) {
            log_audit($conn, $_SESSION['user_id'], $_SESSION['username'], "API_EVENT_DELETE_$id", 'SUCCESS');
            json_response(200, ['success' => true, 'message' => 'Event deleted']);
        } else {
            json_response(404, ['success' => false, 'error' => 'Event not found or already removed']);
        }
        $stmt->close();
    } else {
        json_response(400, ['success' => false, 'error' => 'Invalid action']);
    }
} else {
    json_response(405, ['success' => false, 'error' => 'Method Not Allowed']);
}

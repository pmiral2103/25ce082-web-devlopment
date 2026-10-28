<?php
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/db.php';

function send_json_response($status_code, $success, $message, $data = null, $errors = null) {
    http_response_code($status_code);
    $response = [
        'success' => $success,
        'status' => $status_code,
        'message' => $message,
        'timestamp' => date('Y-m-d H:i:s')
    ];
    if ($data !== null) {
        $response['data'] = $data;
        if (is_array($data) && isset($data[0])) {
            $response['count'] = count($data);
        }
    }
    if ($errors !== null) {
        $response['errors'] = $errors;
    }
    echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit();
}

if ($conn === null) {
    send_json_response(500, false, "Database connection failed. Please ensure MySQL is running in XAMPP.");
}

$method = $_SERVER['REQUEST_METHOD'];
$action = isset($_GET['action']) ? trim($_GET['action']) : '';

$raw_input = file_get_contents('php://input');
$json_data = json_decode($raw_input, true);
if (is_array($json_data)) {
    $request_data = $json_data;
} else {
    $request_data = $_POST;
}

if (empty($action) && isset($request_data['action'])) {
    $action = trim($request_data['action']);
}

if ($method === 'GET') {
    if ($action === 'get') {
        $id = isset($_GET['id']) ? intval($_GET['id']) : 0;
        if ($id <= 0) {
            send_json_response(400, false, "Invalid or missing event ID parameter.");
        }

        $stmt = $conn->prepare("SELECT id, name, category, event_date, event_time, venue, coordinator, status, description, created_at FROM events WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $res = $stmt->get_result();

        if ($event = $res->fetch_assoc()) {
            send_json_response(200, true, "Event details retrieved successfully.", $event);
        } else {
            send_json_response(404, false, "Event with ID {$id} not found.");
        }
        $stmt->close();
    } else {
        $search = isset($_GET['search']) ? trim($_GET['search']) : '';
        $category = isset($_GET['category']) ? trim($_GET['category']) : '';
        $status = isset($_GET['status']) ? trim($_GET['status']) : '';

        $sql = "SELECT id, name, category, event_date, event_time, venue, coordinator, status, description, created_at FROM events WHERE 1=1";
        $params = [];
        $types = "";

        if (!empty($search)) {
            $sql .= " AND (name LIKE ? OR venue LIKE ? OR coordinator LIKE ?)";
            $like = "%" . $search . "%";
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
            $types .= "sss";
        }

        if (!empty($category)) {
            $sql .= " AND category = ?";
            $params[] = $category;
            $types .= "s";
        }

        if (!empty($status)) {
            $sql .= " AND status = ?";
            $params[] = $status;
            $types .= "s";
        }

        $sql .= " ORDER BY event_date ASC";

        $stmt = $conn->prepare($sql);
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $result = $stmt->get_result();

        $events = [];
        while ($row = $result->fetch_assoc()) {
            $events[] = $row;
        }
        $stmt->close();

        send_json_response(200, true, "Events list retrieved successfully.", $events);
    }
} elseif ($method === 'POST') {
    if ($action === 'create') {
        $name = isset($request_data['name']) ? trim($request_data['name']) : '';
        $category = isset($request_data['category']) ? trim($request_data['category']) : 'Technical';
        $event_date = isset($request_data['event_date']) ? trim($request_data['event_date']) : '';
        $event_time = isset($request_data['event_time']) ? trim($request_data['event_time']) : '';
        $venue = isset($request_data['venue']) ? trim($request_data['venue']) : '';
        $coordinator = isset($request_data['coordinator']) ? trim($request_data['coordinator']) : '';
        $status = isset($request_data['status']) && in_array($request_data['status'], ['Open', 'Closed']) ? $request_data['status'] : 'Open';
        $description = isset($request_data['description']) ? trim($request_data['description']) : '';

        $errors = [];
        if (empty($name)) $errors['name'] = "Event name is required.";
        if (empty($event_date)) $errors['event_date'] = "Event date is required.";
        if (empty($event_time)) $errors['event_time'] = "Event time is required.";
        if (empty($venue)) $errors['venue'] = "Venue is required.";
        if (empty($coordinator)) $errors['coordinator'] = "Coordinator is required.";

        if (!empty($errors)) {
            send_json_response(400, false, "Validation failed. Please correct input fields.", null, $errors);
        }

        $stmt = $conn->prepare("INSERT INTO events (name, category, event_date, event_time, venue, coordinator, status, description) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssssssss", $name, $category, $event_date, $event_time, $venue, $coordinator, $status, $description);

        if ($stmt->execute()) {
            $new_id = $conn->insert_id;
            $new_event = [
                'id' => $new_id,
                'name' => $name,
                'category' => $category,
                'event_date' => $event_date,
                'event_time' => $event_time,
                'venue' => $venue,
                'coordinator' => $coordinator,
                'status' => $status,
                'description' => $description
            ];
            send_json_response(201, true, "Event '{$name}' created successfully via JSON API.", $new_event);
        } else {
            send_json_response(500, false, "Database error during event insertion: " . $conn->error);
        }
        $stmt->close();
    } elseif ($action === 'update') {
        $id = isset($request_data['id']) ? intval($request_data['id']) : 0;
        if ($id <= 0) {
            send_json_response(400, false, "Invalid or missing event ID for update.");
        }

        $chk = $conn->prepare("SELECT id FROM events WHERE id = ?");
        $chk->bind_param("i", $id);
        $chk->execute();
        if ($chk->get_result()->num_rows === 0) {
            $chk->close();
            send_json_response(404, false, "Cannot update. Event with ID {$id} not found.");
        }
        $chk->close();

        $name = isset($request_data['name']) ? trim($request_data['name']) : '';
        $category = isset($request_data['category']) ? trim($request_data['category']) : 'Technical';
        $event_date = isset($request_data['event_date']) ? trim($request_data['event_date']) : '';
        $event_time = isset($request_data['event_time']) ? trim($request_data['event_time']) : '';
        $venue = isset($request_data['venue']) ? trim($request_data['venue']) : '';
        $coordinator = isset($request_data['coordinator']) ? trim($request_data['coordinator']) : '';
        $status = isset($request_data['status']) && in_array($request_data['status'], ['Open', 'Closed']) ? $request_data['status'] : 'Open';
        $description = isset($request_data['description']) ? trim($request_data['description']) : '';

        $errors = [];
        if (empty($name)) $errors['name'] = "Event name cannot be empty.";
        if (empty($event_date)) $errors['event_date'] = "Event date cannot be empty.";
        if (empty($event_time)) $errors['event_time'] = "Event time cannot be empty.";
        if (empty($venue)) $errors['venue'] = "Venue cannot be empty.";
        if (empty($coordinator)) $errors['coordinator'] = "Coordinator cannot be empty.";

        if (!empty($errors)) {
            send_json_response(400, false, "Validation failed. Please correct input fields.", null, $errors);
        }

        $stmt = $conn->prepare("UPDATE events SET name = ?, category = ?, event_date = ?, event_time = ?, venue = ?, coordinator = ?, status = ?, description = ? WHERE id = ?");
        $stmt->bind_param("ssssssssi", $name, $category, $event_date, $event_time, $venue, $coordinator, $status, $description, $id);

        if ($stmt->execute()) {
            $updated_event = [
                'id' => $id,
                'name' => $name,
                'category' => $category,
                'event_date' => $event_date,
                'event_time' => $event_time,
                'venue' => $venue,
                'coordinator' => $coordinator,
                'status' => $status,
                'description' => $description
            ];
            send_json_response(200, true, "Event with ID {$id} updated successfully.", $updated_event);
        } else {
            send_json_response(500, false, "Database error during event update: " . $conn->error);
        }
        $stmt->close();
    } elseif ($action === 'delete') {
        $id = isset($request_data['id']) ? intval($request_data['id']) : 0;
        if ($id <= 0) {
            send_json_response(400, false, "Invalid or missing event ID for deletion.");
        }

        $stmt = $conn->prepare("DELETE FROM events WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();

        if ($stmt->affected_rows > 0) {
            send_json_response(200, true, "Event with ID {$id} deleted successfully.", ['deleted_id' => $id]);
        } else {
            send_json_response(404, false, "Event with ID {$id} not found or already deleted.");
        }
        $stmt->close();
    } elseif ($action === 'toggle_status') {
        $id = isset($request_data['id']) ? intval($request_data['id']) : 0;
        if ($id <= 0) {
            send_json_response(400, false, "Invalid or missing event ID.");
        }

        $stmt = $conn->prepare("SELECT status FROM events WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $res = $stmt->get_result();

        if ($row = $res->fetch_assoc()) {
            $new_status = ($row['status'] === 'Open') ? 'Closed' : 'Open';
            $stmt->close();

            $upd = $conn->prepare("UPDATE events SET status = ? WHERE id = ?");
            $upd->bind_param("si", $new_status, $id);
            if ($upd->execute()) {
                send_json_response(200, true, "Status toggled to {$new_status} successfully.", ['id' => $id, 'status' => $new_status]);
            } else {
                send_json_response(500, false, "Failed to toggle status: " . $conn->error);
            }
            $upd->close();
        } else {
            $stmt->close();
            send_json_response(404, false, "Event with ID {$id} not found.");
        }
    } else {
        send_json_response(400, false, "Invalid action specified for POST request.");
    }
} else {
    send_json_response(405, false, "HTTP method {$method} is not allowed.");
}

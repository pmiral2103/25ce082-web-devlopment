<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth_helper.php';

check_remember_token($conn);

$is_logged_in = isset($_SESSION['user_id']);
$is_admin = ($is_logged_in && $_SESSION['role'] === 'Admin');

$message = '';
$message_type = 'info';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $is_admin) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $message = "Security verification failed. Please try again.";
        $message_type = "danger";
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'create' || $action === 'update') {
            $name = trim($_POST['name'] ?? '');
            $category = trim($_POST['category'] ?? 'Technical');
            $event_date = trim($_POST['event_date'] ?? '');
            $event_time = trim($_POST['event_time'] ?? '10:00 AM');
            $venue = trim($_POST['venue'] ?? '');
            $coordinator = trim($_POST['coordinator'] ?? 'Faculty Coordinator');
            $description = trim($_POST['description'] ?? '');
            $status = trim($_POST['status'] ?? 'Open');

            $poster_path = '';
            $upload_ok = true;

            if (isset($_FILES['poster']) && $_FILES['poster']['error'] === UPLOAD_ERR_OK) {
                $file_tmp = $_FILES['poster']['tmp_name'];
                $file_size = $_FILES['poster']['size'];
                $file_type = mime_content_type($file_tmp);

                $allowed_types = ['image/jpeg', 'image/png', 'image/webp'];
                $max_size = 2 * 1024 * 1024;

                if (!in_array($file_type, $allowed_types)) {
                    $message = "Only JPG, PNG, and WebP images are allowed for event poster.";
                    $message_type = "danger";
                    $upload_ok = false;
                } elseif ($file_size > $max_size) {
                    $message = "Poster file size cannot exceed 2MB.";
                    $message_type = "danger";
                    $upload_ok = false;
                } else {
                    $ext = ($file_type === 'image/png') ? 'png' : (($file_type === 'image/webp') ? 'webp' : 'jpg');
                    $filename = 'event_' . time() . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
                    $dest_dir = __DIR__ . '/uploads/';
                    if (!is_dir($dest_dir)) {
                        mkdir($dest_dir, 0755, true);
                    }
                    $dest = $dest_dir . $filename;
                    if (move_uploaded_file($file_tmp, $dest)) {
                        $poster_path = $filename;
                    } else {
                        $message = "Failed to save uploaded poster file.";
                        $message_type = "danger";
                        $upload_ok = false;
                    }
                }
            }

            if ($upload_ok) {
                if (empty($name) || empty($event_date) || empty($venue)) {
                    $message = "Title, event date, and venue are required.";
                    $message_type = "danger";
                } elseif ($action === 'create') {
                    if (empty($poster_path)) {
                        $poster_path = 'default_poster.png';
                    }
                    $stmt = $conn->prepare("INSERT INTO events (name, category, description, event_date, event_time, venue, coordinator, poster, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    if ($stmt) {
                        $stmt->bind_param("sssssssss", $name, $category, $description, $event_date, $event_time, $venue, $coordinator, $poster_path, $status);
                        if ($stmt->execute()) {
                            $message = "New event created successfully!";
                            $message_type = "success";
                            log_audit($conn, $_SESSION['user_id'], $_SESSION['username'], "EVENT_CREATE_$name", 'SUCCESS');
                        } else {
                            $message = "Insert error: " . $stmt->error;
                            $message_type = "danger";
                        }
                        $stmt->close();
                    }
                } elseif ($action === 'update') {
                    $id = (int)($_POST['event_id'] ?? 0);
                    if ($id > 0) {
                        if (!empty($poster_path)) {
                            $stmt = $conn->prepare("UPDATE events SET name = ?, category = ?, description = ?, event_date = ?, event_time = ?, venue = ?, coordinator = ?, poster = ?, status = ? WHERE id = ?");
                            $stmt->bind_param("sssssssssi", $name, $category, $description, $event_date, $event_time, $venue, $coordinator, $poster_path, $status, $id);
                        } else {
                            $stmt = $conn->prepare("UPDATE events SET name = ?, category = ?, description = ?, event_date = ?, event_time = ?, venue = ?, coordinator = ?, status = ? WHERE id = ?");
                            $stmt->bind_param("ssssssssi", $name, $category, $description, $event_date, $event_time, $venue, $coordinator, $status, $id);
                        }
                        if ($stmt && $stmt->execute()) {
                            $message = "Event updated successfully!";
                            $message_type = "success";
                            log_audit($conn, $_SESSION['user_id'], $_SESSION['username'], "EVENT_UPDATE_$id", 'SUCCESS');
                            $stmt->close();
                        } else {
                            $message = "Update failed: " . ($stmt ? $stmt->error : $conn->error);
                            $message_type = "danger";
                        }
                    }
                }
            }
        } elseif ($action === 'toggle_status') {
            $id = (int)($_POST['event_id'] ?? 0);
            if ($id > 0) {
                $cur = $conn->query("SELECT status FROM events WHERE id = $id LIMIT 1");
                if ($cur && ($row = $cur->fetch_assoc())) {
                    $new_st = ($row['status'] === 'Open') ? 'Closed' : 'Open';
                    $stmt = $conn->prepare("UPDATE events SET status = ? WHERE id = ?");
                    $stmt->bind_param("si", $new_st, $id);
                    $stmt->execute();
                    $stmt->close();
                    $message = "Event status changed to $new_st.";
                    $message_type = "info";
                    log_audit($conn, $_SESSION['user_id'], $_SESSION['username'], "EVENT_TOGGLE_$id" . "_$new_st", 'SUCCESS');
                }
            }
        } elseif ($action === 'delete') {
            $id = (int)($_POST['event_id'] ?? 0);
            if ($id > 0) {
                $stmt = $conn->prepare("DELETE FROM events WHERE id = ?");
                $stmt->bind_param("i", $id);
                if ($stmt->execute()) {
                    $message = "Event permanently removed.";
                    $message_type = "warning";
                    log_audit($conn, $_SESSION['user_id'], $_SESSION['username'], "EVENT_DELETE_$id", 'SUCCESS');
                }
                $stmt->close();
            }
        }
    }
}

$search = trim($_GET['search'] ?? '');
$category_filter = trim($_GET['category'] ?? 'all');
$status_filter = trim($_GET['status'] ?? 'all');

$where_clauses = ["1=1"];
$params = [];
$types = "";

if (!empty($search)) {
    $where_clauses[] = "(name LIKE ? OR description LIKE ? OR venue LIKE ? OR coordinator LIKE ?)";
    $like = "%$search%";
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $types .= "ssss";
}

if ($category_filter !== 'all' && !empty($category_filter)) {
    $where_clauses[] = "category = ?";
    $params[] = $category_filter;
    $types .= "s";
}

if ($status_filter !== 'all' && !empty($status_filter)) {
    $where_clauses[] = "status = ?";
    $params[] = $status_filter;
    $types .= "s";
}

$where_sql = implode(" AND ", $where_clauses);
$sql = "SELECT id, name, category, description, event_date, event_time, venue, coordinator, poster, status, created_at FROM events WHERE $where_sql ORDER BY event_date ASC";

$events = [];
if ($conn) {
    if (!empty($params)) {
        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) $events[] = $row;
        $stmt->close();
    } else {
        $res = $conn->query($sql);
        if ($res) while ($row = $res->fetch_assoc()) $events[] = $row;
    }
}

$csrf_token = get_csrf_token();
$nav_items = get_dynamic_navigation('events.php');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Event Management - StudentHub</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <a href="#main-content" class="skip-link">Skip to main content</a>
    <header class="app-header shadow-sm">
        <div class="container py-2">
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                <div class="d-flex align-items-center gap-3">
                    <img src="image/charusatlogo.png" alt="CHARUSAT Logo" class="brand-logo" onerror="this.style.display='none'">
                    <div>
                        <h1 class="brand-title mb-0">StudentHub Portal</h1>
                        <p class="brand-subtitle mb-0">CSPIT / CHARUSAT - 25CE082 Final Integration</p>
                    </div>
                </div>
                <div class="text-end">
                    <?php if ($is_logged_in): ?>
                        <span class="badge bg-light text-dark px-3 py-2 me-2">
                            <i class="bi bi-person-circle"></i> <?= htmlspecialchars($_SESSION['full_name'] ?? $_SESSION['username']) ?> (<?= htmlspecialchars($_SESSION['role']) ?>)
                        </span>
                        <a href="logout.php" class="btn btn-sm btn-outline-light">Logout</a>
                    <?php else: ?>
                        <a href="login.php" class="btn btn-sm btn-light text-primary fw-bold me-2">Login</a>
                        <a href="register.php" class="btn btn-sm btn-outline-light">Register</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </header>

    <nav class="app-nav" aria-label="Main Navigation">
        <div class="container d-flex flex-wrap gap-2 py-1">
            <?php foreach ($nav_items as $item): ?>
                <a href="<?= htmlspecialchars($item['url']) ?>" class="nav-item <?= $item['url'] === 'events.php' ? 'active' : '' ?>">
                    <i class="bi bi-<?= htmlspecialchars($item['icon']) ?>"></i> <?= htmlspecialchars($item['label']) ?>
                </a>
            <?php endforeach; ?>
        </div>
    </nav>

    <main id="main-content" class="container py-4 flex-grow-1">
        <?php if (!empty($message)): ?>
            <div class="alert alert-<?= $message_type ?> alert-dismissible fade show" role="alert">
                <i class="bi bi-info-circle-fill me-2"></i><?= htmlspecialchars($message) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
            <div>
                <h2 class="page-title mb-1">Campus Events Management</h2>
                <p class="text-muted mb-0">Event CRUD, poster upload with file validation, and real-time AJAX search (P-12, P-13).</p>
            </div>
            <?php if ($is_admin): ?>
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addEventModal">
                    <i class="bi bi-calendar-plus me-1"></i> Post New Event
                </button>
            <?php endif; ?>
        </div>

        <div class="card shadow-sm mb-4">
            <div class="card-body">
                <form method="GET" action="events.php" class="row g-2 align-items-center">
                    <div class="col-md-5">
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-search"></i></span>
                            <input type="text" id="eventLiveSearch" name="search" class="form-control" placeholder="Search events in real-time (AJAX debounce)..." value="<?= htmlspecialchars($search) ?>">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <select name="category" class="form-select">
                            <option value="all" <?= $category_filter === 'all' ? 'selected' : '' ?>>All Categories</option>
                            <option value="Technical" <?= $category_filter === 'Technical' ? 'selected' : '' ?>>Technical</option>
                            <option value="Cultural" <?= $category_filter === 'Cultural' ? 'selected' : '' ?>>Cultural</option>
                            <option value="Sports" <?= $category_filter === 'Sports' ? 'selected' : '' ?>>Sports</option>
                            <option value="Workshop" <?= $category_filter === 'Workshop' ? 'selected' : '' ?>>Workshop</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <select name="status" class="form-select">
                            <option value="all" <?= $status_filter === 'all' ? 'selected' : '' ?>>All Statuses</option>
                            <option value="Open" <?= $status_filter === 'Open' ? 'selected' : '' ?>>Open</option>
                            <option value="Closed" <?= $status_filter === 'Closed' ? 'selected' : '' ?>>Closed</option>
                        </select>
                    </div>
                    <div class="col-md-2 d-flex gap-2">
                        <button type="submit" class="btn btn-primary w-100"><i class="bi bi-funnel"></i> Apply</button>
                        <a href="events.php" class="btn btn-outline-secondary" title="Reset Filters"><i class="bi bi-arrow-counterclockwise"></i></a>
                    </div>
                </form>
            </div>
        </div>

        <div class="row g-4" id="eventsContainer">
            <?php if (empty($events)): ?>
                <div class="col-12 text-center py-5">
                    <div class="card shadow-sm p-5">
                        <i class="bi bi-calendar-x display-4 text-muted mb-3"></i>
                        <h5>No events match the specified criteria.</h5>
                        <p class="text-muted">Try clearing the search query or category filter.</p>
                    </div>
                </div>
            <?php else: ?>
                <?php foreach ($events as $ev): ?>
                    <div class="col-md-6 col-lg-4 event-item-card">
                        <div class="card event-card shadow-sm h-100">
                            <div class="event-poster-container bg-light text-center">
                                <?php
                                $poster_file = !empty($ev['poster']) ? (strpos($ev['poster'], 'uploads/') === 0 ? $ev['poster'] : 'uploads/' . $ev['poster']) : 'uploads/default_poster.png';
                                ?>
                                <img src="<?= htmlspecialchars($poster_file) ?>" alt="<?= htmlspecialchars($ev['name']) ?>" class="event-poster img-fluid" onerror="this.src='uploads/default_poster.png'">
                            </div>
                            <div class="card-body d-flex flex-column">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <span class="badge bg-primary"><?= htmlspecialchars($ev['category']) ?></span>
                                    <span class="badge bg-<?= $ev['status'] === 'Open' ? 'success' : 'danger' ?>"><?= htmlspecialchars($ev['status']) ?></span>
                                </div>
                                <h5 class="card-title fw-bold text-dark mb-2"><?= htmlspecialchars($ev['name']) ?></h5>
                                <p class="card-text text-muted small flex-grow-1">
                                    <?= htmlspecialchars(mb_strimwidth($ev['description'] ?? 'No description provided.', 0, 140, '...')) ?>
                                </p>
                                <div class="card-text text-muted small border-top pt-2 mt-2">
                                    <p class="mb-1"><i class="bi bi-calendar3 me-2 text-primary"></i><strong>Date:</strong> <?= htmlspecialchars($ev['event_date']) ?> at <?= htmlspecialchars(substr($ev['event_time'], 0, 8)) ?></p>
                                    <p class="mb-1"><i class="bi bi-geo-alt-fill me-2 text-danger"></i><strong>Venue:</strong> <?= htmlspecialchars($ev['venue']) ?></p>
                                    <p class="mb-2"><i class="bi bi-person-circle me-2 text-info"></i><strong>Coordinator:</strong> <?= htmlspecialchars($ev['coordinator']) ?></p>
                                </div>
                                <div class="d-flex gap-2 mt-2">
                                    <?php if ($ev['status'] === 'Open'): ?>
                                        <a href="registrations.php?event_id=<?= (int)$ev['id'] ?>" class="btn btn-outline-primary btn-sm flex-grow-1">
                                            <i class="bi bi-pencil-square me-1"></i> Register
                                        </a>
                                    <?php else: ?>
                                        <button class="btn btn-secondary btn-sm flex-grow-1" disabled>Registrations Closed</button>
                                    <?php endif; ?>

                                    <?php if ($is_admin): ?>
                                        <button type="button" class="btn btn-sm btn-outline-secondary" onclick='openEditEventModal(<?= json_encode($ev) ?>)' title="Edit Event">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <form method="POST" action="events.php" class="d-inline" onsubmit="return confirm('Toggle event status between Open and Closed?');">
                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                                            <input type="hidden" name="action" value="toggle_status">
                                            <input type="hidden" name="event_id" value="<?= (int)$ev['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-warning" title="Toggle Status">
                                                <i class="bi bi-toggle-<?= $ev['status'] === 'Open' ? 'on' : 'off' ?>"></i>
                                            </button>
                                        </form>
                                        <form method="POST" action="events.php" class="d-inline" onsubmit="return confirm('Permanently delete this event?');">
                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="event_id" value="<?= (int)$ev['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </main>

    <?php if ($is_admin): ?>
    <div class="modal fade" id="addEventModal" tabindex="-1" aria-labelledby="addEventModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form method="POST" action="events.php" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                    <input type="hidden" name="action" value="create">
                    <div class="modal-header bg-primary text-white">
                        <h5 class="modal-title" id="addEventModalLabel"><i class="bi bi-calendar-plus me-2"></i>Post New Event</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-md-8">
                                <label class="form-label fw-semibold">Event Name / Title <span class="text-danger">*</span></label>
                                <input type="text" name="name" class="form-control" placeholder="e.g. CodeCamp Hackathon 2026" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Category <span class="text-danger">*</span></label>
                                <select name="category" class="form-select" required>
                                    <option value="Technical">Technical</option>
                                    <option value="Cultural">Cultural</option>
                                    <option value="Sports">Sports</option>
                                    <option value="Workshop">Workshop</option>
                                </select>
                            </div>
                            <div class="col-12">
                                <label class="form-label fw-semibold">Description</label>
                                <textarea name="description" class="form-control" rows="3" placeholder="Provide event schedule, rules, and speaker details..."></textarea>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Event Date <span class="text-danger">*</span></label>
                                <input type="date" name="event_date" class="form-control" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Event Time</label>
                                <input type="text" name="event_time" class="form-control" value="10:00 AM">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Coordinator</label>
                                <input type="text" name="coordinator" class="form-control" value="Faculty Coordinator">
                            </div>
                            <div class="col-md-8">
                                <label class="form-label fw-semibold">Venue / Location <span class="text-danger">*</span></label>
                                <input type="text" name="venue" class="form-control" placeholder="e.g. CSPIT Auditorium" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Initial Status</label>
                                <select name="status" class="form-select">
                                    <option value="Open">Open</option>
                                    <option value="Closed">Closed</option>
                                </select>
                            </div>
                            <div class="col-12">
                                <label class="form-label fw-semibold">Event Poster Image (Max 2MB, JPG/PNG/WebP)</label>
                                <input type="file" name="poster" class="form-control" accept="image/png, image/jpeg, image/webp">
                                <div class="form-text small">Validated server-side for MIME type, file size, and sanitized storage name.</div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i> Publish Event</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="editEventModal" tabindex="-1" aria-labelledby="editEventModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form method="POST" action="events.php" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                    <input type="hidden" name="action" value="update">
                    <input type="hidden" name="event_id" id="edit_event_id">
                    <div class="modal-header bg-dark text-white">
                        <h5 class="modal-title" id="editEventModalLabel"><i class="bi bi-pencil-square me-2"></i>Edit Event Details</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-md-8">
                                <label class="form-label fw-semibold">Event Name / Title</label>
                                <input type="text" name="name" id="edit_name" class="form-control" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Category</label>
                                <select name="category" id="edit_category" class="form-select" required>
                                    <option value="Technical">Technical</option>
                                    <option value="Cultural">Cultural</option>
                                    <option value="Sports">Sports</option>
                                    <option value="Workshop">Workshop</option>
                                </select>
                            </div>
                            <div class="col-12">
                                <label class="form-label fw-semibold">Description</label>
                                <textarea name="description" id="edit_description" class="form-control" rows="3"></textarea>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Event Date</label>
                                <input type="date" name="event_date" id="edit_date" class="form-control" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Event Time</label>
                                <input type="text" name="event_time" id="edit_time" class="form-control">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Coordinator</label>
                                <input type="text" name="coordinator" id="edit_coordinator" class="form-control">
                            </div>
                            <div class="col-md-8">
                                <label class="form-label fw-semibold">Venue</label>
                                <input type="text" name="venue" id="edit_venue" class="form-control" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Status</label>
                                <select name="status" id="edit_status" class="form-select">
                                    <option value="Open">Open</option>
                                    <option value="Closed">Closed</option>
                                </select>
                            </div>
                            <div class="col-12">
                                <label class="form-label fw-semibold">Replace Poster (Optional)</label>
                                <input type="file" name="poster" class="form-control" accept="image/png, image/jpeg, image/webp">
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary"><i class="bi bi-check2-circle me-1"></i> Update Event</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <footer class="app-footer text-center py-3">
        <div class="container">
            <p class="mb-1 text-light fw-bold">MIRAL PATEL || 25CE082 || STUDENT HUB PORTAL</p>
            <p class="mb-0 text-white-50 small">&copy; <?= date('Y') ?> CSPIT / CHARUSAT. Practical 15 Final Integration & Deployment.</p>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    <?php if ($is_admin): ?>
    function openEditEventModal(ev) {
        document.getElementById('edit_event_id').value = ev.id;
        document.getElementById('edit_name').value = ev.name;
        document.getElementById('edit_category').value = ev.category;
        document.getElementById('edit_description').value = ev.description || '';
        document.getElementById('edit_date').value = ev.event_date;
        document.getElementById('edit_time').value = ev.event_time || '10:00 AM';
        document.getElementById('edit_coordinator').value = ev.coordinator || '';
        document.getElementById('edit_venue').value = ev.venue;
        document.getElementById('edit_status').value = ev.status;
        new bootstrap.Modal(document.getElementById('editEventModal')).show();
    }
    <?php endif; ?>

    let searchTimer = null;
    const searchInput = document.getElementById('eventLiveSearch');
    if (searchInput) {
        searchInput.addEventListener('input', function() {
            clearTimeout(searchTimer);
            const query = this.value.trim();
            searchTimer = setTimeout(() => {
                fetch('api/events.php?q=' + encodeURIComponent(query))
                    .then(res => res.json())
                    .then(data => {
                        if (data && data.success && data.data) {
                            renderEvents(data.data);
                        }
                    })
                    .catch(err => console.error(err));
            }, 300);
        });
    }

    function renderEvents(list) {
        const container = document.getElementById('eventsContainer');
        if (!container) return;
        if (!list || list.length === 0) {
            container.innerHTML = `
                <div class="col-12 text-center py-5">
                    <div class="card shadow-sm p-5">
                        <i class="bi bi-calendar-x display-4 text-muted mb-3"></i>
                        <h5>No matching events found.</h5>
                    </div>
                </div>`;
            return;
        }
        let html = '';
        list.forEach(ev => {
            const rawPoster = ev.poster ? ev.poster : 'default_poster.png';
            const poster = rawPoster.indexOf('uploads/') === 0 ? rawPoster : 'uploads/' + rawPoster;
            const statusClass = ev.status === 'Open' ? 'success' : 'danger';
            html += `
                <div class="col-md-6 col-lg-4 event-item-card">
                    <div class="card event-card shadow-sm h-100">
                        <div class="event-poster-container bg-light text-center">
                            <img src="${poster}" alt="${ev.name}" class="event-poster img-fluid" onerror="this.src='uploads/default_poster.png'">
                        </div>
                        <div class="card-body d-flex flex-column">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span class="badge bg-primary">${ev.category}</span>
                                <span class="badge bg-${statusClass}">${ev.status}</span>
                            </div>
                            <h5 class="card-title fw-bold text-dark mb-2">${ev.name}</h5>
                            <p class="card-text text-muted small flex-grow-1">${ev.description ? ev.description.substring(0, 140) + '...' : ''}</p>
                            <div class="card-text text-muted small border-top pt-2 mt-2">
                                <p class="mb-1"><i class="bi bi-calendar3 me-2 text-primary"></i><strong>Date:</strong> ${ev.event_date}</p>
                                <p class="mb-1"><i class="bi bi-geo-alt-fill me-2 text-danger"></i><strong>Venue:</strong> ${ev.venue}</p>
                            </div>
                            <div class="d-grid mt-2">
                                ${ev.status === 'Open'
                                    ? `<a href="registrations.php?event_id=${ev.id}" class="btn btn-outline-primary btn-sm"><i class="bi bi-pencil-square me-1"></i> Register</a>`
                                    : `<button class="btn btn-secondary btn-sm" disabled>Registrations Closed</button>`
                                }
                            </div>
                        </div>
                    </div>
                </div>`;
        });
        container.innerHTML = html;
    }
    </script>
</body>
</html>

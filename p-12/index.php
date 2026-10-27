<?php
require_once "db.php";

$message = "";
$error = "";

if (isset($_GET['msg'])) {
    if ($_GET['msg'] === 'added') $message = "New event created and poster uploaded successfully!";
    elseif ($_GET['msg'] === 'updated') $message = "Event details and poster updated successfully!";
    elseif ($_GET['msg'] === 'deleted') $message = "Event record and associated poster removed successfully.";
    elseif ($_GET['msg'] === 'status_toggled') $message = "Event registration status changed successfully.";
}

if (isset($_GET['err'])) {
    if ($_GET['err'] === 'invalid_id') $error = "Invalid event ID provided.";
    elseif ($_GET['err'] === 'not_found') $error = "Event record not found.";
    elseif ($_GET['err'] === 'delete_failed') $error = "Failed to execute delete operation.";
    elseif ($_GET['err'] === 'update_failed') $error = "Failed to update event record.";
}

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['action']) && $_POST['action'] === 'add_event') {
    $name = isset($_POST['name']) ? trim($_POST['name']) : '';
    $category = isset($_POST['category']) ? trim($_POST['category']) : 'Technical';
    $event_date = isset($_POST['event_date']) ? trim($_POST['event_date']) : '';
    $event_time = isset($_POST['event_time']) ? trim($_POST['event_time']) : '';
    $venue = isset($_POST['venue']) ? trim($_POST['venue']) : '';
    $coordinator = isset($_POST['coordinator']) ? trim($_POST['coordinator']) : '';
    $status = isset($_POST['status']) && in_array($_POST['status'], ['Open', 'Closed']) ? $_POST['status'] : 'Open';
    $description = isset($_POST['description']) ? trim($_POST['description']) : '';

    if (empty($name) || empty($event_date) || empty($event_time) || empty($venue) || empty($coordinator)) {
        $error = "Please fill in all required event details.";
    } else {
        $poster_filename = 'default_poster.png';

        if (isset($_FILES['poster']) && $_FILES['poster']['error'] === UPLOAD_ERR_OK) {
            $file_tmp = $_FILES['poster']['tmp_name'];
            $file_size = $_FILES['poster']['size'];
            $file_orig_name = $_FILES['poster']['name'];
            $file_ext = strtolower(pathinfo($file_orig_name, PATHINFO_EXTENSION));

            $allowed_exts = ['jpg', 'jpeg', 'png', 'webp'];
            $max_size = 2 * 1024 * 1024;

            if ($file_size > $max_size) {
                $error = "File Upload Error: Poster exceeds maximum allowed size of 2MB.";
            } elseif (!in_array($file_ext, $allowed_exts)) {
                $error = "File Upload Error: Invalid format (.{$file_ext}). Only JPG, PNG, and WebP are allowed.";
            } else {
                $check_img = @getimagesize($file_tmp);
                if ($check_img === false) {
                    $error = "File Upload Error: The uploaded file is not a valid image.";
                } else {
                    $new_filename = 'poster_' . time() . '_' . bin2hex(random_bytes(6)) . '.' . $file_ext;
                    $upload_target = __DIR__ . '/uploads/' . $new_filename;

                    if (move_uploaded_file($file_tmp, $upload_target)) {
                        $poster_filename = $new_filename;
                    } else {
                        $error = "Failed to move uploaded file to uploads directory.";
                    }
                }
            }
        } elseif (isset($_FILES['poster']) && $_FILES['poster']['error'] !== UPLOAD_ERR_NO_FILE) {
            $error = "File Upload Error code: " . $_FILES['poster']['error'];
        }

        if (empty($error)) {
            $ins = $conn->prepare("INSERT INTO events (name, category, event_date, event_time, venue, coordinator, status, poster, description) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $ins->bind_param("sssssssss", $name, $category, $event_date, $event_time, $venue, $coordinator, $status, $poster_filename, $description);

            if ($ins->execute()) {
                $new_event_id = $conn->insert_id;
                $ins->close();

                $ip = !empty($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '127.0.0.1';
                $ua = !empty($_SERVER['HTTP_USER_AGENT']) ? substr($_SERVER['HTTP_USER_AGENT'], 0, 255) : 'Unknown';
                $act = "EVENT_CREATED";
                $st = "SUCCESS";
                $un = "admin_hub";
                $log_stmt = $conn->prepare("INSERT INTO audit_logs (username, action, status, ip_address, user_agent) VALUES (?, ?, ?, ?, ?)");
                if ($log_stmt) {
                    $log_stmt->bind_param("sssss", $un, $act, $st, $ip, $ua);
                    $log_stmt->execute();
                    $log_stmt->close();
                }

                $message = "Event '{$name}' created successfully with poster '{$poster_filename}'!";
            } else {
                $error = "Database error: " . $conn->error;
                $ins->close();
            }
        }
    }
}

$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$category = isset($_GET['category']) ? trim($_GET['category']) : '';
$status = isset($_GET['status']) ? trim($_GET['status']) : '';

$sql = "SELECT id, name, category, event_date, event_time, venue, coordinator, status, poster, description, created_at FROM events WHERE 1=1";
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
$events_result = $stmt->get_result();
$events = [];
while ($row = $events_result->fetch_assoc()) {
    $events[] = $row;
}
$stmt->close();

$res_all = $conn->query("SELECT COUNT(*) AS total FROM events");
$count_total = $res_all ? $res_all->fetch_assoc()['total'] : 0;

$res_open = $conn->query("SELECT COUNT(*) AS total FROM events WHERE status = 'Open'");
$count_open = $res_open ? $res_open->fetch_assoc()['total'] : 0;

$res_closed = $conn->query("SELECT COUNT(*) AS total FROM events WHERE status = 'Closed'");
$count_closed = $res_closed ? $res_closed->fetch_assoc()['total'] : 0;

$audit_logs = [];
$res_logs = $conn->query("SELECT id, username, action, status, ip_address, created_at FROM audit_logs WHERE action LIKE 'EVENT_%' ORDER BY id DESC LIMIT 10");
if ($res_logs) {
    while ($l = $res_logs->fetch_assoc()) {
        $audit_logs[] = $l;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>StudentHub - Event Management CRUD (Practical 12)</title>
    <link rel="stylesheet" href="style.css">
    <script>
        function openAddModal() {
            document.getElementById("addEventModal").style.display = "block";
        }
        function closeAddModal() {
            document.getElementById("addEventModal").style.display = "none";
        }
        window.onclick = function(event) {
            var modal = document.getElementById("addEventModal");
            if (event.target == modal) {
                modal.style.display = "none";
            }
        }
        function previewPoster(input) {
            if (input.files && input.files[0]) {
                var reader = new FileReader();
                reader.onload = function(e) {
                    var preview = document.getElementById("posterPreview");
                    preview.src = e.target.result;
                    preview.style.display = "block";
                }
                reader.readAsDataURL(input.files[0]);
            }
        }
    </script>
</head>
<body>
    <header id="header">
        <img src="image/charusatlogo.png" alt="CHARUSAT Logo">
        <div>StudentHub - Event Management System</div>
        <nav>
            <a href="index.php" class="active">Manage Events</a>
            <a href="setup_database.php">Setup DB</a>
        </nav>
    </header>

    <main class="main-content">
        <section class="container">
            <h2>Practical 12: Event Management CRUD with Poster Upload &amp; Validation</h2>
            <div class="info-box">
                Features: MySQLi CRUD, Multi-tier File Upload Validation (Type: JPG/PNG/WebP, Size: &le; 2MB, <code>getimagesize()</code>), Secure Auto-renaming, <code>uploads/.htaccess</code> Directory Protection, Category &amp; Status Filtering, and Event Audit Logging.
            </div>

            <?php if (!empty($message)): ?>
                <div class="alert-success">
                    &check; <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($error)): ?>
                <div class="alert-error">
                    &cross; <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-label">Total Events</div>
                    <div class="stat-number"><?php echo $count_total; ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Open for Registration</div>
                    <div class="stat-number" style="color: #10b981;"><?php echo $count_open; ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Closed Events</div>
                    <div class="stat-number" style="color: #ef4444;"><?php echo $count_closed; ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Matching Query Results</div>
                    <div class="stat-number" style="color: #2563eb;"><?php echo count($events); ?></div>
                </div>
            </div>

            <div class="search-filter-card">
                <form action="index.php" method="GET" class="search-filter-form">
                    <input type="text" name="search" class="search-input" placeholder="Search by Event Name, Venue, or Coordinator..." value="<?php echo htmlspecialchars($search); ?>">

                    <select name="category" class="filter-select">
                        <option value="">All Categories (Intermediate)</option>
                        <option value="Technical" <?php echo $category === 'Technical' ? 'selected' : ''; ?>>Technical</option>
                        <option value="Cultural" <?php echo $category === 'Cultural' ? 'selected' : ''; ?>>Cultural</option>
                        <option value="Sports" <?php echo $category === 'Sports' ? 'selected' : ''; ?>>Sports</option>
                        <option value="Workshop" <?php echo $category === 'Workshop' ? 'selected' : ''; ?>>Workshop</option>
                        <option value="Seminar" <?php echo $category === 'Seminar' ? 'selected' : ''; ?>>Seminar</option>
                    </select>

                    <select name="status" class="filter-select" style="min-width: 140px;">
                        <option value="">All Statuses (Intermediate)</option>
                        <option value="Open" <?php echo $status === 'Open' ? 'selected' : ''; ?>>Open Only</option>
                        <option value="Closed" <?php echo $status === 'Closed' ? 'selected' : ''; ?>>Closed Only</option>
                    </select>

                    <button type="submit" class="btn-primary">Filter Events</button>
                    <a href="index.php" class="btn-secondary">Reset</a>
                </form>
            </div>

            <div class="action-bar">
                <button type="button" class="btn-add" onclick="openAddModal();">+ Add New Event with Poster</button>
                <div style="font-size: 13px; color: #475569;">
                    Displaying <strong><?php echo count($events); ?></strong> events
                </div>
            </div>

            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Poster</th>
                            <th>Event Name</th>
                            <th>Category</th>
                            <th>Date &amp; Time</th>
                            <th>Venue</th>
                            <th>Coordinator</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($events)): ?>
                            <tr>
                                <td colspan="9" style="padding: 25px; color: #64748b; font-size: 14px;">
                                    No events found matching your criteria. Click "Reset" to view all events.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($events as $ev): ?>
                                <tr>
                                    <td><?php echo $ev['id']; ?></td>
                                    <td>
                                        <a href="uploads/<?php echo htmlspecialchars($ev['poster']); ?>" target="_blank" title="View Full Poster">
                                            <img src="uploads/<?php echo htmlspecialchars($ev['poster']); ?>" alt="Poster" class="poster-thumb" onerror="this.src='image/charusatlogo.png';">
                                        </a>
                                    </td>
                                    <td style="text-align: left; font-weight: bold;">
                                        <?php echo htmlspecialchars($ev['name']); ?>
                                        <?php if (!empty($ev['description'])): ?>
                                            <div style="font-size: 11px; color: #64748b; font-weight: normal; margin-top: 3px;">
                                                <?php echo htmlspecialchars(substr($ev['description'], 0, 75)); ?><?php echo strlen($ev['description']) > 75 ? '...' : ''; ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php
                                        $cat = $ev['category'];
                                        $cat_class = 'badge-tech';
                                        if ($cat === 'Cultural') $cat_class = 'badge-cult';
                                        elseif ($cat === 'Sports') $cat_class = 'badge-sports';
                                        elseif ($cat === 'Workshop') $cat_class = 'badge-work';
                                        elseif ($cat === 'Seminar') $cat_class = 'badge-semi';
                                        ?>
                                        <span class="badge <?php echo $cat_class; ?>"><?php echo htmlspecialchars($cat); ?></span>
                                    </td>
                                    <td>
                                        <div><strong><?php echo htmlspecialchars($ev['event_date']); ?></strong></div>
                                        <div style="font-size: 11px; color: #64748b;"><?php echo htmlspecialchars($ev['event_time']); ?></div>
                                    </td>
                                    <td><?php echo htmlspecialchars($ev['venue']); ?></td>
                                    <td><?php echo htmlspecialchars($ev['coordinator']); ?></td>
                                    <td>
                                        <?php if ($ev['status'] === 'Open'): ?>
                                            <span class="badge badge-open">Open</span>
                                        <?php else: ?>
                                            <span class="badge badge-closed">Closed</span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="white-space: nowrap;">
                                        <a href="toggle_status.php?id=<?php echo $ev['id']; ?>" class="btn-toggle" title="Toggle Open/Closed status">
                                            <?php echo ($ev['status'] === 'Open') ? 'Close' : 'Open'; ?>
                                        </a>
                                        <a href="edit_event.php?id=<?php echo $ev['id']; ?>" class="btn-edit">Edit</a>
                                        <a href="delete_event.php?id=<?php echo $ev['id']; ?>" class="btn-delete" onclick="return confirm('Are you sure you want to delete event <?php echo htmlspecialchars(addslashes($ev['name'])); ?> and its poster file?');">Delete</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <?php if (!empty($audit_logs)): ?>
                <h3 style="color: #1E3A8A; margin-top: 35px;">Event Change Audit Logs (Advanced Extension)</h3>
                <div class="info-box" style="margin-bottom: 10px;">
                    Tracks administrative event operations (Create, Update, Delete, Status Toggle) with IP address and timestamps for system accountability.
                </div>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>Log ID</th>
                                <th>User</th>
                                <th>Action</th>
                                <th>Status</th>
                                <th>IP Address</th>
                                <th>Timestamp</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($audit_logs as $al): ?>
                                <tr>
                                    <td><?php echo $al['id']; ?></td>
                                    <td><strong><?php echo htmlspecialchars($al['username']); ?></strong></td>
                                    <td><code><?php echo htmlspecialchars($al['action']); ?></code></td>
                                    <td><span class="badge badge-open"><?php echo htmlspecialchars($al['status']); ?></span></td>
                                    <td><?php echo htmlspecialchars($al['ip_address']); ?></td>
                                    <td style="font-size: 12px;"><?php echo htmlspecialchars($al['created_at']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>
    </main>

    <div id="addEventModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 style="margin: 0; color: #1E3A8A;">Create New Event with Poster (CREATE)</h3>
                <button type="button" class="close-btn" onclick="closeAddModal();">&times;</button>
            </div>

            <form action="index.php" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="add_event">

                <div class="form-grid">
                    <div class="form-group full-width">
                        <label for="m_name">Event Title / Name <span class="req">*</span></label>
                        <input type="text" id="m_name" name="name" required placeholder="e.g. CodeSprint 2026">
                    </div>

                    <div class="form-group">
                        <label for="m_category">Category (Intermediate) <span class="req">*</span></label>
                        <select id="m_category" name="category" required>
                            <option value="Technical">Technical</option>
                            <option value="Cultural">Cultural</option>
                            <option value="Sports">Sports</option>
                            <option value="Workshop">Workshop</option>
                            <option value="Seminar">Seminar</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="m_status">Registration Status (Intermediate) <span class="req">*</span></label>
                        <select id="m_status" name="status" required>
                            <option value="Open">Open</option>
                            <option value="Closed">Closed</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="m_event_date">Event Date <span class="req">*</span></label>
                        <input type="date" id="m_event_date" name="event_date" required value="<?php echo date('Y-m-d', strtotime('+7 days')); ?>">
                    </div>

                    <div class="form-group">
                        <label for="m_event_time">Event Time <span class="req">*</span></label>
                        <input type="text" id="m_event_time" name="event_time" required placeholder="e.g. 10:00 AM">
                    </div>

                    <div class="form-group">
                        <label for="m_venue">Venue / Location <span class="req">*</span></label>
                        <input type="text" id="m_venue" name="venue" required placeholder="e.g. Auditorium, CSPIT">
                    </div>

                    <div class="form-group">
                        <label for="m_coordinator">Coordinator <span class="req">*</span></label>
                        <input type="text" id="m_coordinator" name="coordinator" required placeholder="e.g. Prof. R. K. Patel">
                    </div>

                    <div class="form-group full-width">
                        <label for="m_poster">Upload Event Poster Image</label>
                        <input type="file" id="m_poster" name="poster" accept=".jpg,.jpeg,.png,.webp" onchange="previewPoster(this);">
                        <span class="hint">Validation: JPG, PNG, WebP only | Max file size: 2MB | Auto-renamed securely on upload</span>
                        <img id="posterPreview" src="#" alt="Preview" style="display: none; max-width: 150px; max-height: 120px; margin-top: 10px; border-radius: 4px; border: 1px solid #1E3A8A;">
                    </div>

                    <div class="form-group full-width">
                        <label for="m_description">Event Description</label>
                        <textarea id="m_description" name="description" rows="3" placeholder="Brief overview of event schedule and eligibility..."></textarea>
                    </div>
                </div>

                <div class="btn-row" style="margin-top: 25px;">
                    <button type="submit" class="btn-submit" style="flex: 1;">Create Event</button>
                    <button type="button" class="btn-secondary" onclick="closeAddModal();">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <footer>
        MIRAL PATEL||25CE082||STUDENT HUB PORTAL
    </footer>
</body>
</html>

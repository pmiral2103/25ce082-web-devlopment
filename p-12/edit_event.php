<?php
require_once "db.php";

$error = "";
$event = null;

$id = isset($_GET['id']) ? intval($_GET['id']) : (isset($_POST['id']) ? intval($_POST['id']) : 0);

if ($id <= 0 || $conn === null) {
    header("Location: index.php?err=invalid_id");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $name = isset($_POST['name']) ? trim($_POST['name']) : '';
    $category = isset($_POST['category']) ? trim($_POST['category']) : 'Technical';
    $event_date = isset($_POST['event_date']) ? trim($_POST['event_date']) : '';
    $event_time = isset($_POST['event_time']) ? trim($_POST['event_time']) : '';
    $venue = isset($_POST['venue']) ? trim($_POST['venue']) : '';
    $coordinator = isset($_POST['coordinator']) ? trim($_POST['coordinator']) : '';
    $status = isset($_POST['status']) && in_array($_POST['status'], ['Open', 'Closed']) ? $_POST['status'] : 'Open';
    $description = isset($_POST['description']) ? trim($_POST['description']) : '';
    $current_poster = isset($_POST['current_poster']) ? trim($_POST['current_poster']) : 'default_poster.png';

    if (empty($name) || empty($event_date) || empty($event_time) || empty($venue) || empty($coordinator)) {
        $error = "All required fields must be filled.";
    } else {
        $poster_filename = $current_poster;

        if (isset($_FILES['poster']) && $_FILES['poster']['error'] === UPLOAD_ERR_OK) {
            $file_tmp = $_FILES['poster']['tmp_name'];
            $file_size = $_FILES['poster']['size'];
            $file_orig_name = $_FILES['poster']['name'];
            $file_ext = strtolower(pathinfo($file_orig_name, PATHINFO_EXTENSION));

            $allowed_exts = ['jpg', 'jpeg', 'png', 'webp'];
            $max_size = 2 * 1024 * 1024;

            if ($file_size > $max_size) {
                $error = "Poster file size exceeds the 2MB limit.";
            } elseif (!in_array($file_ext, $allowed_exts)) {
                $error = "Invalid file extension. Only JPG, PNG, and WebP are allowed.";
            } else {
                $check_img = @getimagesize($file_tmp);
                if ($check_img === false) {
                    $error = "The uploaded file is not a valid image.";
                } else {
                    $new_filename = 'poster_' . time() . '_' . bin2hex(random_bytes(6)) . '.' . $file_ext;
                    $upload_target = __DIR__ . '/uploads/' . $new_filename;

                    if (move_uploaded_file($file_tmp, $upload_target)) {
                        if ($current_poster !== 'default_poster.png' && file_exists(__DIR__ . '/uploads/' . $current_poster)) {
                            @unlink(__DIR__ . '/uploads/' . $current_poster);
                        }
                        $poster_filename = $new_filename;
                    } else {
                        $error = "Failed to upload and save new poster.";
                    }
                }
            }
        }

        if (empty($error)) {
            $upd = $conn->prepare("UPDATE events SET name = ?, category = ?, event_date = ?, event_time = ?, venue = ?, coordinator = ?, status = ?, poster = ?, description = ? WHERE id = ?");
            $upd->bind_param("sssssssssi", $name, $category, $event_date, $event_time, $venue, $coordinator, $status, $poster_filename, $description, $id);

            if ($upd->execute()) {
                $ip = !empty($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '127.0.0.1';
                $ua = !empty($_SERVER['HTTP_USER_AGENT']) ? substr($_SERVER['HTTP_USER_AGENT'], 0, 255) : 'Unknown';
                $act = "EVENT_UPDATED";
                $st = "SUCCESS";
                $un = "admin_hub";
                $log_stmt = $conn->prepare("INSERT INTO audit_logs (username, action, status, ip_address, user_agent) VALUES (?, ?, ?, ?, ?)");
                if ($log_stmt) {
                    $log_stmt->bind_param("sssss", $un, $act, $st, $ip, $ua);
                    $log_stmt->execute();
                    $log_stmt->close();
                }

                header("Location: index.php?msg=updated");
                exit();
            } else {
                $error = "Database update error: " . $conn->error;
            }
            $upd->close();
        }
    }
}

$stmt = $conn->prepare("SELECT * FROM events WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$res = $stmt->get_result();
if ($res->num_rows === 0) {
    header("Location: index.php?err=not_found");
    exit();
}
$event = $res->fetch_assoc();
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>StudentHub - Edit Event (Practical 12)</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <header id="header">
        <img src="image/charusatlogo.png" alt="CHARUSAT Logo">
        <div>StudentHub - Event Management System</div>
        <nav>
            <a href="index.php">Manage Events</a>
            <a href="setup_database.php">Setup DB</a>
        </nav>
    </header>

    <main class="main-content" style="max-width: 800px;">
        <section class="container">
            <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid #87CEEB; padding-bottom: 10px; margin-bottom: 15px;">
                <h2 style="margin: 0;">Edit Event: <?php echo htmlspecialchars($event['name']); ?></h2>
                <a href="index.php" class="btn-secondary">&larr; Back to Events</a>
            </div>

            <?php if (!empty($error)): ?>
                <div class="alert-error">
                    &cross; <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <form action="edit_event.php?id=<?php echo $id; ?>" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="id" value="<?php echo $id; ?>">
                <input type="hidden" name="current_poster" value="<?php echo htmlspecialchars($event['poster']); ?>">

                <div class="poster-preview-box">
                    <label style="display: block; margin-bottom: 8px;">Current Event Poster:</label>
                    <img src="uploads/<?php echo htmlspecialchars($event['poster']); ?>" alt="Poster Preview" class="poster-preview-img" onerror="this.src='image/charusatlogo.png';">
                    <div style="font-size: 11px; color: #64748b; margin-top: 5px;">File: <?php echo htmlspecialchars($event['poster']); ?></div>
                </div>

                <div class="form-grid">
                    <div class="form-group full-width">
                        <label for="name">Event Title / Name <span class="req">*</span></label>
                        <input type="text" id="name" name="name" required value="<?php echo htmlspecialchars($event['name']); ?>">
                    </div>

                    <div class="form-group">
                        <label for="category">Category (Intermediate) <span class="req">*</span></label>
                        <select id="category" name="category" required>
                            <option value="Technical" <?php echo $event['category'] === 'Technical' ? 'selected' : ''; ?>>Technical</option>
                            <option value="Cultural" <?php echo $event['category'] === 'Cultural' ? 'selected' : ''; ?>>Cultural</option>
                            <option value="Sports" <?php echo $event['category'] === 'Sports' ? 'selected' : ''; ?>>Sports</option>
                            <option value="Workshop" <?php echo $event['category'] === 'Workshop' ? 'selected' : ''; ?>>Workshop</option>
                            <option value="Seminar" <?php echo $event['category'] === 'Seminar' ? 'selected' : ''; ?>>Seminar</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="status">Event Status (Intermediate) <span class="req">*</span></label>
                        <select id="status" name="status" required>
                            <option value="Open" <?php echo $event['status'] === 'Open' ? 'selected' : ''; ?>>Open for Registrations</option>
                            <option value="Closed" <?php echo $event['status'] === 'Closed' ? 'selected' : ''; ?>>Closed</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="event_date">Event Date <span class="req">*</span></label>
                        <input type="date" id="event_date" name="event_date" required value="<?php echo htmlspecialchars($event['event_date']); ?>">
                    </div>

                    <div class="form-group">
                        <label for="event_time">Event Time <span class="req">*</span></label>
                        <input type="text" id="event_time" name="event_time" required value="<?php echo htmlspecialchars($event['event_time']); ?>">
                    </div>

                    <div class="form-group">
                        <label for="venue">Venue / Location <span class="req">*</span></label>
                        <input type="text" id="venue" name="venue" required value="<?php echo htmlspecialchars($event['venue']); ?>">
                    </div>

                    <div class="form-group">
                        <label for="coordinator">Faculty / Student Coordinator <span class="req">*</span></label>
                        <input type="text" id="coordinator" name="coordinator" required value="<?php echo htmlspecialchars($event['coordinator']); ?>">
                    </div>

                    <div class="form-group full-width">
                        <label for="poster">Upload New Poster (Optional)</label>
                        <input type="file" id="poster" name="poster" accept=".jpg,.jpeg,.png,.webp">
                        <span class="hint">Allowed types: JPG, PNG, WebP | Max file size: 2MB | Auto-renamed securely on upload</span>
                    </div>

                    <div class="form-group full-width">
                        <label for="description">Event Description / Details</label>
                        <textarea id="description" name="description" rows="3"><?php echo htmlspecialchars($event['description']); ?></textarea>
                    </div>
                </div>

                <div class="btn-row" style="margin-top: 25px;">
                    <button type="submit" class="btn-submit" style="flex: 1;">Save Event Changes</button>
                    <a href="index.php" class="btn-secondary">Cancel</a>
                </div>
            </form>
        </section>
    </main>

    <footer>
        MIRAL PATEL||25CE082||STUDENT HUB PORTAL
    </footer>
</body>
</html>

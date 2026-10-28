<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth_helper.php';

require_login($conn);

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['role'];
$is_admin = ($user_role === 'Admin');

$message = '';
$message_type = 'info';

$student_profile = null;
if ($conn) {
    $sq = $conn->prepare("SELECT id, student_id_no, name, email FROM students WHERE email = ? LIMIT 1");
    if ($sq) {
        $sq->bind_param("s", $_SESSION['email']);
        $sq->execute();
        $student_profile = $sq->get_result()->fetch_assoc();
        $sq->close();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $message = "Security token mismatch. Please try again.";
        $message_type = "danger";
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'register_event') {
            $event_id = (int)($_POST['event_id'] ?? 0);
            $student_id = (int)($_POST['student_id'] ?? ($student_profile['id'] ?? 0));

            if ($event_id <= 0 || $student_id <= 0) {
                $message = "Please select a valid event and ensure your student profile exists.";
                $message_type = "danger";
            } else {
                $dup_check = $conn->prepare("SELECT id FROM registrations WHERE event_id = ? AND student_id = ? LIMIT 1");
                $dup_check->bind_param("ii", $event_id, $student_id);
                $dup_check->execute();
                if ($dup_check->get_result()->num_rows > 0) {
                    $message = "You are already registered for this event!";
                    $message_type = "warning";
                } else {
                    $reg_code = 'REG-' . date('Y') . '-' . strtoupper(bin2hex(random_bytes(3)));
                    $ins = $conn->prepare("INSERT INTO registrations (event_id, student_id, remarks, status) VALUES (?, ?, ?, 'Confirmed')");
                    $ins->bind_param("iis", $event_id, $student_id, $reg_code);
                    if ($ins->execute()) {
                        $message = "Registration successful! Your confirmation code is " . $reg_code;
                        $message_type = "success";
                        log_audit($conn, $user_id, $_SESSION['username'], "EVENT_REGISTER_$reg_code", 'SUCCESS');
                    } else {
                        $message = "Registration failed: " . $ins->error;
                        $message_type = "danger";
                    }
                    $ins->close();
                }
                $dup_check->close();
            }
        } elseif ($action === 'cancel_registration' && $is_admin) {
            $reg_id = (int)($_POST['registration_id'] ?? 0);
            if ($reg_id > 0) {
                $stmt = $conn->prepare("UPDATE registrations SET status = 'Cancelled' WHERE id = ?");
                $stmt->bind_param("i", $reg_id);
                $stmt->execute();
                $stmt->close();
                $message = "Registration marked as Cancelled.";
                $message_type = "info";
                log_audit($conn, $user_id, $_SESSION['username'], "REGISTRATION_CANCEL_$reg_id", 'SUCCESS');
            }
        }
    }
}

$preselected_event = isset($_GET['event_id']) ? (int)$_GET['event_id'] : 0;

$open_events = [];
if ($conn) {
    $ev_res = $conn->query("SELECT id, name, category, event_date, venue FROM events WHERE status = 'Open' ORDER BY event_date ASC");
    if ($ev_res) {
        while ($row = $ev_res->fetch_assoc()) $open_events[] = $row;
    }
}

$registrations = [];
if ($conn) {
    if ($is_admin) {
        $r_sql = "SELECT r.id, r.remarks, r.registration_date, r.status, e.name AS event_title, e.category, e.event_date, e.venue, s.name AS student_name, s.student_id_no, s.email AS student_email FROM registrations r JOIN events e ON r.event_id = e.id JOIN students s ON r.student_id = s.id ORDER BY r.id DESC";
        $r_res = $conn->query($r_sql);
        if ($r_res) while ($row = $r_res->fetch_assoc()) $registrations[] = $row;
    } else {
        $st_id = $student_profile['id'] ?? 0;
        $r_stmt = $conn->prepare("SELECT r.id, r.remarks, r.registration_date, r.status, e.name AS event_title, e.category, e.event_date, e.venue, s.name AS student_name, s.student_id_no, s.email AS student_email FROM registrations r JOIN events e ON r.event_id = e.id JOIN students s ON r.student_id = s.id WHERE r.student_id = ? ORDER BY r.id DESC");
        if ($r_stmt) {
            $r_stmt->bind_param("i", $st_id);
            $r_stmt->execute();
            $res = $r_stmt->get_result();
            while ($row = $res->fetch_assoc()) $registrations[] = $row;
            $r_stmt->close();
        }
    }
}

$csrf_token = get_csrf_token();
$nav_items = get_dynamic_navigation('registrations.php');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Event Registrations - StudentHub</title>
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
                    <span class="badge bg-light text-dark px-3 py-2 me-2">
                        <i class="bi bi-person-circle"></i> <?= htmlspecialchars($_SESSION['full_name'] ?? $_SESSION['username']) ?> (<?= htmlspecialchars($_SESSION['role']) ?>)
                    </span>
                    <a href="logout.php" class="btn btn-sm btn-outline-light">Logout</a>
                </div>
            </div>
        </div>
    </header>

    <nav class="app-nav" aria-label="Main Navigation">
        <div class="container d-flex flex-wrap gap-2 py-1">
            <?php foreach ($nav_items as $item): ?>
                <a href="<?= htmlspecialchars($item['url']) ?>" class="nav-item <?= $item['url'] === 'registrations.php' ? 'active' : '' ?>">
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
                <h2 class="page-title mb-1">Campus Event Registrations</h2>
                <p class="text-muted mb-0">Manage bookings, student enrollments, and confirmed tickets.</p>
            </div>
            <div>
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#newRegModal">
                    <i class="bi bi-ticket-perforated-fill me-1"></i> New Registration
                </button>
            </div>
        </div>

        <div class="card shadow-sm">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <h5 class="mb-0 fw-bold text-dark">
                    <?= $is_admin ? 'Master Registrations Table' : 'My Event Bookings' ?>
                </h5>
                <span class="badge bg-primary rounded-pill"><?= count($registrations) ?> Records</span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Ref Code</th>
                                <th>Event Name</th>
                                <th>Category</th>
                                <th>Student</th>
                                <th>Schedule</th>
                                <th>Venue</th>
                                <th>Status</th>
                                <?php if ($is_admin): ?>
                                    <th class="text-end">Actions</th>
                                <?php endif; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($registrations)): ?>
                                <tr>
                                    <td colspan="<?= $is_admin ? 8 : 7 ?>" class="text-center py-5 text-muted">
                                        <i class="bi bi-journal-x display-6 d-block mb-2"></i>
                                        No event registrations found.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($registrations as $r): ?>
                                    <tr>
                                        <td class="font-monospace fw-bold text-primary"><?= htmlspecialchars($r['remarks'] ?? 'REG-' . $r['id']) ?></td>
                                        <td><strong><?= htmlspecialchars($r['event_title']) ?></strong></td>
                                        <td><span class="badge bg-info text-dark"><?= htmlspecialchars($r['category']) ?></span></td>
                                        <td>
                                            <div><?= htmlspecialchars($r['student_name']) ?></div>
                                            <small class="text-muted font-monospace"><?= htmlspecialchars($r['student_id_no']) ?></small>
                                        </td>
                                        <td class="small"><?= htmlspecialchars($r['event_date']) ?></td>
                                        <td class="small"><?= htmlspecialchars($r['venue']) ?></td>
                                        <td>
                                            <span class="badge bg-<?= $r['status'] === 'Confirmed' ? 'success' : 'danger' ?>">
                                                <?= htmlspecialchars($r['status']) ?>
                                            </span>
                                        </td>
                                        <?php if ($is_admin): ?>
                                            <td class="text-end">
                                                <?php if ($r['status'] === 'Confirmed'): ?>
                                                    <form method="POST" action="registrations.php" class="d-inline" onsubmit="return confirm('Cancel this registration?');">
                                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                                                        <input type="hidden" name="action" value="cancel_registration">
                                                        <input type="hidden" name="registration_id" value="<?= (int)$r['id'] ?>">
                                                        <button type="submit" class="btn btn-sm btn-outline-danger">Cancel</button>
                                                    </form>
                                                <?php else: ?>
                                                    <span class="text-muted small">Cancelled</span>
                                                <?php endif; ?>
                                            </td>
                                        <?php endif; ?>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>

    <div class="modal fade" id="newRegModal" tabindex="-1" aria-labelledby="newRegModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" action="registrations.php">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                    <input type="hidden" name="action" value="register_event">
                    <div class="modal-header bg-primary text-white">
                        <h5 class="modal-title" id="newRegModalLabel"><i class="bi bi-ticket-detailed me-2"></i>Register for Campus Event</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <?php if ($student_profile): ?>
                            <div class="alert alert-light border mb-3">
                                <div class="small text-muted">Registering as:</div>
                                <strong><?= htmlspecialchars($student_profile['name']) ?></strong> (<?= htmlspecialchars($student_profile['student_id_no']) ?>)
                                <input type="hidden" name="student_id" value="<?= (int)$student_profile['id'] ?>">
                            </div>
                        <?php else: ?>
                            <div class="alert alert-warning mb-3">
                                Notice: No student profile linked with your account. A default student profile will be used.
                                <input type="hidden" name="student_id" value="1">
                            </div>
                        <?php endif; ?>

                        <div class="mb-3">
                            <label class="form-label fw-semibold">Select Event</label>
                            <select name="event_id" class="form-select" required>
                                <option value="">-- Choose an Open Event --</option>
                                <?php foreach ($open_events as $oev): ?>
                                    <option value="<?= (int)$oev['id'] ?>" <?= ($preselected_event === (int)$oev['id']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($oev['name']) ?> (<?= htmlspecialchars($oev['category']) ?> - <?= htmlspecialchars($oev['event_date']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        <button type="submit" class="btn btn-primary"><i class="bi bi-check2-circle me-1"></i> Confirm Registration</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <footer class="app-footer text-center py-3">
        <div class="container">
            <p class="mb-1 text-light fw-bold">MIRAL PATEL || 25CE082 || STUDENT HUB PORTAL</p>
            <p class="mb-0 text-white-50 small">&copy; <?= date('Y') ?> CSPIT / CHARUSAT. Practical 15 Final Integration & Deployment.</p>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <?php if ($preselected_event > 0): ?>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        new bootstrap.Modal(document.getElementById('newRegModal')).show();
    });
    </script>
    <?php endif; ?>
</body>
</html>

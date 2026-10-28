<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth_helper.php';

require_login($conn);

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];
$role = $_SESSION['role'];
$full_name = $_SESSION['full_name'] ?? $username;

$stats = [
    'students_active' => 0,
    'students_total' => 0,
    'events_open' => 0,
    'events_total' => 0,
    'registrations_confirmed' => 0,
    'registrations_total' => 0,
    'users_active' => 0,
    'users_total' => 0
];

$monthly_labels = ['May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct'];
$monthly_counts = [0, 0, 0, 0, 0, 0];
$audit_logs = [];
$users_list = [];
$student_info = null;
$my_registrations = [];

if ($conn) {
    if ($role === 'Admin') {
        $q = $conn->query("SELECT SUM(CASE WHEN status = 'Active' THEN 1 ELSE 0 END) AS active_c, COUNT(*) AS total_c FROM students");
        if ($q && ($row = $q->fetch_assoc())) {
            $stats['students_active'] = (int)$row['active_c'];
            $stats['students_total'] = (int)$row['total_c'];
        }

        $q = $conn->query("SELECT SUM(CASE WHEN status = 'Open' THEN 1 ELSE 0 END) AS open_c, COUNT(*) AS total_c FROM events");
        if ($q && ($row = $q->fetch_assoc())) {
            $stats['events_open'] = (int)$row['open_c'];
            $stats['events_total'] = (int)$row['total_c'];
        }

        $q = $conn->query("SELECT SUM(CASE WHEN status = 'Confirmed' THEN 1 ELSE 0 END) AS conf_c, COUNT(*) AS total_c FROM registrations");
        if ($q && ($row = $q->fetch_assoc())) {
            $stats['registrations_confirmed'] = (int)$row['conf_c'];
            $stats['registrations_total'] = (int)$row['total_c'];
        }

        $q = $conn->query("SELECT SUM(CASE WHEN status = 'Active' THEN 1 ELSE 0 END) AS act_c, COUNT(*) AS total_c FROM users");
        if ($q && ($row = $q->fetch_assoc())) {
            $stats['users_active'] = (int)$row['act_c'];
            $stats['users_total'] = (int)$row['total_c'];
        }

        $chart_q = $conn->query("SELECT DATE_FORMAT(registration_date, '%b') AS mname, COUNT(*) AS cnt FROM registrations WHERE registration_date >= DATE_SUB(NOW(), INTERVAL 6 MONTH) GROUP BY DATE_FORMAT(registration_date, '%Y-%m'), DATE_FORMAT(registration_date, '%b') ORDER BY DATE_FORMAT(registration_date, '%Y-%m') ASC");
        if ($chart_q && $chart_q->num_rows > 0) {
            $monthly_labels = [];
            $monthly_counts = [];
            while ($cr = $chart_q->fetch_assoc()) {
                $monthly_labels[] = $cr['mname'];
                $monthly_counts[] = (int)$cr['cnt'];
            }
        }

        $users_q = $conn->query("SELECT id, username, email, full_name, role, status, created_at FROM users ORDER BY id DESC LIMIT 8");
        if ($users_q) {
            while ($ur = $users_q->fetch_assoc()) {
                $users_list[] = $ur;
            }
        }

        $audit_q = $conn->query("SELECT id, username, action, status, ip_address, created_at FROM audit_logs ORDER BY id DESC LIMIT 10");
        if ($audit_q) {
            while ($ar = $audit_q->fetch_assoc()) {
                $audit_logs[] = $ar;
            }
        }
    } else {
        $st_q = $conn->prepare("SELECT s.id, s.student_id_no, s.name, s.email, s.department, s.semester, s.cgpa, s.status FROM students s WHERE s.email = ? LIMIT 1");
        if ($st_q) {
            $st_q->bind_param("s", $_SESSION['email']);
            $st_q->execute();
            $student_info = $st_q->get_result()->fetch_assoc();
            $st_q->close();
        }

        $student_db_id = $student_info['id'] ?? 0;
        if ($student_db_id > 0) {
            $my_reg_q = $conn->prepare("SELECT r.id, r.remarks, r.registration_date, r.status, e.name AS event_title, e.category, e.event_date, e.event_time, e.venue FROM registrations r JOIN events e ON r.event_id = e.id WHERE r.student_id = ? ORDER BY r.id DESC");
            if ($my_reg_q) {
                $my_reg_q->bind_param("i", $student_db_id);
                $my_reg_q->execute();
                $res = $my_reg_q->get_result();
                while ($mr = $res->fetch_assoc()) {
                    $my_registrations[] = $mr;
                }
                $my_reg_q->close();
            }
        }
    }
}

$csrf_token = get_csrf_token();
$nav_items = get_dynamic_navigation('dashboard.php');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - StudentHub</title>
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
                        <i class="bi bi-person-circle"></i> <?= htmlspecialchars($full_name) ?> (<?= htmlspecialchars($role) ?>)
                    </span>
                    <a href="logout.php" class="btn btn-sm btn-outline-light">Logout</a>
                </div>
            </div>
        </div>
    </header>

    <nav class="app-nav" aria-label="Main Navigation">
        <div class="container d-flex flex-wrap gap-2 py-1">
            <?php foreach ($nav_items as $item): ?>
                <a href="<?= htmlspecialchars($item['url']) ?>" class="nav-item <?= $item['url'] === 'dashboard.php' ? 'active' : '' ?>">
                    <i class="bi bi-<?= htmlspecialchars($item['icon']) ?>"></i> <?= htmlspecialchars($item['label']) ?>
                </a>
            <?php endforeach; ?>
        </div>
    </nav>

    <main id="main-content" class="container py-4 flex-grow-1">
        <?php if (isset($_GET['msg']) && $_GET['msg'] === 'user_toggled'): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="bi bi-check-circle-fill me-2"></i> User account status updated successfully.
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (isset($_GET['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="bi bi-exclamation-triangle-fill me-2"></i> <?= htmlspecialchars($_GET['error']) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
            <div>
                <h2 class="page-title mb-1"><?= htmlspecialchars($role) ?> Control Center</h2>
                <p class="text-muted mb-0">Welcome back, <strong><?= htmlspecialchars($full_name) ?></strong>. Signed in as <em><?= htmlspecialchars($role) ?></em>.</p>
            </div>
            <div class="d-flex gap-2">
                <?php if ($role === 'Admin'): ?>
                    <a href="students.php" class="btn btn-primary"><i class="bi bi-people-fill me-1"></i> Manage Students</a>
                    <a href="events.php" class="btn btn-success"><i class="bi bi-calendar-plus me-1"></i> Manage Events</a>
                <?php else: ?>
                    <a href="events.php" class="btn btn-primary"><i class="bi bi-search me-1"></i> Browse & Register Events</a>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($role === 'Admin'): ?>
            <div class="row g-4 mb-4">
                <div class="col-sm-6 col-xl-3">
                    <div class="card stat-card shadow-sm p-3">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <span class="stat-label">Active Students</span>
                                <div class="stat-number text-primary"><?= $stats['students_active'] ?></div>
                                <div class="small text-muted">Total: <?= $stats['students_total'] ?></div>
                            </div>
                            <span class="fs-2 text-primary"><i class="bi bi-mortarboard-fill"></i></span>
                        </div>
                    </div>
                </div>
                <div class="col-sm-6 col-xl-3">
                    <div class="card stat-card shadow-sm p-3">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <span class="stat-label">Active Events</span>
                                <div class="stat-number text-success"><?= $stats['events_open'] ?></div>
                                <div class="small text-muted">Total: <?= $stats['events_total'] ?></div>
                            </div>
                            <span class="fs-2 text-success"><i class="bi bi-calendar2-check-fill"></i></span>
                        </div>
                    </div>
                </div>
                <div class="col-sm-6 col-xl-3">
                    <div class="card stat-card shadow-sm p-3">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <span class="stat-label">Total Bookings</span>
                                <div class="stat-number text-warning"><?= $stats['registrations_confirmed'] ?></div>
                                <div class="small text-muted">All Registrations: <?= $stats['registrations_total'] ?></div>
                            </div>
                            <span class="fs-2 text-warning"><i class="bi bi-ticket-perforated-fill"></i></span>
                        </div>
                    </div>
                </div>
                <div class="col-sm-6 col-xl-3">
                    <div class="card stat-card shadow-sm p-3">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <span class="stat-label">Active Accounts</span>
                                <div class="stat-number text-info"><?= $stats['users_active'] ?></div>
                                <div class="small text-muted">Total Users: <?= $stats['users_total'] ?></div>
                            </div>
                            <span class="fs-2 text-info"><i class="bi bi-shield-lock-fill"></i></span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row g-4 mb-4">
                <div class="col-lg-7">
                    <div class="card shadow-sm h-100">
                        <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                            <h5 class="mb-0 fw-bold text-primary"><i class="bi bi-bar-chart-fill me-2"></i>Event Registrations (Monthly Analytics)</h5>
                            <span class="badge bg-light text-muted">Practical 14 Chart</span>
                        </div>
                        <div class="card-body">
                            <canvas id="monthlyRegistrationsChart" height="140"></canvas>
                        </div>
                    </div>
                </div>
                <div class="col-lg-5">
                    <div class="card shadow-sm h-100">
                        <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                            <h5 class="mb-0 fw-bold text-dark"><i class="bi bi-people-fill me-2"></i>User Access Control</h5>
                            <span class="badge bg-secondary">Toggle Status</span>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>User</th>
                                            <th>Role</th>
                                            <th>Status</th>
                                            <th class="text-end">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($users_list as $u): ?>
                                            <tr>
                                                <td>
                                                    <strong><?= htmlspecialchars($u['username']) ?></strong>
                                                    <div class="small text-muted"><?= htmlspecialchars($u['email']) ?></div>
                                                </td>
                                                <td>
                                                    <span class="badge bg-<?= $u['role'] === 'Admin' ? 'danger' : ($u['role'] === 'Faculty' ? 'info text-dark' : 'success') ?>">
                                                        <?= htmlspecialchars($u['role']) ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <span class="badge bg-<?= $u['status'] === 'Active' ? 'success' : 'secondary' ?>">
                                                        <?= htmlspecialchars($u['status']) ?>
                                                    </span>
                                                </td>
                                                <td class="text-end">
                                                    <?php if ($u['id'] != $user_id): ?>
                                                        <form method="POST" action="toggle_user.php" class="d-inline">
                                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                                                            <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
                                                            <button type="submit" class="btn btn-sm btn-outline-<?= $u['status'] === 'Active' ? 'warning' : 'success' ?>" title="Toggle Active / Inactive">
                                                                <i class="bi bi-toggle-<?= $u['status'] === 'Active' ? 'on' : 'off' ?>"></i>
                                                            </button>
                                                        </form>
                                                    <?php else: ?>
                                                        <span class="small text-muted">Current</span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card shadow-sm">
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                    <h5 class="mb-0 fw-bold text-dark"><i class="bi bi-shield-shaded me-2"></i>Security Audit Log Trail (Latest 10 Events)</h5>
                    <span class="badge bg-dark">Immutable Log</span>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-striped align-middle mb-0 font-monospace small">
                            <thead class="table-light">
                                <tr>
                                    <th>#</th>
                                    <th>Timestamp</th>
                                    <th>Username</th>
                                    <th>Action</th>
                                    <th>Status</th>
                                    <th>IP Address</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($audit_logs)): ?>
                                    <tr><td colspan="6" class="text-center text-muted py-3">No audit logs recorded yet.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($audit_logs as $log): ?>
                                        <tr>
                                            <td><?= (int)$log['id'] ?></td>
                                            <td><?= htmlspecialchars($log['created_at']) ?></td>
                                            <td><strong><?= htmlspecialchars($log['username'] ?? 'System') ?></strong></td>
                                            <td><span class="badge bg-secondary"><?= htmlspecialchars($log['action']) ?></span></td>
                                            <td>
                                                <span class="badge bg-<?= strpos($log['status'], 'SUCCESS') !== false ? 'success' : 'danger' ?>">
                                                    <?= htmlspecialchars($log['status']) ?>
                                                </span>
                                            </td>
                                            <td><?= htmlspecialchars($log['ip_address']) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        <?php else: ?>
            <div class="row g-4">
                <div class="col-lg-4">
                    <div class="card shadow-sm h-100">
                        <div class="card-header bg-primary text-white">
                            <h5 class="mb-0"><i class="bi bi-person-badge me-2"></i>Student Profile</h5>
                        </div>
                        <div class="card-body text-center">
                            <div class="display-1 text-primary mb-3">
                                <i class="bi bi-person-circle"></i>
                            </div>
                            <h4 class="fw-bold mb-1"><?= htmlspecialchars($full_name) ?></h4>
                            <p class="text-muted mb-3"><?= htmlspecialchars($_SESSION['email']) ?></p>
                            <ul class="list-group list-group-flush text-start">
                                <li class="list-group-item d-flex justify-content-between">
                                    <span class="text-muted">Student ID:</span>
                                    <strong><?= htmlspecialchars($student_info['student_id_no'] ?? '25CE082') ?></strong>
                                </li>
                                <li class="list-group-item d-flex justify-content-between">
                                    <span class="text-muted">Department:</span>
                                    <strong><?= htmlspecialchars($student_info['department'] ?? 'CE') ?></strong>
                                </li>
                                <li class="list-group-item d-flex justify-content-between">
                                    <span class="text-muted">Semester:</span>
                                    <strong>Sem <?= htmlspecialchars($student_info['semester'] ?? '4') ?></strong>
                                </li>
                                <li class="list-group-item d-flex justify-content-between">
                                    <span class="text-muted">CGPA:</span>
                                    <strong><?= htmlspecialchars($student_info['cgpa'] ?? '8.50') ?></strong>
                                </li>
                                <li class="list-group-item d-flex justify-content-between">
                                    <span class="text-muted">Academic Status:</span>
                                    <span class="badge bg-success"><?= htmlspecialchars($student_info['status'] ?? 'Active') ?></span>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>

                <div class="col-lg-8">
                    <div class="card shadow-sm h-100">
                        <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                            <h5 class="mb-0 fw-bold text-primary"><i class="bi bi-journal-bookmark-fill me-2"></i>My Registered Campus Events</h5>
                            <a href="events.php" class="btn btn-sm btn-outline-primary"><i class="bi bi-plus-circle"></i> Register for More</a>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Registration Ref</th>
                                            <th>Event Title</th>
                                            <th>Category</th>
                                            <th>Schedule</th>
                                            <th>Venue</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($my_registrations)): ?>
                                            <tr>
                                                <td colspan="6" class="text-center py-5 text-muted">
                                                    <i class="bi bi-calendar2-x display-6 mb-2 d-block"></i>
                                                    You haven't registered for any events yet.
                                                    <div class="mt-2">
                                                        <a href="events.php" class="btn btn-sm btn-primary">Browse Open Events</a>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php else: ?>
                                            <?php foreach ($my_registrations as $reg): ?>
                                                <tr>
                                                    <td class="font-monospace fw-bold text-primary"><?= htmlspecialchars($reg['remarks'] ?? 'REG-' . $reg['id']) ?></td>
                                                    <td><strong><?= htmlspecialchars($reg['event_title']) ?></strong></td>
                                                    <td><span class="badge bg-info text-dark"><?= htmlspecialchars($reg['category']) ?></span></td>
                                                    <td class="small"><?= htmlspecialchars($reg['event_date']) ?> <?= htmlspecialchars(substr($reg['event_time'], 0, 5)) ?></td>
                                                    <td class="small"><?= htmlspecialchars($reg['venue']) ?></td>
                                                    <td>
                                                        <span class="badge bg-<?= $reg['status'] === 'Confirmed' ? 'success' : 'danger' ?>">
                                                            <?= htmlspecialchars($reg['status']) ?>
                                                        </span>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </main>

    <footer class="app-footer text-center py-3">
        <div class="container">
            <p class="mb-1 text-light fw-bold">MIRAL PATEL || 25CE082 || STUDENT HUB PORTAL</p>
            <p class="mb-0 text-white-50 small">&copy; <?= date('Y') ?> CSPIT / CHARUSAT. Practical 15 Final Integration & Deployment.</p>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <?php if ($role === 'Admin'): ?>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.2/dist/chart.umd.min.js"></script>
    <script>
    const ctx = document.getElementById('monthlyRegistrationsChart').getContext('2d');
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: <?= json_encode($monthly_labels) ?>,
            datasets: [{
                label: 'Event Registrations',
                data: <?= json_encode($monthly_counts) ?>,
                backgroundColor: 'rgba(30, 58, 138, 0.75)',
                borderColor: '#1E3A8A',
                borderWidth: 1,
                borderRadius: 4
            }]
        },
        options: {
            responsive: true,
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: { precision: 0 }
                }
            }
        }
    });
    </script>
    <?php endif; ?>
</body>
</html>

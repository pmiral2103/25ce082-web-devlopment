<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth_helper.php';

check_remember_token($conn);

$student_count = 0;
$event_count = 0;
$registration_count = 0;
$active_user_count = 0;
$featured_events = [];

if ($conn) {
    $res = $conn->query("SELECT COUNT(*) AS c FROM students WHERE status = 'Active'");
    if ($res && ($row = $res->fetch_assoc())) $student_count = (int)$row['c'];

    $res = $conn->query("SELECT COUNT(*) AS c FROM events WHERE status = 'Open'");
    if ($res && ($row = $res->fetch_assoc())) $event_count = (int)$row['c'];

    $res = $conn->query("SELECT COUNT(*) AS c FROM registrations WHERE status = 'Confirmed'");
    if ($res && ($row = $res->fetch_assoc())) $registration_count = (int)$row['c'];

    $res = $conn->query("SELECT COUNT(*) AS c FROM users WHERE status = 'Active'");
    if ($res && ($row = $res->fetch_assoc())) $active_user_count = (int)$row['c'];

    $res = $conn->query("SELECT id, name, category, event_date, event_time, venue, poster, status FROM events WHERE status = 'Open' ORDER BY event_date ASC LIMIT 6");
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $featured_events[] = $row;
        }
    }
}

$nav_items = get_dynamic_navigation('index.php');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>StudentHub Portal - CSPIT / CHARUSAT (25CE082)</title>
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
                    <?php if (isset($_SESSION['user_id'])): ?>
                        <span class="badge bg-light text-dark px-3 py-2 me-2">
                            <i class="bi bi-person-circle"></i> <?= htmlspecialchars($_SESSION['full_name'] ?? $_SESSION['username']) ?> (<?= htmlspecialchars($_SESSION['role']) ?>)
                        </span>
                        <a href="dashboard.php" class="btn btn-sm btn-light text-primary fw-bold me-1">Dashboard</a>
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
                <a href="<?= htmlspecialchars($item['url']) ?>" class="nav-item <?= $item['url'] === 'index.php' ? 'active' : '' ?>">
                    <i class="bi bi-<?= htmlspecialchars($item['icon']) ?>"></i> <?= htmlspecialchars($item['label']) ?>
                </a>
            <?php endforeach; ?>
        </div>
    </nav>

    <main id="main-content">
        <section class="hero-section text-center text-white py-5 shadow-sm">
            <div class="container py-3">
                <span class="badge bg-light text-primary mb-3 px-3 py-2 rounded-pill fw-bold text-uppercase tracking-wider">
                    Practical 15 - Complete System Integration
                </span>
                <h1 class="display-5 fw-bold mb-3">Centralized Student & Campus Event Management</h1>
                <p class="lead mx-auto mb-4" style="max-width: 780px;">
                    A unified, production-ready web portal integrating secure user authentication, role-based dashboards, student & event CRUD with validated poster uploads, real-time AJAX search, and audit trail logging.
                </p>
                <div class="d-flex justify-content-center gap-3 flex-wrap">
                    <a href="events.php" class="btn btn-warning btn-lg px-4 fw-bold">
                        <i class="bi bi-calendar-event me-2"></i>Browse Events
                    </a>
                    <?php if (!isset($_SESSION['user_id'])): ?>
                        <a href="register.php" class="btn btn-light btn-lg px-4 text-primary fw-bold">
                            <i class="bi bi-person-plus-fill me-2"></i>Student Registration
                        </a>
                        <a href="login.php" class="btn btn-outline-light btn-lg px-4">
                            <i class="bi bi-box-arrow-in-right me-2"></i>Portal Login
                        </a>
                    <?php else: ?>
                        <a href="dashboard.php" class="btn btn-light btn-lg px-4 text-primary fw-bold">
                            <i class="bi bi-speedometer2 me-2"></i>Go to Dashboard
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </section>

        <section class="container my-5">
            <div class="row g-4 text-center">
                <div class="col-6 col-md-3">
                    <div class="card stat-card shadow-sm p-4 h-100">
                        <div class="stat-number text-primary"><?= number_format($student_count) ?></div>
                        <div class="stat-label"><i class="bi bi-people-fill me-1"></i> Enrolled Students</div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="card stat-card shadow-sm p-4 h-100">
                        <div class="stat-number text-success"><?= number_format($event_count) ?></div>
                        <div class="stat-label"><i class="bi bi-calendar2-check-fill me-1"></i> Open Events</div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="card stat-card shadow-sm p-4 h-100">
                        <div class="stat-number text-warning"><?= number_format($registration_count) ?></div>
                        <div class="stat-label"><i class="bi bi-journal-check me-1"></i> Confirmed Bookings</div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="card stat-card shadow-sm p-4 h-100">
                        <div class="stat-number text-info"><?= number_format($active_user_count) ?></div>
                        <div class="stat-label"><i class="bi bi-person-check-fill me-1"></i> Active Accounts</div>
                    </div>
                </div>
            </div>
        </section>

        <section class="container my-5">
            <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
                <div>
                    <h2 class="h3 fw-bold text-primary mb-1"><i class="bi bi-fire text-danger me-2"></i>Upcoming Campus Events</h2>
                    <p class="text-muted mb-0">Discover workshops, technical hackathons, cultural festivals, and seminars.</p>
                </div>
                <a href="events.php" class="btn btn-outline-primary">View All Events <i class="bi bi-arrow-right"></i></a>
            </div>

            <div class="row g-4">
                <?php if (empty($featured_events)): ?>
                    <div class="col-12 text-center py-5">
                        <div class="card p-5 shadow-sm">
                            <i class="bi bi-calendar-x display-4 text-muted mb-3"></i>
                            <h5>No upcoming open events currently available.</h5>
                            <p class="text-muted">Initialize the database with demo events via the Setup page.</p>
                            <div>
                                <a href="setup.php" class="btn btn-primary"><i class="bi bi-wrench"></i> Run Setup</a>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <?php foreach ($featured_events as $ev): ?>
                        <div class="col-md-6 col-lg-4">
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
                                        <span class="badge bg-success"><?= htmlspecialchars($ev['status']) ?></span>
                                    </div>
                                    <h5 class="card-title fw-bold text-dark"><?= htmlspecialchars($ev['name']) ?></h5>
                                    <div class="card-text text-muted small mt-auto pt-3">
                                        <p class="mb-1"><i class="bi bi-calendar3 me-2 text-primary"></i><strong>Date:</strong> <?= htmlspecialchars($ev['event_date']) ?> at <?= htmlspecialchars(substr($ev['event_time'], 0, 5)) ?></p>
                                        <p class="mb-3"><i class="bi bi-geo-alt-fill me-2 text-danger"></i><strong>Venue:</strong> <?= htmlspecialchars($ev['venue']) ?></p>
                                    </div>
                                    <div class="d-grid mt-2">
                                        <a href="registrations.php?event_id=<?= (int)$ev['id'] ?>" class="btn btn-outline-primary btn-sm">
                                            <i class="bi bi-pencil-square me-1"></i> Register Now
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </section>

        <section class="container my-5 py-4 border-top">
            <div class="text-center mb-4">
                <h3 class="fw-bold text-primary">Practical Modules Integrated in StudentHub</h3>
                <p class="text-muted">A consolidated overview of learning outcomes across Practicals 1 through 15</p>
            </div>
            <div class="row g-4">
                <div class="col-md-4">
                    <div class="card h-100 shadow-sm p-3">
                        <div class="d-flex align-items-center gap-3 mb-3">
                            <span class="fs-2 text-primary"><i class="bi bi-shield-lock-fill"></i></span>
                            <div>
                                <h6 class="fw-bold mb-0">Auth & Security (P-9, P-10)</h6>
                                <small class="text-muted">RBAC, Sessions, Bcrypt, Remember-Me</small>
                            </div>
                        </div>
                        <p class="text-muted small mb-0">Secure login/registration with <code>password_hash()</code>, role redirection, 30m idle session expiry, SHA-256 persistent tokens, and CSRF token defense.</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card h-100 shadow-sm p-3">
                        <div class="d-flex align-items-center gap-3 mb-3">
                            <span class="fs-2 text-success"><i class="bi bi-database-check"></i></span>
                            <div>
                                <h6 class="fw-bold mb-0">Data CRUD & Files (P-8, 11, 12)</h6>
                                <small class="text-muted">MySQLi, Prepared Stmts, Uploads</small>
                            </div>
                        </div>
                        <p class="text-muted small mb-0">Normalized relational database design, parameterized SQL, soft-deletion for students, CSV streaming, and poster uploads with MIME validation & size restrictions.</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card h-100 shadow-sm p-3">
                        <div class="d-flex align-items-center gap-3 mb-3">
                            <span class="fs-2 text-info"><i class="bi bi-speedometer2"></i></span>
                            <div>
                                <h6 class="fw-bold mb-0">Dashboards & AJAX (P-13, 14, 15)</h6>
                                <small class="text-muted">Fetch API, REST, Audit Trail</small>
                            </div>
                        </div>
                        <p class="text-muted small mb-0">Role-specific dashboards, dynamic role-based navigation menus, monthly analytics charts, account toggles, and live Fetch API search with debouncing.</p>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <footer class="app-footer text-center py-3">
        <div class="container">
            <p class="mb-1 text-light fw-bold">MIRAL PATEL || 25CE082 || STUDENT HUB PORTAL</p>
            <p class="mb-0 text-white-50 small">&copy; <?= date('Y') ?> CSPIT / CHARUSAT. Practical 15 Final Integration & Deployment.</p>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

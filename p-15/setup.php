<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth_helper.php';

$message = '';
$message_type = 'info';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $message = "Invalid CSRF security token.";
        $message_type = "danger";
    } elseif ($_POST['action'] === 'init_schema' || $_POST['action'] === 'reset_all') {
        $raw_conn = @new mysqli($host, $username, $password);
        if ($raw_conn->connect_error) {
            $message = "MySQL Connection Failed: " . $raw_conn->connect_error;
            $message_type = "danger";
        } else {
            $raw_conn->query("CREATE DATABASE IF NOT EXISTS `studenthub_db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            $raw_conn->select_db("studenthub_db");

            if ($_POST['action'] === 'reset_all') {
                $raw_conn->query("SET FOREIGN_KEY_CHECKS = 0");
                $raw_conn->query("DROP TABLE IF EXISTS `audit_logs`");
                $raw_conn->query("DROP TABLE IF EXISTS `remember_tokens`");
                $raw_conn->query("DROP TABLE IF EXISTS `registrations`");
                $raw_conn->query("DROP TABLE IF EXISTS `events`");
                $raw_conn->query("DROP TABLE IF EXISTS `students`");
                $raw_conn->query("DROP TABLE IF EXISTS `users`");
                $raw_conn->query("SET FOREIGN_KEY_CHECKS = 1");
            }

            $sql_file = __DIR__ . '/studenthub_complete.sql';
            if (file_exists($sql_file)) {
                $sql = file_get_contents($sql_file);
                if ($raw_conn->multi_query($sql)) {
                    do {
                        if ($result = $raw_conn->store_result()) {
                            $result->free();
                        }
                    } while ($raw_conn->more_results() && $raw_conn->next_result());
                    $message = "Database schema and demo seed data initialized successfully!";
                    $message_type = "success";
                } else {
                    $message = "Schema Execution Error: " . $raw_conn->error;
                    $message_type = "danger";
                }
            } else {
                $message = "SQL Dump file studenthub_complete.sql not found.";
                $message_type = "danger";
            }
            $raw_conn->close();
        }
        $conn = @new mysqli($host, $username, $password, $database);
    }
}

$tables_status = [];
$check_tables = ['users', 'students', 'events', 'registrations', 'remember_tokens', 'audit_logs'];
if ($conn) {
    foreach ($check_tables as $tb) {
        $res = $conn->query("SHOW TABLES LIKE '$tb'");
        if ($res && $res->num_rows > 0) {
            $count_res = $conn->query("SELECT COUNT(*) AS c FROM `$tb`");
            $c = $count_res ? $count_res->fetch_assoc()['c'] : 0;
            $tables_status[$tb] = ['exists' => true, 'count' => $c];
        } else {
            $tables_status[$tb] = ['exists' => false, 'count' => 0];
        }
    }
}

$csrf_token = get_csrf_token();
$nav_items = get_dynamic_navigation('setup.php');
$uploads_writable = is_writable(__DIR__ . '/uploads');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Setup & Health Check - StudentHub</title>
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
                <a href="<?= htmlspecialchars($item['url']) ?>" class="nav-item <?= $item['url'] === 'setup.php' ? 'active' : '' ?>">
                    <i class="bi bi-<?= htmlspecialchars($item['icon']) ?>"></i> <?= htmlspecialchars($item['label']) ?>
                </a>
            <?php endforeach; ?>
        </div>
    </nav>

    <main id="main-content" class="container py-4 flex-grow-1">
        <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
            <div>
                <h2 class="page-title mb-1">System Setup & Environment Health Check</h2>
                <p class="text-muted mb-0">Verify system dependencies, database connectivity, and seed demo records.</p>
            </div>
            <a href="index.php" class="btn btn-outline-primary"><i class="bi bi-arrow-left"></i> Back to Portal</a>
        </div>

        <?php if (!empty($message)): ?>
            <div class="alert alert-<?= $message_type ?> alert-dismissible fade show shadow-sm" role="alert">
                <i class="bi bi-info-circle-fill me-2"></i><?= htmlspecialchars($message) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <div class="row g-4">
            <div class="col-lg-6">
                <div class="card shadow-sm h-100">
                    <div class="card-header bg-primary text-white d-flex align-items-center gap-2">
                        <i class="bi bi-heart-pulse-fill"></i>
                        <h5 class="mb-0">Environment Diagnostics</h5>
                    </div>
                    <div class="card-body">
                        <ul class="list-group list-group-flush">
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <div>
                                    <strong>PHP Version</strong>
                                    <div class="small text-muted">Required: >= 7.4</div>
                                </div>
                                <span class="badge bg-success px-3 py-2">PHP <?= phpversion() ?></span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <div>
                                    <strong>MySQL Connectivity</strong>
                                    <div class="small text-muted">Database: studenthub_db</div>
                                </div>
                                <?php if ($conn): ?>
                                    <span class="badge bg-success px-3 py-2"><i class="bi bi-check-circle"></i> Connected</span>
                                <?php else: ?>
                                    <span class="badge bg-danger px-3 py-2"><i class="bi bi-x-circle"></i> Disconnected</span>
                                <?php endif; ?>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <div>
                                    <strong>MySQL Server Info</strong>
                                    <div class="small text-muted">Engine Version</div>
                                </div>
                                <span class="badge bg-info text-dark px-3 py-2"><?= $conn ? htmlspecialchars($conn->server_info) : 'N/A' ?></span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <div>
                                    <strong>Uploads Directory</strong>
                                    <div class="small text-muted"><?= htmlspecialchars(__DIR__ . '/uploads') ?></div>
                                </div>
                                <?php if ($uploads_writable): ?>
                                    <span class="badge bg-success px-3 py-2"><i class="bi bi-shield-check"></i> Writable</span>
                                <?php else: ?>
                                    <span class="badge bg-warning text-dark px-3 py-2"><i class="bi bi-exclamation-triangle"></i> Not Writable</span>
                                <?php endif; ?>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <div>
                                    <strong>Session Management</strong>
                                    <div class="small text-muted">Status & Lifetime</div>
                                </div>
                                <span class="badge bg-success px-3 py-2">Active (30m Timeout)</span>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>

            <div class="col-lg-6">
                <div class="card shadow-sm h-100">
                    <div class="card-header bg-dark text-white d-flex align-items-center gap-2">
                        <i class="bi bi-database-fill-gear"></i>
                        <h5 class="mb-0">Database Schema & Record Counts</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Table Name</th>
                                        <th>Status</th>
                                        <th>Records</th>
                                        <th>Purpose</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $purposes = [
                                        'users' => 'Accounts & Roles',
                                        'students' => 'Student Profiles',
                                        'events' => 'Campus Events',
                                        'registrations' => 'Enrollments',
                                        'remember_tokens' => 'Remember-Me Auth',
                                        'audit_logs' => 'Security Audit Trail'
                                    ];
                                    foreach ($check_tables as $tb):
                                        $st = $tables_status[$tb] ?? ['exists' => false, 'count' => 0];
                                    ?>
                                        <tr>
                                            <td class="fw-bold font-monospace"><?= htmlspecialchars($tb) ?></td>
                                            <td>
                                                <?php if ($st['exists']): ?>
                                                    <span class="badge bg-success"><i class="bi bi-check2"></i> Present</span>
                                                <?php else: ?>
                                                    <span class="badge bg-danger"><i class="bi bi-x"></i> Missing</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="badge bg-primary rounded-pill"><?= (int)$st['count'] ?></span>
                                            </td>
                                            <td class="small text-muted"><?= $purposes[$tb] ?? '' ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-12">
                <div class="card shadow-sm border-primary">
                    <div class="card-header bg-light d-flex align-items-center justify-content-between">
                        <h5 class="mb-0 text-primary"><i class="bi bi-magic me-2"></i>One-Click Schema & Demo Seed Actions</h5>
                        <span class="badge bg-primary">XAMPP Port 8080</span>
                    </div>
                    <div class="card-body">
                        <p class="card-text text-muted">
                            Initialize the required tables or reset all data back to the clean demonstration state. All demo accounts, 20+ student profiles, sample events, and audit logs will be restored.
                        </p>
                        <div class="d-flex flex-wrap gap-3">
                            <form method="POST" action="setup.php" class="d-inline">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                                <input type="hidden" name="action" value="init_schema">
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-play-circle-fill me-1"></i> Initialize Missing Tables & Seed
                                </button>
                            </form>

                            <form method="POST" action="setup.php" class="d-inline" onsubmit="return confirm('Reset all tables and reload clean demo records? Current data will be replaced.');">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                                <input type="hidden" name="action" value="reset_all">
                                <button type="submit" class="btn btn-outline-danger">
                                    <i class="bi bi-arrow-counterclockwise me-1"></i> Reset All Data & Re-seed
                                </button>
                            </form>

                            <a href="index.php" class="btn btn-success">
                                <i class="bi bi-globe me-1"></i> Launch StudentHub Portal
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-12">
                <div class="card shadow-sm">
                    <div class="card-header bg-dark text-white">
                        <h5 class="mb-0"><i class="bi bi-key-fill me-2"></i>Default Demo Test Credentials</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-bordered align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Role</th>
                                        <th>Username</th>
                                        <th>Email</th>
                                        <th>Password</th>
                                        <th>Permissions</th>
                                        <th>Quick Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td><span class="badge bg-danger">Admin</span></td>
                                        <td class="font-monospace fw-bold">admin_hub</td>
                                        <td>admin@charusat.edu.in</td>
                                        <td class="font-monospace">Admin@123</td>
                                        <td>Full CRUD on Students, Events, Registrations, User Toggle, Audit Logs</td>
                                        <td><a href="login.php" class="btn btn-sm btn-outline-danger">Login as Admin</a></td>
                                    </tr>
                                    <tr>
                                        <td><span class="badge bg-success">Student</span></td>
                                        <td class="font-monospace fw-bold">miral082</td>
                                        <td>25ce082@charusat.edu.in</td>
                                        <td class="font-monospace">Password@123</td>
                                        <td>Browse Events, Register for Events, View Registered Events</td>
                                        <td><a href="login.php" class="btn btn-sm btn-outline-success">Login as Student</a></td>
                                    </tr>
                                    <tr>
                                        <td><span class="badge bg-info text-dark">Faculty</span></td>
                                        <td class="font-monospace fw-bold">prof_sharma</td>
                                        <td>sharma@charusat.edu.in</td>
                                        <td class="font-monospace">Faculty@123</td>
                                        <td>View Student List, View Events & Registrations</td>
                                        <td><a href="login.php" class="btn btn-sm btn-outline-info">Login as Faculty</a></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
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

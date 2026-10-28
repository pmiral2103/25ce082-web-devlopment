<?php
require_once "auth_helper.php";

require_admin($conn);

$role = $_SESSION['role'];
$menu_items = get_role_menu($role);

$message = "";
$error = "";

if (isset($_GET['msg'])) {
    if ($_GET['msg'] === 'status_toggled') $message = "User account status toggled successfully!";
}

if (isset($_GET['err'])) {
    if ($_GET['err'] === 'self_deactivation_blocked') $error = "Security Restriction: You cannot deactivate your own logged-in Administrator account.";
    elseif ($_GET['err'] === 'invalid_id') $error = "Invalid user ID provided.";
    elseif ($_GET['err'] === 'update_failed') $error = "Failed to update user status.";
}

$stat_users = 0;
$stat_active_users = 0;
$stat_students = 0;
$stat_events = 0;
$stat_open_events = 0;
$stat_regs = 0;

$res_u = $conn->query("SELECT COUNT(*) AS total, SUM(CASE WHEN status = 'Active' THEN 1 ELSE 0 END) AS active_total FROM users");
if ($res_u && $row_u = $res_u->fetch_assoc()) {
    $stat_users = intval($row_u['total']);
    $stat_active_users = intval($row_u['active_total']);
}

$res_s = $conn->query("SELECT COUNT(*) AS total FROM students");
if ($res_s) $stat_students = intval($res_s->fetch_assoc()['total']);

$res_e = $conn->query("SELECT COUNT(*) AS total, SUM(CASE WHEN status = 'Open' THEN 1 ELSE 0 END) AS open_total FROM events");
if ($res_e && $row_e = $res_e->fetch_assoc()) {
    $stat_events = intval($row_e['total']);
    $stat_open_events = intval($row_e['open_total']);
}

$res_r = $conn->query("SELECT COUNT(*) AS total FROM registrations");
if ($res_r) $stat_regs = intval($res_r->fetch_assoc()['total']);

$monthly_data = [];
$max_month_count = 1;
$res_m = $conn->query("SELECT DATE_FORMAT(registration_date, '%b %Y') AS month_label, COUNT(*) AS total FROM registrations GROUP BY DATE_FORMAT(registration_date, '%Y-%m') ORDER BY MIN(registration_date) ASC");
if ($res_m) {
    while ($m_row = $res_m->fetch_assoc()) {
        $c = intval($m_row['total']);
        if ($c > $max_month_count) $max_month_count = $c;
        $monthly_data[] = [
            'label' => $m_row['month_label'],
            'count' => $c
        ];
    }
}

$all_users = [];
$res_users = $conn->query("SELECT id, username, email, full_name, role, status, last_login, created_at FROM users ORDER BY id ASC");
if ($res_users) {
    while ($ur = $res_users->fetch_assoc()) {
        $all_users[] = $ur;
    }
}

$latest_regs = [];
$res_latest = $conn->query("SELECT r.id, s.name AS student_name, s.student_id_no, e.name AS event_name, e.category, r.registration_date, r.status FROM registrations r JOIN students s ON r.student_id = s.id JOIN events e ON r.event_id = e.id ORDER BY r.registration_date DESC LIMIT 6");
if ($res_latest) {
    while ($lr = $res_latest->fetch_assoc()) {
        $latest_regs[] = $lr;
    }
}

$audit_logs = [];
$res_logs = $conn->query("SELECT id, username, action, status, ip_address, created_at FROM audit_logs ORDER BY id DESC LIMIT 10");
if ($res_logs) {
    while ($al = $res_logs->fetch_assoc()) {
        $audit_logs[] = $al;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>StudentHub - Admin Dashboard (Practical 14)</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <header id="header">
        <img src="image/charusatlogo.png" alt="CHARUSAT Logo">
        <div>StudentHub - Admin Monitoring &amp; RBAC Dashboard</div>
        <nav>
            <?php foreach ($menu_items as $item): ?>
                <a href="<?php echo htmlspecialchars($item['url']); ?>">
                    <?php echo htmlspecialchars($item['label']); ?>
                </a>
            <?php endforeach; ?>
            <span class="user-badge-header">
                <?php echo htmlspecialchars($_SESSION['full_name']); ?> (ADMIN)
            </span>
            <a href="logout.php" class="btn-danger" style="margin-left: 10px; padding: 4px 10px; font-size: 13px;">Logout</a>
        </nav>
    </header>

    <main class="main-content">
        <section class="container">
            <div class="demo-role-bar">
                <span><strong>Role-Based Access Control (RBAC) Active:</strong> You are authenticated as an Administrator with full permissions.</span>
                <div style="display: flex; gap: 8px; align-items: center;">
                    <span style="font-size: 12px; color: #cbd5e1;">Test Dynamic Menu:</span>
                    <a href="login.php?quick_login=Faculty">Preview Faculty Menu</a>
                    <a href="login.php?quick_login=Student">Preview Student Menu</a>
                </div>
            </div>

            <?php if (isset($_GET['login']) && $_GET['login'] === 'success'): ?>
                <div class="alert-success">
                    &check; Welcome back, Administrator! RBAC clearance confirmed.
                </div>
            <?php endif; ?>

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

            <h2>Administrator Control &amp; Monitoring Panel</h2>
            <div class="info-box">
                Key Features: Dynamic SQL Aggregations, RBAC Protection, Role-Specific Dynamic Menu Generation, Active/Inactive User Toggling (Intermediate), Monthly Event Registrations Analytics Chart (Advanced), and Security Audit Trail.
            </div>

            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-label">Total Users</div>
                    <div class="stat-number"><?php echo $stat_users; ?></div>
                    <span style="font-size: 11px; color: #10b981; font-weight: bold;"><?php echo $stat_active_users; ?> Active Accounts</span>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Enrolled Students</div>
                    <div class="stat-number"><?php echo $stat_students; ?></div>
                    <span style="font-size: 11px; color: #475569;">In Academic Database</span>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Total Events</div>
                    <div class="stat-number"><?php echo $stat_events; ?></div>
                    <span style="font-size: 11px; color: #10b981; font-weight: bold;"><?php echo $stat_open_events; ?> Open Events</span>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Event Registrations</div>
                    <div class="stat-number" style="color: #2563eb;"><?php echo $stat_regs; ?></div>
                    <span style="font-size: 11px; color: #475569;">Confirmed Student Entries</span>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Open Registration Rate</div>
                    <div class="stat-number" style="color: #10b981; font-size: 24px;">
                        <?php echo $stat_events > 0 ? round(($stat_open_events / $stat_events) * 100) : 0; ?>%
                    </div>
                    <span style="font-size: 11px; color: #475569;">Registration Availability</span>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Audit Log Events</div>
                    <div class="stat-number" style="color: #7c3aed; font-size: 24px;"><?php echo count($audit_logs); ?>+</div>
                    <span style="font-size: 11px; color: #475569;">Tracked Security Actions</span>
                </div>
            </div>

            <div id="analytics-section" class="analytics-card">
                <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
                    <div>
                        <h3 style="margin: 0; color: #1E3A8A;">Monthly Event Registrations Analytics (Advanced Extension)</h3>
                        <span style="font-size: 13px; color: #64748b;">Visual trend of student registrations aggregated by month directly from MySQL</span>
                    </div>
                    <div>
                        <span class="badge badge-admin" style="font-size: 12px; padding: 5px 12px;">Total: <?php echo $stat_regs; ?> Registrations</span>
                    </div>
                </div>

                <div class="chart-wrapper">
                    <?php if (empty($monthly_data)): ?>
                        <div style="width: 100%; text-align: center; color: #64748b; padding-top: 80px;">No registration analytics records available.</div>
                    <?php else: ?>
                        <?php foreach ($monthly_data as $m): ?>
                            <?php $height_pct = max(18, round(($m['count'] / $max_month_count) * 100)); ?>
                            <div class="chart-bar-group">
                                <div class="chart-bar" style="height: <?php echo $height_pct; ?>%;">
                                    <span class="chart-bar-val"><?php echo $m['count']; ?></span>
                                </div>
                                <span class="chart-label"><?php echo htmlspecialchars($m['label']); ?></span>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <div id="users-section">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 15px; margin-bottom: 10px; flex-wrap: wrap; gap: 10px;">
                    <h3 style="margin: 0; color: #1E3A8A;">User Management &amp; Status Toggling (Intermediate Extension)</h3>
                    <span style="font-size: 13px; color: #64748b;">Deactivating a user blocks them from logging into StudentHub</span>
                </div>

                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Full Name</th>
                                <th>Username</th>
                                <th>Email Address</th>
                                <th>Role</th>
                                <th>Account Status</th>
                                <th>Last Login</th>
                                <th>Toggle Status (Intermediate)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($all_users as $u): ?>
                                <tr>
                                    <td><?php echo $u['id']; ?></td>
                                    <td style="text-align: left; font-weight: bold;"><?php echo htmlspecialchars($u['full_name']); ?></td>
                                    <td><?php echo htmlspecialchars($u['username']); ?></td>
                                    <td style="text-align: left;"><?php echo htmlspecialchars($u['email']); ?></td>
                                    <td>
                                        <?php if ($u['role'] === 'Admin'): ?>
                                            <span class="badge badge-admin">Admin</span>
                                        <?php elseif ($u['role'] === 'Faculty'): ?>
                                            <span class="badge badge-faculty">Faculty</span>
                                        <?php else: ?>
                                            <span class="badge badge-student">Student</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($u['status'] === 'Active'): ?>
                                            <span class="badge badge-active">Active</span>
                                        <?php else: ?>
                                            <span class="badge badge-inactive">Inactive (Disabled)</span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="font-size: 12px; color: #475569;">
                                        <?php echo $u['last_login'] ? htmlspecialchars($u['last_login']) : 'Never'; ?>
                                    </td>
                                    <td>
                                        <?php if ($u['id'] == $_SESSION['user_id']): ?>
                                            <span style="font-size: 11px; color: #64748b; font-weight: bold;">(Current Session)</span>
                                        <?php elseif ($u['status'] === 'Active'): ?>
                                            <a href="toggle_user_status.php?id=<?php echo $u['id']; ?>" class="btn-toggle-inactive" onclick="return confirm('Deactivate user <?php echo htmlspecialchars(addslashes($u['username'])); ?>? They will be blocked from logging in.');">Deactivate</a>
                                        <?php else: ?>
                                            <a href="toggle_user_status.php?id=<?php echo $u['id']; ?>" class="btn-toggle-active" onclick="return confirm('Activate user <?php echo htmlspecialchars(addslashes($u['username'])); ?>?');">Activate</a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div id="registrations-section" style="margin-top: 35px;">
                <h3 style="color: #1E3A8A; margin-bottom: 10px;">Recent Event Registrations</h3>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>Reg ID</th>
                                <th>Student Name</th>
                                <th>Student ID No</th>
                                <th>Event Name</th>
                                <th>Category</th>
                                <th>Registration Date</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($latest_regs)): ?>
                                <tr>
                                    <td colspan="7" style="padding: 20px; color: #64748b;">No recent registrations found.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($latest_regs as $reg): ?>
                                    <tr>
                                        <td>#<?php echo $reg['id']; ?></td>
                                        <td style="text-align: left; font-weight: bold;"><?php echo htmlspecialchars($reg['student_name']); ?></td>
                                        <td><?php echo htmlspecialchars($reg['student_id_no']); ?></td>
                                        <td style="text-align: left;"><?php echo htmlspecialchars($reg['event_name']); ?></td>
                                        <td><span class="badge badge-student"><?php echo htmlspecialchars($reg['category']); ?></span></td>
                                        <td style="font-size: 12px;"><?php echo htmlspecialchars($reg['registration_date']); ?></td>
                                        <td><span class="badge badge-active"><?php echo htmlspecialchars($reg['status']); ?></span></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div id="audit-section" style="margin-top: 35px;">
                <h3 style="color: #1E3A8A; margin-bottom: 10px;">Administrative Security Audit Trail</h3>
                <div class="info-box" style="margin-bottom: 10px;">
                    Tracks administrative logins, user status alterations, role modifications, and blocked unauthorized attempts with client IP addresses.
                </div>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>Log ID</th>
                                <th>User</th>
                                <th>Action</th>
                                <th>Status / Details</th>
                                <th>Client IP</th>
                                <th>Timestamp</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($audit_logs)): ?>
                                <tr>
                                    <td colspan="6" style="padding: 20px; color: #64748b;">No audit records available.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($audit_logs as $log): ?>
                                    <tr>
                                        <td>#<?php echo $log['id']; ?></td>
                                        <td><strong><?php echo htmlspecialchars($log['username']); ?></strong></td>
                                        <td><code><?php echo htmlspecialchars($log['action']); ?></code></td>
                                        <td style="text-align: left; font-size: 12px;"><?php echo htmlspecialchars($log['status']); ?></td>
                                        <td><?php echo htmlspecialchars($log['ip_address']); ?></td>
                                        <td style="font-size: 12px;"><?php echo htmlspecialchars($log['created_at']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>
    </main>

    <footer>
        MIRAL PATEL||25CE082||STUDENT HUB PORTAL
    </footer>
</body>
</html>

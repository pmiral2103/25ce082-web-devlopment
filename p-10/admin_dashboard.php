<?php
require_once "auth_helper.php";

require_role($conn, 'Admin');

$users = [];
$audit_logs = [];
$total_users = 0;
$total_tokens = 0;
$total_logs = 0;

if ($conn !== null) {
    $res_users = $conn->query("SELECT id, username, email, full_name, mobile, role, last_login, created_at FROM users ORDER BY id ASC");
    if ($res_users) {
        while ($row = $res_users->fetch_assoc()) {
            $users[] = $row;
        }
    }
    $total_users = count($users);

    $res_tokens = $conn->query("SELECT COUNT(*) AS total FROM remember_tokens WHERE expires_at > NOW()");
    if ($res_tokens) {
        $total_tokens = $res_tokens->fetch_assoc()['total'];
    }

    $res_logs = $conn->query("SELECT id, user_id, username, action, status, ip_address, created_at FROM audit_logs ORDER BY id DESC LIMIT 50");
    if ($res_logs) {
        while ($row = $res_logs->fetch_assoc()) {
            $audit_logs[] = $row;
        }
    }
    $total_logs = count($audit_logs);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>StudentHub - Admin Management Portal</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <header id="header">
        <img src="image/charusatlogo.png" alt="CHARUSAT Logo">
        <div>StudentHub Portal - Admin Management Dashboard</div>
        <nav>
            <a href="student_dashboard.php">Student Portal</a>
            <a href="admin_dashboard.php" class="active">Admin Portal</a>
            <a href="setup_database.php">Setup DB</a>
            <span class="user-badge-header" style="background-color: #7c3aed;"><?php echo htmlspecialchars($_SESSION['full_name']); ?> (ADMIN)</span>
            <a href="logout.php" class="btn-danger" style="margin-left: 10px; padding: 4px 10px; font-size: 13px;">Logout</a>
        </nav>
    </header>

    <main class="main-content">
        <section class="container">
            <?php if (isset($_GET['login']) && $_GET['login'] === 'success'): ?>
                <div class="alert-success">
                    &check; Welcome Administrator! Role-Based Access Control (RBAC) verified your Admin privileges.
                </div>
            <?php endif; ?>

            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; flex-wrap: wrap; gap: 10px;">
                <div>
                    <h2 style="margin: 0;">Admin Control Center</h2>
                    <span style="font-size: 13px; color: #475569;">Authorized User: <strong><?php echo htmlspecialchars($_SESSION['username']); ?></strong> | Active Session: <code><?php echo htmlspecialchars(session_id()); ?></code></span>
                </div>
                <div style="display: flex; gap: 8px;">
                    <a href="test_timeout.php" class="btn-warning">Simulate Timeout</a>
                    <a href="logout.php" class="btn-danger">Logout Securely</a>
                </div>
            </div>

            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-label">Total Users</div>
                    <div class="stat-number"><?php echo $total_users; ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Active Remember Tokens</div>
                    <div class="stat-number"><?php echo $total_tokens; ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Security Audit Logs</div>
                    <div class="stat-number"><?php echo $total_logs; ?></div>
                </div>
            </div>

            <div class="info-box">
                <strong>RBAC &amp; Authentication Verification:</strong> This administrative dashboard is protected by <code>require_role($conn, 'Admin')</code>. Any non-admin student session attempting to access this page is automatically blocked, redirected to their student dashboard, and logged as an <code>UNAUTHORIZED_ACCESS_ATTEMPT</code> in the audit logs below.
            </div>

            <h3 style="color: #1E3A8A; margin-top: 25px;">Registered Accounts &amp; Last-Login Timestamps</h3>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Full Name</th>
                            <th>Username</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Last Login (Intermediate)</th>
                            <th>Account Created</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $u): ?>
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
                                    <strong style="color: #059669; font-size: 12px;">
                                        <?php echo $u['last_login'] ? htmlspecialchars($u['last_login']) : 'Never'; ?>
                                    </strong>
                                </td>
                                <td style="font-size: 12px;"><?php echo htmlspecialchars($u['created_at']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <h3 style="color: #1E3A8A; margin-top: 30px;">Security Audit Logs (Login, Logout, Timeout, RBAC Blocks)</h3>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Log ID</th>
                            <th>User ID</th>
                            <th>Target Username</th>
                            <th>Action</th>
                            <th>Status</th>
                            <th>IP Address</th>
                            <th>Timestamp</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($audit_logs)): ?>
                            <tr>
                                <td colspan="7" style="padding: 20px; color: #666;">No audit records found.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($audit_logs as $log): ?>
                                <tr>
                                    <td><?php echo $log['id']; ?></td>
                                    <td><?php echo $log['user_id'] ? $log['user_id'] : '-'; ?></td>
                                    <td><strong><?php echo htmlspecialchars($log['username']); ?></strong></td>
                                    <td><code><?php echo htmlspecialchars($log['action']); ?></code></td>
                                    <td>
                                        <?php if ($log['status'] === 'SUCCESS'): ?>
                                            <span class="badge badge-success">SUCCESS</span>
                                        <?php elseif ($log['status'] === 'EXPIRED'): ?>
                                            <span class="badge badge-warning">EXPIRED</span>
                                        <?php elseif ($log['status'] === 'BLOCKED'): ?>
                                            <span class="badge badge-fail">BLOCKED</span>
                                        <?php else: ?>
                                            <span class="badge badge-fail"><?php echo htmlspecialchars($log['status']); ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($log['ip_address']); ?></td>
                                    <td style="font-size: 12px;"><?php echo htmlspecialchars($log['created_at']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </main>

    <footer>
        MIRAL PATEL||25CE082||STUDENT HUB PORTAL
    </footer>
</body>
</html>

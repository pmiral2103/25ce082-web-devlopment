<?php
require_once "db.php";

$users = [];
$audit_logs = [];
$total_users = 0;
$total_audits = 0;
$total_admins = 0;

if ($conn !== null) {
    $res_users = $conn->query("SELECT id, username, email, password, full_name, mobile, role, created_at FROM users ORDER BY id DESC");
    if ($res_users) {
        while ($row = $res_users->fetch_assoc()) {
            $users[] = $row;
            if ($row['role'] === 'Admin') {
                $total_admins++;
            }
        }
    }
    $total_users = count($users);

    $res_logs = $conn->query("SELECT id, user_id, username, action, status, ip_address, user_agent, created_at FROM audit_logs ORDER BY id DESC LIMIT 50");
    if ($res_logs) {
        while ($row = $res_logs->fetch_assoc()) {
            $audit_logs[] = $row;
        }
    }
    $total_audits = count($audit_logs);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>StudentHub - Registered Users &amp; Audit Logs</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <header id="header">
        <img src="image/charusatlogo.png" alt="CHARUSAT Logo">
        <div>StudentHub Registration System</div>
        <nav>
            <a href="index.php">Register</a>
            <a href="users_list.php" class="active">View Users &amp; Logs</a>
            <a href="setup_database.php">Setup Database</a>
        </nav>
    </header>

    <main class="main-content">
        <section class="container">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; flex-wrap: wrap; gap: 10px;">
                <h2 style="margin: 0;">Registered Users &amp; Hashed Passwords</h2>
                <div>
                    <a href="index.php" class="btn-setup">+ Register New User</a>
                    <a href="setup_database.php" class="btn-secondary" style="font-size: 13px; padding: 8px 14px;">Database Migration</a>
                </div>
            </div>

            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-label">Total Users</div>
                    <div class="stat-number"><?php echo $total_users; ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Admin Accounts</div>
                    <div class="stat-number"><?php echo $total_admins; ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Audit Logs Recorded</div>
                    <div class="stat-number"><?php echo $total_audits; ?></div>
                </div>
            </div>

            <div class="info-box">
                <strong>Viva Note on Password Security:</strong> Notice the <code>Password Hash (Bcrypt)</code> column below. Plain passwords are never stored in the database. Each hash begins with <code>$2y$10$</code>, indicating the 60-character bcrypt hash generated via PHP <code>password_hash($password, PASSWORD_BCRYPT)</code> with an automatically generated cryptographic salt.
            </div>

            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Full Name</th>
                            <th>Username</th>
                            <th>Email</th>
                            <th>Mobile</th>
                            <th>Role</th>
                            <th>Password Hash (Bcrypt)</th>
                            <th>Registered At</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($users)): ?>
                            <tr>
                                <td colspan="8" style="padding: 20px; color: #666;">No registered users found. Click "Setup Database" or register a new user.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($users as $u): ?>
                                <tr>
                                    <td><?php echo $u['id']; ?></td>
                                    <td style="font-weight: bold; text-align: left;"><?php echo htmlspecialchars($u['full_name']); ?></td>
                                    <td><?php echo htmlspecialchars($u['username']); ?></td>
                                    <td style="text-align: left;"><?php echo htmlspecialchars($u['email']); ?></td>
                                    <td><?php echo htmlspecialchars($u['mobile']); ?></td>
                                    <td>
                                        <span class="badge badge-role"><?php echo htmlspecialchars($u['role']); ?></span>
                                    </td>
                                    <td>
                                        <span class="hash-code" title="<?php echo htmlspecialchars($u['password']); ?>">
                                            <?php echo htmlspecialchars($u['password']); ?>
                                        </span>
                                    </td>
                                    <td style="font-size: 12px;"><?php echo htmlspecialchars($u['created_at']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="container">
            <h3>Registration Audit Logs (Security Tracking)</h3>
            <div class="info-box">
                Tracks registration requests with client IP address, action type, status, and browser user agent to prevent spam or unauthorized duplicate registration attempts.
            </div>

            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Log ID</th>
                            <th>User ID</th>
                            <th>Username</th>
                            <th>Action</th>
                            <th>Status</th>
                            <th>IP Address</th>
                            <th>User Agent</th>
                            <th>Timestamp</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($audit_logs)): ?>
                            <tr>
                                <td colspan="8" style="padding: 20px; color: #666;">No audit log records available.</td>
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
                                        <?php else: ?>
                                            <span class="badge badge-fail"><?php echo htmlspecialchars($log['status']); ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($log['ip_address']); ?></td>
                                    <td style="text-align: left; font-size: 11px; max-width: 280px; word-break: break-all;">
                                        <?php echo htmlspecialchars(substr($log['user_agent'], 0, 70)); ?><?php echo strlen($log['user_agent']) > 70 ? '...' : ''; ?>
                                    </td>
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

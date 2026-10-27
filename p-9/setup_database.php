<?php
$messages = [];
$errors = [];

$raw_conn = @new mysqli("localhost", "root", "");
if ($raw_conn->connect_error) {
    $errors[] = "Failed to connect to MySQL: " . $raw_conn->connect_error;
} else {
    $sql_db = "CREATE DATABASE IF NOT EXISTS studenthub_db";
    if ($raw_conn->query($sql_db) === TRUE) {
        $messages[] = "Database 'studenthub_db' verified / created successfully.";
    } else {
        $errors[] = "Database creation failed: " . $raw_conn->error;
    }
    $raw_conn->close();
}

if (empty($errors)) {
    $conn = @new mysqli("localhost", "root", "", "studenthub_db");
    if ($conn->connect_error) {
        $errors[] = "Database connection failed: " . $conn->connect_error;
    } else {
        $sql_users = "CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(50) NOT NULL UNIQUE,
            email VARCHAR(100) NOT NULL UNIQUE,
            password VARCHAR(255) NOT NULL,
            full_name VARCHAR(100) NOT NULL,
            mobile VARCHAR(15) NOT NULL,
            role ENUM('Student', 'Faculty', 'Admin') DEFAULT 'Student',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )";

        if ($conn->query($sql_users) === TRUE) {
            $messages[] = "Table 'users' verified / created successfully.";
        } else {
            $errors[] = "Error creating 'users' table: " . $conn->error;
        }

        $sql_audit = "CREATE TABLE IF NOT EXISTS audit_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NULL,
            username VARCHAR(50) NOT NULL,
            action VARCHAR(50) NOT NULL,
            status VARCHAR(30) NOT NULL,
            ip_address VARCHAR(45) NOT NULL,
            user_agent TEXT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
        )";

        if ($conn->query($sql_audit) === TRUE) {
            $messages[] = "Table 'audit_logs' verified / created successfully.";
        } else {
            $errors[] = "Error creating 'audit_logs' table: " . $conn->error;
        }

        $check_users = $conn->query("SELECT COUNT(*) AS total FROM users");
        $user_count = 0;
        if ($check_users && $row = $check_users->fetch_assoc()) {
            $user_count = intval($row['total']);
        }

        if ($user_count === 0) {
            $pass_hash = password_hash("Password@123", PASSWORD_BCRYPT);
            $seed_stmt = $conn->prepare("INSERT INTO users (username, email, password, full_name, mobile, role) VALUES (?, ?, ?, ?, ?, ?)");
            
            $u1 = "miral082"; $e1 = "25ce082@charusat.edu.in"; $f1 = "Miral Patel"; $m1 = "9876543210"; $r1 = "Student";
            $seed_stmt->bind_param("ssssss", $u1, $e1, $pass_hash, $f1, $m1, $r1);
            $seed_stmt->execute();
            $uid1 = $conn->insert_id;

            $u2 = "rahul_shah"; $e2 = "rahul@charusat.edu.in"; $f2 = "Rahul Shah"; $m2 = "9898989898"; $r2 = "Student";
            $seed_stmt->bind_param("ssssss", $u2, $e2, $pass_hash, $f2, $m2, $r2);
            $seed_stmt->execute();
            $uid2 = $conn->insert_id;

            $u3 = "admin_hub"; $e3 = "admin@charusat.edu.in"; $f3 = "System Administrator"; $m3 = "9123456780"; $r3 = "Admin";
            $seed_stmt->bind_param("ssssss", $u3, $e3, $pass_hash, $f3, $m3, $r3);
            $seed_stmt->execute();
            $uid3 = $conn->insert_id;

            $seed_stmt->close();

            $log_stmt = $conn->prepare("INSERT INTO audit_logs (user_id, username, action, status, ip_address, user_agent) VALUES (?, ?, ?, ?, ?, ?)");
            $act = "SEED_USER"; $st = "SUCCESS"; $ip = "127.0.0.1"; $ua = "System Seeder";
            
            $log_stmt->bind_param("isssss", $uid1, $u1, $act, $st, $ip, $ua);
            $log_stmt->execute();
            $log_stmt->bind_param("isssss", $uid2, $u2, $act, $st, $ip, $ua);
            $log_stmt->execute();
            $log_stmt->bind_param("isssss", $uid3, $u3, $act, $st, $ip, $ua);
            $log_stmt->execute();
            $log_stmt->close();

            $messages[] = "Default seed users and audit log records created successfully.";
        }

        $res_u = $conn->query("SELECT COUNT(*) AS total FROM users");
        $final_user_count = $res_u ? $res_u->fetch_assoc()['total'] : 0;

        $res_a = $conn->query("SELECT COUNT(*) AS total FROM audit_logs");
        $final_audit_count = $res_a ? $res_a->fetch_assoc()['total'] : 0;

        $conn->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>StudentHub - Setup Database</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <header id="header">
        <img src="image/charusatlogo.png" alt="CHARUSAT Logo">
        <div>StudentHub Registration System</div>
        <nav>
            <a href="index.php">Register</a>
            <a href="users_list.php">View Users &amp; Logs</a>
            <a href="setup_database.php" class="active">Setup Database</a>
        </nav>
    </header>

    <main class="main-content">
        <section class="container">
            <h2>Practical 9: Database Setup &amp; Migration</h2>
            <div class="info-box">
                This utility initializes the <code>studenthub_db</code> database, verifies the <code>users</code> and <code>audit_logs</code> tables, and seeds initial records with <code>password_hash()</code> encryption.
            </div>

            <?php if (!empty($messages)): ?>
                <div class="alert-success">
                    <?php foreach ($messages as $msg): ?>
                        <div>&check; <?php echo htmlspecialchars($msg); ?></div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($errors)): ?>
                <div class="alert-error">
                    <?php foreach ($errors as $err): ?>
                        <div>&cross; <?php echo htmlspecialchars($err); ?></div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if (empty($errors)): ?>
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-label">Total Users</div>
                        <div class="stat-number"><?php echo $final_user_count; ?></div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-label">Audit Log Entries</div>
                        <div class="stat-number"><?php echo $final_audit_count; ?></div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-label">Database Status</div>
                        <div class="stat-number" style="font-size: 20px; color: #10b981;">CONNECTED</div>
                    </div>
                </div>

                <div class="btn-row">
                    <a href="index.php" class="btn-submit" style="text-align: center; text-decoration: none;">Go to Registration Form</a>
                    <a href="users_list.php" class="btn-secondary">View Registered Users &amp; Hashes</a>
                </div>
            <?php endif; ?>
        </section>
    </main>

    <footer>
        MIRAL PATEL||25CE082||STUDENT HUB PORTAL
    </footer>
</body>
</html>

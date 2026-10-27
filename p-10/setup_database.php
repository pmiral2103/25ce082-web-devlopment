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
            last_login DATETIME NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )";

        if ($conn->query($sql_users) === TRUE) {
            $messages[] = "Table 'users' verified / created successfully.";
        } else {
            $errors[] = "Error creating 'users' table: " . $conn->error;
        }

        $col_check = $conn->query("SHOW COLUMNS FROM users LIKE 'last_login'");
        if ($col_check && $col_check->num_rows == 0) {
            $conn->query("ALTER TABLE users ADD COLUMN last_login DATETIME NULL AFTER role");
            $messages[] = "Column 'last_login' added to 'users' table.";
        }

        $sql_tokens = "CREATE TABLE IF NOT EXISTS remember_tokens (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            token_hash VARCHAR(64) NOT NULL UNIQUE,
            expires_at DATETIME NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )";

        if ($conn->query($sql_tokens) === TRUE) {
            $messages[] = "Table 'remember_tokens' verified / created successfully.";
        } else {
            $errors[] = "Error creating 'remember_tokens' table: " . $conn->error;
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

        $seed_users = [
            [
                'username' => 'miral082',
                'email' => '25ce082@charusat.edu.in',
                'password' => 'Password@123',
                'full_name' => 'Miral Patel',
                'mobile' => '9876543210',
                'role' => 'Student'
            ],
            [
                'username' => 'admin_hub',
                'email' => 'admin@charusat.edu.in',
                'password' => 'Admin@123',
                'full_name' => 'System Administrator',
                'mobile' => '9123456780',
                'role' => 'Admin'
            ],
            [
                'username' => 'prof_sharma',
                'email' => 'sharma@charusat.edu.in',
                'password' => 'Faculty@123',
                'full_name' => 'Prof. R. K. Sharma',
                'mobile' => '9898989898',
                'role' => 'Faculty'
            ]
        ];

        foreach ($seed_users as $su) {
            $chk = $conn->prepare("SELECT id FROM users WHERE username = ?");
            $chk->bind_param("s", $su['username']);
            $chk->execute();
            $r = $chk->get_result();
            $hash = password_hash($su['password'], PASSWORD_BCRYPT);
            if ($r->num_rows == 0) {
                $ins = $conn->prepare("INSERT INTO users (username, email, password, full_name, mobile, role, last_login) VALUES (?, ?, ?, ?, ?, ?, NOW())");
                $ins->bind_param("ssssss", $su['username'], $su['email'], $hash, $su['full_name'], $su['mobile'], $su['role']);
                $ins->execute();
                $ins->close();
            } else {
                $upd = $conn->prepare("UPDATE users SET password = ?, role = ? WHERE username = ?");
                $upd->bind_param("sss", $hash, $su['role'], $su['username']);
                $upd->execute();
                $upd->close();
            }
            $chk->close();
        }
        $messages[] = "Seed test accounts (Student, Admin, Faculty) synced with verified Bcrypt passwords.";

        $res_u = $conn->query("SELECT COUNT(*) AS total FROM users");
        $final_user_count = $res_u ? $res_u->fetch_assoc()['total'] : 0;

        $res_t = $conn->query("SELECT COUNT(*) AS total FROM remember_tokens");
        $final_token_count = $res_t ? $res_t->fetch_assoc()['total'] : 0;

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
    <title>StudentHub - Setup Database (Practical 10)</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <header id="header">
        <img src="image/charusatlogo.png" alt="CHARUSAT Logo">
        <div>StudentHub Authentication System</div>
        <nav>
            <a href="index.php">Login</a>
            <a href="student_dashboard.php">Student Portal</a>
            <a href="admin_dashboard.php">Admin Portal</a>
            <a href="setup_database.php" class="active">Setup DB</a>
        </nav>
    </header>

    <main class="main-content">
        <section class="container">
            <h2>Practical 10: Database Setup &amp; Migration</h2>
            <div class="info-box">
                Initializes database schema for Practical 10: <code>users</code> (with <code>last_login</code>), <code>remember_tokens</code> (secure SHA-256 hashed persistent tokens), and <code>audit_logs</code>.
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
                        <div class="stat-label">Active Remember Tokens</div>
                        <div class="stat-number"><?php echo $final_token_count; ?></div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-label">Audit Log Records</div>
                        <div class="stat-number"><?php echo $final_audit_count; ?></div>
                    </div>
                </div>

                <div class="info-box" style="margin-top: 15px;">
                    <strong>Pre-configured Demo Accounts:</strong>
                    <ul style="margin: 5px 0 0 20px; padding: 0;">
                        <li><strong>Student Account:</strong> Username: <code>miral082</code> | Password: <code>Password@123</code> (Role: Student)</li>
                        <li><strong>Admin Account:</strong> Username: <code>admin_hub</code> | Password: <code>Admin@123</code> (Role: Admin)</li>
                        <li><strong>Faculty Account:</strong> Username: <code>prof_sharma</code> | Password: <code>Faculty@123</code> (Role: Faculty)</li>
                    </ul>
                </div>

                <div class="btn-row" style="margin-top: 20px;">
                    <a href="index.php" class="btn-submit" style="text-align: center; text-decoration: none;">Go to Login Page</a>
                </div>
            <?php endif; ?>
        </section>
    </main>

    <footer>
        MIRAL PATEL||25CE082||STUDENT HUB PORTAL
    </footer>
</body>
</html>

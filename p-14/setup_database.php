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
            status ENUM('Active', 'Inactive') DEFAULT 'Active',
            last_login DATETIME NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
        $conn->query($sql_users);

        $chk_status = $conn->query("SHOW COLUMNS FROM users LIKE 'status'");
        if ($chk_status && $chk_status->num_rows == 0) {
            $conn->query("ALTER TABLE users ADD COLUMN status ENUM('Active', 'Inactive') DEFAULT 'Active' AFTER role");
            $messages[] = "Column 'status' added to 'users' table.";
        }

        $chk_login = $conn->query("SHOW COLUMNS FROM users LIKE 'last_login'");
        if ($chk_login && $chk_login->num_rows == 0) {
            $conn->query("ALTER TABLE users ADD COLUMN last_login DATETIME NULL AFTER status");
        }

        $sql_students = "CREATE TABLE IF NOT EXISTS students (
            id INT AUTO_INCREMENT PRIMARY KEY,
            student_id_no VARCHAR(20) NOT NULL UNIQUE,
            name VARCHAR(100) NOT NULL,
            email VARCHAR(100) NOT NULL UNIQUE,
            mobile VARCHAR(15) NOT NULL,
            department VARCHAR(50) NOT NULL,
            semester VARCHAR(10) DEFAULT '4',
            cgpa DECIMAL(3,2) DEFAULT 8.00,
            status ENUM('Active', 'Inactive') DEFAULT 'Active',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
        $conn->query($sql_students);

        $sql_events = "CREATE TABLE IF NOT EXISTS events (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(150) NOT NULL,
            category VARCHAR(50) NOT NULL,
            event_date DATE NOT NULL,
            event_time VARCHAR(20) NOT NULL,
            venue VARCHAR(100) NOT NULL,
            coordinator VARCHAR(100) NOT NULL,
            status ENUM('Open', 'Closed') DEFAULT 'Open',
            poster VARCHAR(255) DEFAULT 'default_poster.png',
            description TEXT DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
        $conn->query($sql_events);

        $sql_reg = "CREATE TABLE IF NOT EXISTS registrations (
            id INT AUTO_INCREMENT PRIMARY KEY,
            student_id INT NOT NULL,
            event_id INT NOT NULL,
            registration_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            status VARCHAR(20) DEFAULT 'Confirmed',
            remarks VARCHAR(255) DEFAULT NULL,
            UNIQUE KEY unique_reg (student_id, event_id),
            CONSTRAINT fk_reg_stud FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
            CONSTRAINT fk_reg_ev FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
        $conn->query($sql_reg);

        $sql_audit = "CREATE TABLE IF NOT EXISTS audit_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NULL,
            username VARCHAR(50) NOT NULL,
            action VARCHAR(50) NOT NULL,
            status VARCHAR(30) NOT NULL,
            ip_address VARCHAR(45) NOT NULL,
            user_agent TEXT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
        $conn->query($sql_audit);

        $hash = password_hash("Admin@123", PASSWORD_BCRYPT);
        $pass_hash = password_hash("Password@123", PASSWORD_BCRYPT);
        $fac_hash = password_hash("Faculty@123", PASSWORD_BCRYPT);

        $seed_users = [
            ['admin_hub', 'admin@charusat.edu.in', $hash, 'System Administrator', '9123456780', 'Admin', 'Active'],
            ['miral082', '25ce082@charusat.edu.in', $pass_hash, 'Miral Patel', '9876543210', 'Student', 'Active'],
            ['prof_sharma', 'sharma@charusat.edu.in', $fac_hash, 'Prof. R. K. Sharma', '9898989898', 'Faculty', 'Active'],
            ['rahul_shah', 'rahul@charusat.edu.in', $pass_hash, 'Rahul Shah', '9898012345', 'Student', 'Active'],
            ['priya_ce', 'priya@charusat.edu.in', $pass_hash, 'Priya Patel', '9898123456', 'Student', 'Inactive'],
            ['karan_ce', 'karan@charusat.edu.in', $pass_hash, 'Karan Joshi', '9898234567', 'Student', 'Active'],
            ['ananya_it', 'ananya@charusat.edu.in', $pass_hash, 'Ananya Sharma', '9898345678', 'Student', 'Active']
        ];

        foreach ($seed_users as $u) {
            $chk = $conn->prepare("SELECT id FROM users WHERE username = ?");
            $chk->bind_param("s", $u[0]);
            $chk->execute();
            if ($chk->get_result()->num_rows == 0) {
                $ins = $conn->prepare("INSERT INTO users (username, email, password, full_name, mobile, role, status, last_login) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
                $ins->bind_param("sssssss", $u[0], $u[1], $u[2], $u[3], $u[4], $u[5], $u[6]);
                $ins->execute();
                $ins->close();
            }
            $chk->close();
        }
        $messages[] = "User accounts synced with roles (Admin, Faculty, Student) and active/inactive status.";

        $stud_res = $conn->query("SELECT id FROM students LIMIT 10");
        $stud_ids = [];
        if ($stud_res) {
            while ($r = $stud_res->fetch_assoc()) $stud_ids[] = $r['id'];
        }

        $ev_res = $conn->query("SELECT id FROM events LIMIT 6");
        $ev_ids = [];
        if ($ev_res) {
            while ($r = $ev_res->fetch_assoc()) $ev_ids[] = $r['id'];
        }

        if (count($stud_ids) >= 4 && count($ev_ids) >= 3) {
            $monthly_regs = [
                [$stud_ids[0], $ev_ids[0], '2026-08-15 10:30:00', 'Confirmed', 'Early Bird Registration'],
                [$stud_ids[1], $ev_ids[0], '2026-08-22 14:15:00', 'Confirmed', 'Department Nominee'],
                [$stud_ids[2], $ev_ids[1], '2026-09-05 09:45:00', 'Confirmed', 'Solo participant'],
                [$stud_ids[3], $ev_ids[1], '2026-09-18 11:20:00', 'Confirmed', 'Hackathon team leader'],
                [$stud_ids[0], $ev_ids[1], '2026-10-02 16:00:00', 'Confirmed', 'Campus Ambassador'],
                [$stud_ids[1], $ev_ids[2], '2026-10-14 12:30:00', 'Confirmed', 'Cultural night participant'],
                [$stud_ids[2], $ev_ids[2], '2026-10-25 15:45:00', 'Confirmed', 'Group dance entry'],
                [$stud_ids[3], $ev_ids[0], '2026-11-04 10:00:00', 'Confirmed', 'Coding sprint entry'],
                [$stud_ids[0], $ev_ids[2], '2026-11-12 11:30:00', 'Confirmed', 'Drama team entry']
            ];

            foreach ($monthly_regs as $mr) {
                $chk_r = $conn->prepare("SELECT id FROM registrations WHERE student_id = ? AND event_id = ?");
                $chk_r->bind_param("ii", $mr[0], $mr[1]);
                $chk_r->execute();
                if ($chk_r->get_result()->num_rows == 0) {
                    $ins_r = $conn->prepare("INSERT INTO registrations (student_id, event_id, registration_date, status, remarks) VALUES (?, ?, ?, ?, ?)");
                    $ins_r->bind_param("iisss", $mr[0], $mr[1], $mr[2], $mr[3], $mr[4]);
                    $ins_r->execute();
                    $ins_r->close();
                }
                $chk_r->close();
            }
            $messages[] = "Monthly event registration records synced for analytics chart.";
        }

        $res_u = $conn->query("SELECT COUNT(*) AS total FROM users");
        $final_users = $res_u ? $res_u->fetch_assoc()['total'] : 0;

        $res_s = $conn->query("SELECT COUNT(*) AS total FROM students");
        $final_students = $res_s ? $res_s->fetch_assoc()['total'] : 0;

        $res_e = $conn->query("SELECT COUNT(*) AS total FROM events");
        $final_events = $res_e ? $res_e->fetch_assoc()['total'] : 0;

        $res_r = $conn->query("SELECT COUNT(*) AS total FROM registrations");
        $final_regs = $res_r ? $res_r->fetch_assoc()['total'] : 0;

        $conn->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>StudentHub - Setup Admin Dashboard DB (Practical 14)</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <header id="header">
        <img src="image/charusatlogo.png" alt="CHARUSAT Logo">
        <div>StudentHub - Admin Dashboard &amp; RBAC System</div>
        <nav>
            <a href="login.php">Login Portal</a>
            <a href="index.php">Admin Dashboard</a>
            <a href="setup_database.php" class="active">Setup DB</a>
        </nav>
    </header>

    <main class="main-content">
        <section class="container">
            <h2>Practical 14: Admin Dashboard Database Setup</h2>
            <div class="info-box">
                Initializes schema for Practical 14: <code>users</code> with active/inactive status toggle, multi-month <code>registrations</code> for analytics charts, and security <code>audit_logs</code>.
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
                        <div class="stat-number"><?php echo $final_users; ?></div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-label">Students</div>
                        <div class="stat-number"><?php echo $final_students; ?></div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-label">Events</div>
                        <div class="stat-number"><?php echo $final_events; ?></div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-label">Event Registrations</div>
                        <div class="stat-number" style="color: #10b981;"><?php echo $final_regs; ?></div>
                    </div>
                </div>

                <div class="info-box" style="margin-top: 15px;">
                    <strong>Demo Accounts for Viva:</strong>
                    <ul style="margin: 5px 0 0 20px; padding: 0;">
                        <li><strong>Admin Account:</strong> Username: <code>admin_hub</code> | Password: <code>Admin@123</code> (Full Dashboard + Analytics + Status Toggles)</li>
                        <li><strong>Student Account:</strong> Username: <code>miral082</code> | Password: <code>Password@123</code> (Redirected / Restricted)</li>
                        <li><strong>Faculty Account:</strong> Username: <code>prof_sharma</code> | Password: <code>Faculty@123</code> (Faculty Dynamic Menu)</li>
                    </ul>
                </div>

                <div class="btn-row" style="margin-top: 20px;">
                    <a href="login.php" class="btn-submit" style="text-align: center; text-decoration: none;">Go to Login Portal</a>
                </div>
            <?php endif; ?>
        </section>
    </main>

    <footer>
        MIRAL PATEL||25CE082||STUDENT HUB PORTAL
    </footer>
</body>
</html>

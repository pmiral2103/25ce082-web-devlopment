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
        $sql_table = "CREATE TABLE IF NOT EXISTS students (
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

        if ($conn->query($sql_table) === TRUE) {
            $messages[] = "Table 'students' verified / created successfully.";
        } else {
            $errors[] = "Table creation failed: " . $conn->error;
        }

        $check_sem = $conn->query("SHOW COLUMNS FROM students LIKE 'semester'");
        if ($check_sem && $check_sem->num_rows == 0) {
            $conn->query("ALTER TABLE students ADD COLUMN semester VARCHAR(10) DEFAULT '4' AFTER department");
            $messages[] = "Column 'semester' added to 'students' table.";
        }

        $check_cgpa = $conn->query("SHOW COLUMNS FROM students LIKE 'cgpa'");
        if ($check_cgpa && $check_cgpa->num_rows == 0) {
            $conn->query("ALTER TABLE students ADD COLUMN cgpa DECIMAL(3,2) DEFAULT 8.00 AFTER semester");
            $messages[] = "Column 'cgpa' added to 'students' table.";
        }

        $check_status = $conn->query("SHOW COLUMNS FROM students LIKE 'status'");
        if ($check_status && $check_status->num_rows == 0) {
            $conn->query("ALTER TABLE students ADD COLUMN status ENUM('Active', 'Inactive') DEFAULT 'Active' AFTER cgpa");
            $messages[] = "Column 'status' (Soft-Delete) added to 'students' table.";
        }

        $seed_data = [
            ['25CE082', 'Miral Patel', '25ce082@charusat.edu.in', '9876543210', 'Computer Engineering', '4', 8.85, 'Active'],
            ['25CE001', 'Aarav Mehta', '25ce001@charusat.edu.in', '9876500001', 'Computer Engineering', '4', 8.45, 'Active'],
            ['25CE015', 'Bhavya Shah', '25ce015@charusat.edu.in', '9876500015', 'Computer Engineering', '4', 7.90, 'Active'],
            ['25CE042', 'Dhruv Joshi', '25ce042@charusat.edu.in', '9876500042', 'Computer Engineering', '4', 9.10, 'Active'],
            ['25CE065', 'Kavya Trivedi', '25ce065@charusat.edu.in', '9876500065', 'Computer Engineering', '4', 8.75, 'Active'],
            ['25IT004', 'Ananya Sharma', '25it004@charusat.edu.in', '9876510004', 'Information Technology', '4', 8.60, 'Active'],
            ['25IT021', 'Devansh Patel', '25it021@charusat.edu.in', '9876510021', 'Information Technology', '4', 7.80, 'Active'],
            ['25IT038', 'Harshil Vora', '25it038@charusat.edu.in', '9876510038', 'Information Technology', '4', 8.30, 'Active'],
            ['25IT055', 'Isha Pandya', '25it055@charusat.edu.in', '9876510055', 'Information Technology', '4', 9.25, 'Active'],
            ['25CS008', 'Jaydeep Barot', '25cs008@charusat.edu.in', '9876520008', 'Computer Science', '4', 8.15, 'Active'],
            ['25CS019', 'Khushi Prajapati', '25cs019@charusat.edu.in', '9876520019', 'Computer Science', '4', 8.90, 'Active'],
            ['25CS034', 'Manan Desai', '25cs034@charusat.edu.in', '9876520034', 'Computer Science', '4', 7.65, 'Inactive'],
            ['25CS050', 'Nishi Gajjar', '25cs050@charusat.edu.in', '9876520050', 'Computer Science', '4', 8.55, 'Active'],
            ['25EC003', 'Omkar Soni', '25ec003@charusat.edu.in', '9876530003', 'Electronics & Communication', '4', 7.95, 'Active'],
            ['25EC017', 'Parth Parmar', '25ec017@charusat.edu.in', '9876530017', 'Electronics & Communication', '4', 8.20, 'Active'],
            ['25EC029', 'Riddhi Dave', '25ec029@charusat.edu.in', '9876530029', 'Electronics & Communication', '4', 9.00, 'Active'],
            ['25ME005', 'Smit Makwana', '25me005@charusat.edu.in', '9876540005', 'Mechanical Engineering', '4', 7.40, 'Inactive'],
            ['25ME018', 'Tanvi Solanki', '25me018@charusat.edu.in', '9876540018', 'Mechanical Engineering', '4', 8.10, 'Active'],
            ['25CL002', 'Urvish Rathod', '25cl002@charusat.edu.in', '9876550002', 'Civil Engineering', '4', 7.70, 'Active'],
            ['25CL014', 'Vishwa Panchal', '25cl014@charusat.edu.in', '9876550014', 'Civil Engineering', '4', 8.40, 'Active'],
            ['25CE095', 'Yashvi Raval', '25ce095@charusat.edu.in', '9876500095', 'Computer Engineering', '4', 8.95, 'Active'],
            ['25IT062', 'Zeel Shah', '25it062@charusat.edu.in', '9876510062', 'Information Technology', '4', 9.05, 'Active']
        ];

        foreach ($seed_data as $s) {
            $chk = $conn->prepare("SELECT id FROM students WHERE student_id_no = ?");
            $chk->bind_param("s", $s[0]);
            $chk->execute();
            $r = $chk->get_result();
            if ($r->num_rows == 0) {
                $ins = $conn->prepare("INSERT INTO students (student_id_no, name, email, mobile, department, semester, cgpa, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                $ins->bind_param("ssssssds", $s[0], $s[1], $s[2], $s[3], $s[4], $s[5], $s[6], $s[7]);
                $ins->execute();
                $ins->close();
            } else {
                $upd = $conn->prepare("UPDATE students SET name = ?, email = ?, mobile = ?, department = ?, semester = ?, cgpa = ?, status = ? WHERE student_id_no = ?");
                $upd->bind_param("sssssdss", $s[1], $s[2], $s[3], $s[4], $s[5], $s[6], $s[7], $s[0]);
                $upd->execute();
                $upd->close();
            }
            $chk->close();
        }
        $messages[] = "22 Seeded student records synced with CHARUSAT dataset.";

        $res_tot = $conn->query("SELECT COUNT(*) AS total FROM students");
        $final_total = $res_tot ? $res_tot->fetch_assoc()['total'] : 0;

        $res_act = $conn->query("SELECT COUNT(*) AS total FROM students WHERE status = 'Active'");
        $final_active = $res_act ? $res_act->fetch_assoc()['total'] : 0;

        $res_inact = $conn->query("SELECT COUNT(*) AS total FROM students WHERE status = 'Inactive'");
        $final_inactive = $res_inact ? $res_inact->fetch_assoc()['total'] : 0;

        $conn->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>StudentHub - Setup Student Management DB (Practical 11)</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <header id="header">
        <img src="image/charusatlogo.png" alt="CHARUSAT Logo">
        <div>StudentHub - Student Management System</div>
        <nav>
            <a href="index.php">Manage Students</a>
            <a href="setup_database.php" class="active">Setup DB</a>
        </nav>
    </header>

    <main class="main-content">
        <section class="container">
            <h2>Practical 11: Student Management Database Setup</h2>
            <div class="info-box">
                Initializes schema for Practical 11: <code>students</code> table with soft-delete <code>status</code> column, semester, CGPA, and 22 seeded student records.
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
                        <div class="stat-label">Total Seeded Students</div>
                        <div class="stat-number"><?php echo $final_total; ?></div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-label">Active Records</div>
                        <div class="stat-number" style="color: #10b981;"><?php echo $final_active; ?></div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-label">Soft-Deleted (Inactive)</div>
                        <div class="stat-number" style="color: #ef4444;"><?php echo $final_inactive; ?></div>
                    </div>
                </div>

                <div class="btn-row" style="margin-top: 20px;">
                    <a href="index.php" class="btn-submit" style="text-align: center; text-decoration: none;">Go to Student Management CRUD</a>
                </div>
            <?php endif; ?>
        </section>
    </main>

    <footer>
        MIRAL PATEL||25CE082||STUDENT HUB PORTAL
    </footer>
</body>
</html>

<?php
$host = "localhost";
$username = "root";
$password = "";
$message = "";
$status = "";

try {
    $pdo = new PDO("mysql:host=$host;charset=utf8mb4", $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);

    $pdo->exec("CREATE DATABASE IF NOT EXISTS studenthub_db; USE studenthub_db;");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS students (
            id INT AUTO_INCREMENT PRIMARY KEY,
            student_id_no VARCHAR(20) NOT NULL UNIQUE,
            name VARCHAR(100) NOT NULL,
            email VARCHAR(100) NOT NULL UNIQUE,
            mobile VARCHAR(15) NOT NULL,
            department VARCHAR(50) NOT NULL,
            year VARCHAR(10) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

        CREATE TABLE IF NOT EXISTS events (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL,
            event_date DATE NOT NULL,
            event_time VARCHAR(20) NOT NULL,
            venue VARCHAR(100) NOT NULL,
            coordinator VARCHAR(100) NOT NULL,
            category VARCHAR(50) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

        CREATE TABLE IF NOT EXISTS registrations (
            id INT AUTO_INCREMENT PRIMARY KEY,
            student_id INT NOT NULL,
            event_id INT NOT NULL,
            registration_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            status VARCHAR(20) DEFAULT 'Confirmed',
            remarks VARCHAR(255) DEFAULT NULL,
            UNIQUE KEY unique_registration (student_id, event_id),
            CONSTRAINT fk_registration_student FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
            CONSTRAINT fk_registration_event FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    $pdo->exec("
        INSERT INTO students (student_id_no, name, email, mobile, department, year) VALUES
        ('25CE082', 'Miral Patel', '25ce082@charusat.edu.in', '9876543210', 'CE', '2nd'),
        ('25CE083', 'Omi Patel', '25ce083@charusat.edu.in', '9823456781', 'CE', '2nd'),
        ('25IT005', 'Sneha Joshi', '25it005@charusat.edu.in', '9898123456', 'IT', '2nd'),
        ('25CE023', 'Dhruva Mehta', '25ce023@charusat.edu.in', '9712345678', 'CE', '2nd'),
        ('25CS014', 'Isha Shah', '25cs014@charusat.edu.in', '9909876543', 'CS', '2nd')
        ON DUPLICATE KEY UPDATE student_id_no = student_id_no;

        INSERT INTO events (name, event_date, event_time, venue, coordinator, category) VALUES
        ('Web Development Workshop', '2026-11-15', '10:00 AM', 'MAC Lab', 'By CE Department', 'Workshop'),
        ('Coding Competition', '2026-11-20', '11:00 AM', 'Computer Lab', 'By Technical Cell', 'Technical'),
        ('Hackathon 2026', '2026-12-05', '09:00 AM', 'Innovation Center', 'By University', 'Technical'),
        ('Sports Day', '2027-01-25', '08:30 AM', 'College Ground', 'Sports Committee', 'Sports'),
        ('AI Workshop', '2027-02-10', '10:30 AM', 'Seminar Hall', 'AI Department', 'Workshop')
        ON DUPLICATE KEY UPDATE name = name;

        INSERT INTO registrations (student_id, event_id, status, remarks) VALUES
        (1, 1, 'Confirmed', 'Enthusiastic to learn PHP & MySQL'),
        (1, 3, 'Confirmed', 'Participating in team web dev track'),
        (2, 1, 'Confirmed', 'Registered for lab session'),
        (3, 2, 'Confirmed', 'Competitive coding track'),
        (4, 4, 'Confirmed', 'Cricket tournament participant')
        ON DUPLICATE KEY UPDATE id = id;
    ");

    $pdo->exec("DROP PROCEDURE IF EXISTS sp_register_student_event;");
    $pdo->exec("
        CREATE PROCEDURE sp_register_student_event(
            IN p_student_id INT,
            IN p_event_id INT,
            IN p_remarks VARCHAR(255)
        )
        BEGIN
            INSERT INTO registrations (student_id, event_id, remarks)
            VALUES (p_student_id, p_event_id, p_remarks);
        END
    ");

    $message = "Database 'studenthub_db', normalized tables, seed data, and Stored Procedures setup successfully!";
    $status = "success";
} catch (PDOException $e) {
    $message = "Database setup failed: " . $e->getMessage();
    $status = "error";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Setup - StudentHub</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

    <header id="header">
        <img src="image/charusatlogo.png" alt="charusat logo">
        CHARUSAT<br>CHAROTAR UNIVERSITY OF SCIENCE AND TECHNOLOGY
        <nav id="navMenu">
            <a href="index.php">Dashboard</a>
            <a href="setup_database.php">Setup Database</a>
            <a href="../p-2/html/home.html">Portal Home</a>
        </nav>
    </header>

    <main>
        <div class="container" style="width: 550px; text-align: center;">
            <h2>MySQL Database Automatic Setup</h2>

            <?php if ($status == "success"): ?>
                <div class="alert-success"><?php echo $message; ?></div>
                <p style="color: #003366;">You can now view live data, test database connectivity, and register students for events.</p>
                <div style="margin-top: 20px;">
                    <a href="index.php" class="btn-submit" style="text-decoration: none; display: inline-block;">Open StudentHub DB Dashboard &rarr;</a>
                </div>
            <?php else: ?>
                <div class="alert-error"><?php echo $message; ?></div>
                <p style="color: red;">Make sure XAMPP MySQL service is running, then refresh this page.</p>
            <?php endif; ?>
        </div>
    </main>

    <footer>
        MIRAL PATEL||25CE082||STUDENT HUB PORTAL
    </footer>

</body>
</html>

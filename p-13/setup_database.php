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
        $sql_events = "CREATE TABLE IF NOT EXISTS events (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(150) NOT NULL,
            category VARCHAR(50) NOT NULL,
            event_date DATE NOT NULL,
            event_time VARCHAR(20) NOT NULL,
            venue VARCHAR(100) NOT NULL,
            coordinator VARCHAR(100) NOT NULL,
            status ENUM('Open', 'Closed') DEFAULT 'Open',
            description TEXT DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

        if ($conn->query($sql_events) === TRUE) {
            $messages[] = "Table 'events' verified / created successfully.";
        } else {
            $errors[] = "Error creating 'events' table: " . $conn->error;
        }

        $seed_events = [
            [1, 'HackCHARUSAT 2026', 'Technical', '2026-11-15', '09:00 AM', 'CSPIT Computer Center', 'Dr. A. K. Patel', 'Open', 'Annual 36-hour national level hackathon for innovative software and AI solutions.'],
            [2, 'Cognizance Tech Fest', 'Technical', '2026-11-20', '10:00 AM', 'CHARUSAT Auditorium', 'Prof. R. M. Shah', 'Open', 'State level tech symposium with robotics, coding sprints, and paper presentations.'],
            [3, 'Spoural Cultural Night', 'Cultural', '2026-12-05', '06:00 PM', 'University Central Ground', 'Dr. N. B. Joshi', 'Open', 'Grand university cultural fest featuring music, drama, dance, and fashion show.'],
            [4, 'AI & Deep Learning Workshop', 'Workshop', '2026-11-10', '11:00 AM', 'Seminar Hall, Depstar', 'Prof. S. V. Trivedi', 'Closed', 'Hands-on training session on PyTorch and Generative AI transformer models.'],
            [5, 'Inter-College Cricket Cup', 'Sports', '2026-11-28', '08:00 AM', 'CHARUSAT Sports Complex', 'Mr. D. P. Parmar', 'Open', 'Annual inter-departmental leather ball cricket tournament with 16 teams.'],
            [6, 'Cyber Security & Cloud Summit', 'Seminar', '2026-10-15', '02:00 PM', 'Conference Room, CSPIT', 'Dr. K. G. Desai', 'Closed', 'Expert talk by industry leaders on zero trust architecture and cloud safety.']
        ];

        foreach ($seed_events as $se) {
            $chk = $conn->prepare("SELECT id FROM events WHERE id = ?");
            $chk->bind_param("i", $se[0]);
            $chk->execute();
            $r = $chk->get_result();
            if ($r->num_rows == 0) {
                $ins = $conn->prepare("INSERT INTO events (id, name, category, event_date, event_time, venue, coordinator, status, description) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $ins->bind_param("issssssss", $se[0], $se[1], $se[2], $se[3], $se[4], $se[5], $se[6], $se[7], $se[8]);
                $ins->execute();
                $ins->close();
            } else {
                $upd = $conn->prepare("UPDATE events SET name = ?, category = ?, event_date = ?, event_time = ?, venue = ?, coordinator = ?, status = ?, description = ? WHERE id = ?");
                $upd->bind_param("ssssssssi", $se[1], $se[2], $se[3], $se[4], $se[5], $se[6], $se[7], $se[8], $se[0]);
                $upd->execute();
                $upd->close();
            }
            $chk->close();
        }
        $messages[] = "Seed events dataset synced successfully.";

        $res_tot = $conn->query("SELECT COUNT(*) AS total FROM events");
        $final_events_count = $res_tot ? $res_tot->fetch_assoc()['total'] : 0;

        $conn->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>StudentHub - Setup JSON API DB (Practical 13)</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <header id="header">
        <img src="image/charusatlogo.png" alt="CHARUSAT Logo">
        <div>StudentHub - JSON API &amp; AJAX CRUD System</div>
        <nav>
            <a href="index.php">Fetch API CRUD</a>
            <a href="setup_database.php" class="active">Setup DB</a>
        </nav>
    </header>

    <main class="main-content">
        <section class="container">
            <h2>Practical 13: Database &amp; API Setup</h2>
            <div class="info-box">
                Initializes schema for Practical 13: <code>events</code> table connected to PHP RESTful JSON API endpoints for asynchronous Fetch API CRUD operations.
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
                        <div class="stat-label">Total API Records</div>
                        <div class="stat-number"><?php echo $final_events_count; ?></div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-label">API Status</div>
                        <div class="stat-number" style="font-size: 20px; color: #10b981;">READY</div>
                    </div>
                </div>

                <div class="btn-row" style="margin-top: 20px;">
                    <a href="index.php" class="btn-submit" style="text-align: center; text-decoration: none;">Go to Fetch API Asynchronous CRUD</a>
                </div>
            <?php endif; ?>
        </section>
    </main>

    <footer>
        MIRAL PATEL||25CE082||STUDENT HUB PORTAL
    </footer>
</body>
</html>

<?php
require_once "db.php";

$message = "";
$error = "";

$students = [];
$events = [];
$registrations = [];
$student_count = 0;
$event_count = 0;
$registration_count = 0;

if ($pdo) {
    if (isset($_SERVER["REQUEST_METHOD"]) && $_SERVER["REQUEST_METHOD"] == "POST") {
        $student_id = isset($_POST['student_id']) ? intval($_POST['student_id']) : 0;
        $event_id = isset($_POST['event_id']) ? intval($_POST['event_id']) : 0;
        $remarks = isset($_POST['remarks']) ? htmlspecialchars(trim($_POST['remarks'])) : "";

        if ($student_id > 0 && $event_id > 0) {
            try {
                $stmt = $pdo->prepare("INSERT INTO registrations (student_id, event_id, remarks) VALUES (?, ?, ?)");
                $stmt->execute([$student_id, $event_id, $remarks]);
                $message = "Student registered for event successfully using PDO Prepared Statement!";
            } catch (PDOException $e) {
                if ($e->getCode() == 23000) {
                    $error = "Duplicate Entry: This student is already registered for this event!";
                } else {
                    $error = "Database Error: " . $e->getMessage();
                }
            }
        } else {
            $error = "Please select both a student and an event.";
        }
    }

    try {
        $students = $pdo->query("SELECT id, student_id_no, name, department FROM students ORDER BY student_id_no ASC")->fetchAll();
        $events = $pdo->query("SELECT id, name, category, event_date FROM events ORDER BY event_date ASC")->fetchAll();

        $student_count = count($students);
        $event_count = count($events);

        $stmt = $pdo->query("
            SELECT 
                r.id,
                s.student_id_no,
                s.name AS student_name,
                s.department,
                e.name AS event_name,
                e.venue,
                e.event_date,
                r.registration_date,
                r.status,
                r.remarks
            FROM registrations r
            JOIN students s ON r.student_id = s.id
            JOIN events e ON r.event_id = e.id
            ORDER BY r.id DESC
        ");
        $registrations = $stmt->fetchAll();
        $registration_count = count($registrations);
    } catch (PDOException $e) {
        $error = "Error querying database tables: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>StudentHub - MySQL & PDO Database</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

    <header id="header">
        <img src="image/charusatlogo.png" alt="charusat logo">
        CHARUSAT<br>CHAROTAR UNIVERSITY OF SCIENCE AND TECHNOLOGY
        <nav id="navMenu">
            <a href="index.php">Dashboard</a>
            <a href="setup_database.php">Setup Database</a>
            <a href="../php/index.php">Practical 7 (PHP)</a>
            <a href="../p-2/html/home.html">StudentHub Portal</a>
        </nav>
    </header>

    <main class="main-content">
        <?php if (!$pdo): ?>
            <div class="container" style="text-align: center;">
                <div class="alert-error">
                    Database Connection Error: <?php echo htmlspecialchars($db_error); ?>
                </div>
                <p>Database <code>studenthub_db</code> might not exist yet or MySQL service is not running.</p>
                <a href="setup_database.php" class="btn-submit" style="display: inline-block; width: auto; text-decoration: none; padding: 10px 20px;">Click here to Setup Database &amp; Tables</a>
            </div>
        <?php else: ?>

            <div class="container" style="padding: 15px 25px;">
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <div style="color: #155724; font-weight: bold; font-size: 15px;">
                        &#10004; MySQL Database Connected Successfully via PHP PDO (Database: <code>studenthub_db</code>)
                    </div>
                    <div>
                        <a href="setup_database.php" style="color: #1E3A8A; font-weight: bold; text-decoration: none;">[Re-run DB Setup]</a>
                    </div>
                </div>
            </div>

            <?php if (!empty($message)): ?>
                <div class="alert-success"><?php echo $message; ?></div>
            <?php endif; ?>

            <?php if (!empty($error)): ?>
                <div class="alert-error"><?php echo $error; ?></div>
            <?php endif; ?>

            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-label">TOTAL STUDENTS</div>
                    <div class="stat-number"><?php echo $student_count; ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">UPCOMING EVENTS</div>
                    <div class="stat-number"><?php echo $event_count; ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">EVENT REGISTRATIONS</div>
                    <div class="stat-number"><?php echo $registration_count; ?></div>
                </div>
            </div>

            <div class="two-col">
                <div class="col-form">
                    <div class="container">
                        <h3>Event Registration (Prepared Statement)</h3>

                        <form action="index.php" method="POST">
                            <label for="student_id">Select Student:</label>
                            <select name="student_id" id="student_id" required>
                                <option value="">-- Choose Student --</option>
                                <?php foreach ($students as $s): ?>
                                    <option value="<?php echo $s['id']; ?>">
                                        <?php echo htmlspecialchars($s['student_id_no'] . " - " . $s['name'] . " (" . $s['department'] . ")"); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>

                            <label for="event_id">Select Event:</label>
                            <select name="event_id" id="event_id" required>
                                <option value="">-- Choose Event --</option>
                                <?php foreach ($events as $ev): ?>
                                    <option value="<?php echo $ev['id']; ?>">
                                        <?php echo htmlspecialchars($ev['name'] . " [" . $ev['category'] . "] (" . $ev['event_date'] . ")"); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>

                            <label for="remarks">Remarks (Optional):</label>
                            <input type="text" name="remarks" id="remarks" placeholder="e.g. Team participation, remarks">

                            <input type="submit" value="Register for Event" class="btn-submit">
                        </form>
                    </div>
                </div>

                <div class="col-table">
                    <div class="container">
                        <h3>Confirmed Registrations (JOIN Query)</h3>

                        <table>
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Student</th>
                                    <th>Event</th>
                                    <th>Venue</th>
                                    <th>Date</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($registrations)): ?>
                                    <tr>
                                        <td colspan="6" style="padding: 20px; color: gray;">No registrations found. Register a student using the form.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($registrations as $r): ?>
                                        <tr>
                                            <td><b>#<?php echo $r['id']; ?></b></td>
                                            <td>
                                                <b><?php echo htmlspecialchars($r['student_name']); ?></b><br>
                                                <small style="color: gray;"><?php echo htmlspecialchars($r['student_id_no']); ?></small>
                                            </td>
                                            <td><?php echo htmlspecialchars($r['event_name']); ?></td>
                                            <td><?php echo htmlspecialchars($r['venue']); ?></td>
                                            <td><?php echo htmlspecialchars($r['event_date']); ?></td>
                                            <td><span class="badge"><?php echo htmlspecialchars($r['status']); ?></span></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        <?php endif; ?>
    </main>

    <footer>
        MIRAL PATEL||25CE082||STUDENT HUB PORTAL
    </footer>

</body>
</html>

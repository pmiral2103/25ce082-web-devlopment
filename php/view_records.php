<?php
$jsonFile = __DIR__ . "/records.json";
$records = [];

if (file_exists($jsonFile)) {
    $content = file_get_contents($jsonFile);
    $records = json_decode($content, true) ?: [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>StudentHub - Stored Registration Records</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

    <header id="header">
        <img src="image/charusatlogo.png" alt="charusat logo">
        CHARUSAT<br>CHAROTAR UNIVERSITY OF SCIENCE AND TECHNOLOGY
        <nav id="navMenu">
            <a href="index.php">Register Form</a>
            <a href="view_records.php">View Records</a>
            <a href="../p-2/html/home.html">StudentHub Portal</a>
        </nav>
    </header>

    <main>
        <div class="table-container">
            <h2>Registered Students List (Stored Records)</h2>
            <p style="text-align: center; color: #003366; font-size: 15px;">
                Total Registered Records: <b><?php echo count($records); ?></b> | Data Source: <code>records.json</code> & <code>records.csv</code>
            </p>

            <div style="text-align: right; margin-bottom: 10px;">
                <a href="index.php" class="btn-link" style="margin-top: 0;">+ New Registration</a>
            </div>

            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Full Name</th>
                        <th>Email Address</th>
                        <th>Mobile Number</th>
                        <th>Course</th>
                        <th>Year</th>
                        <th>Gender</th>
                        <th>Submission Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($records)): ?>
                        <tr>
                            <td colspan="8" style="padding: 20px; color: gray;">No registration records found.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($records as $row): ?>
                            <tr>
                                <td><b>#<?php echo htmlspecialchars($row['id']); ?></b></td>
                                <td><?php echo htmlspecialchars($row['name']); ?></td>
                                <td><?php echo htmlspecialchars($row['email']); ?></td>
                                <td><?php echo htmlspecialchars($row['mobile']); ?></td>
                                <td><?php echo htmlspecialchars($row['course']); ?></td>
                                <td><?php echo htmlspecialchars($row['year']); ?></td>
                                <td><?php echo htmlspecialchars($row['gender']); ?></td>
                                <td><?php echo htmlspecialchars($row['created_at']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>

            <div style="text-align: center; margin-top: 25px;">
                <a href="index.php" class="btn-link">&larr; Back to Registration Form</a>
            </div>
        </div>
    </main>

    <footer>
        MIRAL PATEL||25CE082||STUDENT HUB PORTAL
    </footer>

</body>
</html>

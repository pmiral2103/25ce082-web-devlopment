<?php
require_once "db.php";

$error = "";
$success = "";
$student = null;

$id = isset($_GET['id']) ? intval($_GET['id']) : (isset($_POST['id']) ? intval($_POST['id']) : 0);

if ($id <= 0) {
    header("Location: index.php?err=invalid_id");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $student_id_no = isset($_POST['student_id_no']) ? trim($_POST['student_id_no']) : '';
    $name = isset($_POST['name']) ? trim($_POST['name']) : '';
    $email = isset($_POST['email']) ? trim($_POST['email']) : '';
    $mobile = isset($_POST['mobile']) ? trim($_POST['mobile']) : '';
    $department = isset($_POST['department']) ? trim($_POST['department']) : '';
    $semester = isset($_POST['semester']) ? trim($_POST['semester']) : '4';
    $cgpa = isset($_POST['cgpa']) ? floatval($_POST['cgpa']) : 0.00;
    $status = isset($_POST['status']) && in_array($_POST['status'], ['Active', 'Inactive']) ? $_POST['status'] : 'Active';

    if (empty($student_id_no) || empty($name) || empty($email) || empty($mobile) || empty($department)) {
        $error = "All required fields must be filled.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please provide a valid institutional email.";
    } elseif (!preg_match("/^[0-9]{10}$/", $mobile)) {
        $error = "Mobile number must be exactly 10 digits.";
    } elseif ($cgpa < 0 || $cgpa > 10) {
        $error = "CGPA must be between 0.00 and 10.00.";
    } else {
        $chk = $conn->prepare("SELECT id FROM students WHERE (student_id_no = ? OR email = ?) AND id != ?");
        $chk->bind_param("ssi", $student_id_no, $email, $id);
        $chk->execute();
        $dup_res = $chk->get_result();

        if ($dup_res->num_rows > 0) {
            $error = "Duplicate Entry: Another student already exists with this Student ID or Email.";
        } else {
            $upd = $conn->prepare("UPDATE students SET student_id_no = ?, name = ?, email = ?, mobile = ?, department = ?, semester = ?, cgpa = ?, status = ? WHERE id = ?");
            $upd->bind_param("sssssdssi", $student_id_no, $name, $email, $mobile, $department, $semester, $cgpa, $status, $id);

            if ($upd->execute()) {
                header("Location: index.php?msg=updated");
                exit();
            } else {
                $error = "Update failed: " . $conn->error;
            }
            $upd->close();
        }
        $chk->close();
    }
}

$stmt = $conn->prepare("SELECT * FROM students WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$res = $stmt->get_result();
if ($res->num_rows === 0) {
    header("Location: index.php?err=not_found");
    exit();
}
$student = $res->fetch_assoc();
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>StudentHub - Edit Student Record (Practical 11)</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <header id="header">
        <img src="image/charusatlogo.png" alt="CHARUSAT Logo">
        <div>StudentHub - Student Management System</div>
        <nav>
            <a href="index.php">Manage Students</a>
            <a href="setup_database.php">Setup DB</a>
        </nav>
    </header>

    <main class="main-content" style="max-width: 750px;">
        <section class="container">
            <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid #87CEEB; padding-bottom: 10px; margin-bottom: 15px;">
                <h2 style="margin: 0;">Edit Student: <?php echo htmlspecialchars($student['name']); ?></h2>
                <a href="index.php" class="btn-secondary">&larr; Back to Student List</a>
            </div>

            <?php if (!empty($error)): ?>
                <div class="alert-error">
                    &cross; <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <form action="edit_student.php?id=<?php echo $id; ?>" method="POST">
                <input type="hidden" name="id" value="<?php echo $id; ?>">

                <div class="form-grid">
                    <div class="form-group">
                        <label for="student_id_no">Student ID No <span class="req">*</span></label>
                        <input type="text" id="student_id_no" name="student_id_no" required value="<?php echo htmlspecialchars($student['student_id_no']); ?>">
                    </div>

                    <div class="form-group">
                        <label for="name">Full Name <span class="req">*</span></label>
                        <input type="text" id="name" name="name" required value="<?php echo htmlspecialchars($student['name']); ?>">
                    </div>

                    <div class="form-group">
                        <label for="email">Institutional Email <span class="req">*</span></label>
                        <input type="email" id="email" name="email" required value="<?php echo htmlspecialchars($student['email']); ?>">
                    </div>

                    <div class="form-group">
                        <label for="mobile">Mobile Number <span class="req">*</span></label>
                        <input type="tel" id="mobile" name="mobile" required pattern="[0-9]{10}" maxlength="10" value="<?php echo htmlspecialchars($student['mobile']); ?>">
                    </div>

                    <div class="form-group">
                        <label for="department">Department / Course <span class="req">*</span></label>
                        <select id="department" name="department" required>
                            <option value="Computer Engineering" <?php echo $student['department'] === 'Computer Engineering' ? 'selected' : ''; ?>>Computer Engineering</option>
                            <option value="Information Technology" <?php echo $student['department'] === 'Information Technology' ? 'selected' : ''; ?>>Information Technology</option>
                            <option value="Computer Science" <?php echo $student['department'] === 'Computer Science' ? 'selected' : ''; ?>>Computer Science</option>
                            <option value="Electronics & Communication" <?php echo $student['department'] === 'Electronics & Communication' ? 'selected' : ''; ?>>Electronics &amp; Communication</option>
                            <option value="Mechanical Engineering" <?php echo $student['department'] === 'Mechanical Engineering' ? 'selected' : ''; ?>>Mechanical Engineering</option>
                            <option value="Civil Engineering" <?php echo $student['department'] === 'Civil Engineering' ? 'selected' : ''; ?>>Civil Engineering</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="semester">Current Semester <span class="req">*</span></label>
                        <select id="semester" name="semester" required>
                            <?php for ($s = 1; $s <= 8; $s++): ?>
                                <option value="<?php echo $s; ?>" <?php echo $student['semester'] == $s ? 'selected' : ''; ?>>Semester <?php echo $s; ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="cgpa">CGPA (0.00 - 10.00) <span class="req">*</span></label>
                        <input type="number" step="0.01" min="0" max="10" id="cgpa" name="cgpa" required value="<?php echo htmlspecialchars($student['cgpa']); ?>">
                    </div>

                    <div class="form-group">
                        <label for="status">Record Status (Soft-Delete) <span class="req">*</span></label>
                        <select id="status" name="status" required>
                            <option value="Active" <?php echo $student['status'] === 'Active' ? 'selected' : ''; ?>>Active (Enrolled)</option>
                            <option value="Inactive" <?php echo $student['status'] === 'Inactive' ? 'selected' : ''; ?>>Inactive (Soft-Deleted)</option>
                        </select>
                    </div>
                </div>

                <div class="btn-row" style="margin-top: 25px;">
                    <button type="submit" class="btn-submit" style="flex: 1;">Update Student Record</button>
                    <a href="index.php" class="btn-secondary">Cancel</a>
                </div>
            </form>
        </section>
    </main>

    <footer>
        MIRAL PATEL||25CE082||STUDENT HUB PORTAL
    </footer>
</body>
</html>

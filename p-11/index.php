<?php
require_once "db.php";

$message = "";
$error = "";

if (isset($_GET['msg'])) {
    if ($_GET['msg'] === 'added') $message = "Student record added successfully!";
    elseif ($_GET['msg'] === 'updated') $message = "Student record updated successfully!";
    elseif ($_GET['msg'] === 'soft_deleted') $message = "Student record soft-deleted (status set to Inactive) successfully!";
    elseif ($_GET['msg'] === 'restored') $message = "Student record restored to Active status successfully!";
    elseif ($_GET['msg'] === 'permanently_deleted') $message = "Student record permanently deleted from database!";
}

if (isset($_GET['err'])) {
    if ($_GET['err'] === 'invalid_id') $error = "Invalid student ID provided.";
    elseif ($_GET['err'] === 'not_found') $error = "Student record not found.";
    elseif ($_GET['err'] === 'delete_failed') $error = "Failed to execute delete operation.";
    elseif ($_GET['err'] === 'restore_failed') $error = "Failed to restore student record.";
}

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['action']) && $_POST['action'] === 'add_student') {
    $student_id_no = isset($_POST['student_id_no']) ? trim($_POST['student_id_no']) : '';
    $name = isset($_POST['name']) ? trim($_POST['name']) : '';
    $email = isset($_POST['email']) ? trim($_POST['email']) : '';
    $mobile = isset($_POST['mobile']) ? trim($_POST['mobile']) : '';
    $department = isset($_POST['department']) ? trim($_POST['department']) : '';
    $semester = isset($_POST['semester']) ? trim($_POST['semester']) : '4';
    $cgpa = isset($_POST['cgpa']) ? floatval($_POST['cgpa']) : 0.00;
    $status = isset($_POST['status']) ? $_POST['status'] : 'Active';

    if (empty($student_id_no) || empty($name) || empty($email) || empty($mobile) || empty($department)) {
        $error = "Please fill in all required fields.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please provide a valid institutional email.";
    } elseif (!preg_match("/^[0-9]{10}$/", $mobile)) {
        $error = "Mobile number must be exactly 10 digits.";
    } elseif ($cgpa < 0 || $cgpa > 10) {
        $error = "CGPA must be between 0.00 and 10.00.";
    } else {
        $chk = $conn->prepare("SELECT id FROM students WHERE student_id_no = ? OR email = ?");
        $chk->bind_param("ss", $student_id_no, $email);
        $chk->execute();
        $res_chk = $chk->get_result();

        if ($res_chk->num_rows > 0) {
            $error = "Duplicate Entry: A student with this Student ID or Email already exists.";
        } else {
            $ins = $conn->prepare("INSERT INTO students (student_id_no, name, email, mobile, department, semester, cgpa, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $ins->bind_param("ssssssds", $student_id_no, $name, $email, $mobile, $department, $semester, $cgpa, $status);
            if ($ins->execute()) {
                $message = "New student '{$name}' ({$student_id_no}) registered successfully!";
            } else {
                $error = "Failed to add student: " . $conn->error;
            }
            $ins->close();
        }
        $chk->close();
    }
}

$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$department = isset($_GET['department']) ? trim($_GET['department']) : '';
$status = isset($_GET['status']) ? trim($_GET['status']) : '';

$sql = "SELECT id, student_id_no, name, email, mobile, department, semester, cgpa, status, created_at FROM students WHERE 1=1";
$params = [];
$types = "";

if (!empty($search)) {
    $sql .= " AND (name LIKE ? OR email LIKE ? OR student_id_no LIKE ? OR department LIKE ?)";
    $like = "%" . $search . "%";
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $types .= "ssss";
}

if (!empty($department)) {
    $sql .= " AND department = ?";
    $params[] = $department;
    $types .= "s";
}

if (!empty($status)) {
    $sql .= " AND status = ?";
    $params[] = $status;
    $types .= "s";
}

$sql .= " ORDER BY id DESC";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$students_result = $stmt->get_result();
$students = [];
while ($row = $students_result->fetch_assoc()) {
    $students[] = $row;
}
$stmt->close();

$res_all = $conn->query("SELECT COUNT(*) AS total FROM students");
$count_total = $res_all ? $res_all->fetch_assoc()['total'] : 0;

$res_active = $conn->query("SELECT COUNT(*) AS total FROM students WHERE status = 'Active'");
$count_active = $res_active ? $res_active->fetch_assoc()['total'] : 0;

$res_inactive = $conn->query("SELECT COUNT(*) AS total FROM students WHERE status = 'Inactive'");
$count_inactive = $res_inactive ? $res_inactive->fetch_assoc()['total'] : 0;

$export_query = http_build_query([
    'search' => $search,
    'department' => $department,
    'status' => $status
]);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>StudentHub - Student Management CRUD (Practical 11)</title>
    <link rel="stylesheet" href="style.css">
    <script>
        function openAddModal() {
            document.getElementById("addStudentModal").style.display = "block";
        }
        function closeAddModal() {
            document.getElementById("addStudentModal").style.display = "none";
        }
        window.onclick = function(event) {
            var modal = document.getElementById("addStudentModal");
            if (event.target == modal) {
                modal.style.display = "none";
            }
        }
    </script>
</head>
<body>
    <header id="header">
        <img src="image/charusatlogo.png" alt="CHARUSAT Logo">
        <div>StudentHub - Student Management System</div>
        <nav>
            <a href="index.php" class="active">Manage Students</a>
            <a href="setup_database.php">Setup DB</a>
        </nav>
    </header>

    <main class="main-content">
        <section class="container">
            <h2>Practical 11: Student Management Module (CRUD, Search &amp; Filter)</h2>
            <div class="info-box">
                Features: Full CRUD with MySQLi Prepared Statements, Multi-field Search (Name, Email, Student ID, Course), Department &amp; Status Filtering, Intermediate CSV Export, and Advanced Soft-Delete with Confirmation.
            </div>

            <?php if (!empty($message)): ?>
                <div class="alert-success">
                    &check; <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($error)): ?>
                <div class="alert-error">
                    &cross; <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-label">Total Students</div>
                    <div class="stat-number"><?php echo $count_total; ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Active Enrolled</div>
                    <div class="stat-number" style="color: #10b981;"><?php echo $count_active; ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Soft-Deleted (Inactive)</div>
                    <div class="stat-number" style="color: #ef4444;"><?php echo $count_inactive; ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Matching Query Results</div>
                    <div class="stat-number" style="color: #2563eb;"><?php echo count($students); ?></div>
                </div>
            </div>

            <div class="search-filter-card">
                <form action="index.php" method="GET" class="search-filter-form">
                    <input type="text" name="search" class="search-input" placeholder="Search by Name, Email, Student ID, or Course..." value="<?php echo htmlspecialchars($search); ?>">

                    <select name="department" class="filter-select">
                        <option value="">All Departments / Courses</option>
                        <option value="Computer Engineering" <?php echo $department === 'Computer Engineering' ? 'selected' : ''; ?>>Computer Engineering</option>
                        <option value="Information Technology" <?php echo $department === 'Information Technology' ? 'selected' : ''; ?>>Information Technology</option>
                        <option value="Computer Science" <?php echo $department === 'Computer Science' ? 'selected' : ''; ?>>Computer Science</option>
                        <option value="Electronics & Communication" <?php echo $department === 'Electronics & Communication' ? 'selected' : ''; ?>>Electronics &amp; Communication</option>
                        <option value="Mechanical Engineering" <?php echo $department === 'Mechanical Engineering' ? 'selected' : ''; ?>>Mechanical Engineering</option>
                        <option value="Civil Engineering" <?php echo $department === 'Civil Engineering' ? 'selected' : ''; ?>>Civil Engineering</option>
                    </select>

                    <select name="status" class="filter-select" style="min-width: 140px;">
                        <option value="">All Statuses</option>
                        <option value="Active" <?php echo $status === 'Active' ? 'selected' : ''; ?>>Active Only</option>
                        <option value="Inactive" <?php echo $status === 'Inactive' ? 'selected' : ''; ?>>Soft-Deleted (Inactive)</option>
                    </select>

                    <button type="submit" class="btn-primary">Search &amp; Filter</button>
                    <a href="index.php" class="btn-secondary">Reset</a>
                </form>
            </div>

            <div class="action-bar">
                <div>
                    <button type="button" class="btn-add" onclick="openAddModal();">+ Add New Student</button>
                    <a href="export_csv.php?<?php echo $export_query; ?>" class="btn-export">&darr; Export to CSV (Intermediate)</a>
                </div>
                <div style="font-size: 13px; color: #475569;">
                    Showing <strong><?php echo count($students); ?></strong> student records
                </div>
            </div>

            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Student ID</th>
                            <th>Full Name</th>
                            <th>Email Address</th>
                            <th>Mobile</th>
                            <th>Department</th>
                            <th>Sem</th>
                            <th>CGPA</th>
                            <th>Status (Soft-Delete)</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($students)): ?>
                            <tr>
                                <td colspan="10" style="padding: 25px; color: #64748b; font-size: 14px;">
                                    No student records matched your search or filter criteria. Click "Reset" to view all records.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($students as $s): ?>
                                <tr class="<?php echo $s['status'] === 'Inactive' ? 'soft-deleted' : ''; ?>">
                                    <td><?php echo $s['id']; ?></td>
                                    <td><strong><?php echo htmlspecialchars($s['student_id_no']); ?></strong></td>
                                    <td style="text-align: left; font-weight: bold;"><?php echo htmlspecialchars($s['name']); ?></td>
                                    <td style="text-align: left;"><?php echo htmlspecialchars($s['email']); ?></td>
                                    <td><?php echo htmlspecialchars($s['mobile']); ?></td>
                                    <td>
                                        <span class="badge badge-dept"><?php echo htmlspecialchars($s['department']); ?></span>
                                    </td>
                                    <td>Sem <?php echo htmlspecialchars($s['semester']); ?></td>
                                    <td><strong><?php echo number_format($s['cgpa'], 2); ?></strong></td>
                                    <td>
                                        <?php if ($s['status'] === 'Active'): ?>
                                            <span class="badge badge-active">Active</span>
                                        <?php else: ?>
                                            <span class="badge badge-inactive">Inactive (Soft-Deleted)</span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="white-space: nowrap;">
                                        <a href="edit_student.php?id=<?php echo $s['id']; ?>" class="btn-edit">Edit</a>

                                        <?php if ($s['status'] === 'Active'): ?>
                                            <a href="delete_student.php?id=<?php echo $s['id']; ?>&amp;action=soft_delete" class="btn-delete" onclick="return confirm('Are you sure you want to SOFT-DELETE student <?php echo htmlspecialchars(addslashes($s['name'])); ?>? (Status will be set to Inactive)');">Soft Delete</a>
                                        <?php else: ?>
                                            <a href="delete_student.php?id=<?php echo $s['id']; ?>&amp;action=restore" class="btn-restore" onclick="return confirm('Restore student <?php echo htmlspecialchars(addslashes($s['name'])); ?> back to Active status?');">Restore</a>
                                        <?php endif; ?>

                                        <a href="delete_student.php?id=<?php echo $s['id']; ?>&amp;action=permanent" class="btn-delete" style="background-color: #991b1b;" onclick="return confirm('PERMANENT DELETE: Are you completely sure you want to remove <?php echo htmlspecialchars(addslashes($s['name'])); ?> from database?');">Hard Delete</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </main>

    <div id="addStudentModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 style="margin: 0; color: #1E3A8A;">Add New Student (CREATE)</h3>
                <button type="button" class="close-btn" onclick="closeAddModal();">&times;</button>
            </div>

            <form action="index.php" method="POST">
                <input type="hidden" name="action" value="add_student">

                <div class="form-grid">
                    <div class="form-group">
                        <label for="m_student_id_no">Student ID No <span class="req">*</span></label>
                        <input type="text" id="m_student_id_no" name="student_id_no" required placeholder="e.g. 25CE105">
                    </div>

                    <div class="form-group">
                        <label for="m_name">Full Name <span class="req">*</span></label>
                        <input type="text" id="m_name" name="name" required placeholder="e.g. Rahul Sharma">
                    </div>

                    <div class="form-group">
                        <label for="m_email">Institutional Email <span class="req">*</span></label>
                        <input type="email" id="m_email" name="email" required placeholder="e.g. rahul@charusat.edu.in">
                    </div>

                    <div class="form-group">
                        <label for="m_mobile">Mobile Number <span class="req">*</span></label>
                        <input type="tel" id="m_mobile" name="mobile" required pattern="[0-9]{10}" maxlength="10" placeholder="10-digit number">
                    </div>

                    <div class="form-group">
                        <label for="m_department">Department / Course <span class="req">*</span></label>
                        <select id="m_department" name="department" required>
                            <option value="Computer Engineering">Computer Engineering</option>
                            <option value="Information Technology">Information Technology</option>
                            <option value="Computer Science">Computer Science</option>
                            <option value="Electronics & Communication">Electronics &amp; Communication</option>
                            <option value="Mechanical Engineering">Mechanical Engineering</option>
                            <option value="Civil Engineering">Civil Engineering</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="m_semester">Semester <span class="req">*</span></label>
                        <select id="m_semester" name="semester" required>
                            <?php for ($sem = 1; $sem <= 8; $sem++): ?>
                                <option value="<?php echo $sem; ?>" <?php echo $sem == 4 ? 'selected' : ''; ?>>Semester <?php echo $sem; ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="m_cgpa">CGPA (0.00 - 10.00) <span class="req">*</span></label>
                        <input type="number" step="0.01" min="0" max="10" id="m_cgpa" name="cgpa" required value="8.50">
                    </div>

                    <div class="form-group">
                        <label for="m_status">Status <span class="req">*</span></label>
                        <select id="m_status" name="status" required>
                            <option value="Active">Active</option>
                            <option value="Inactive">Inactive</option>
                        </select>
                    </div>
                </div>

                <div class="btn-row" style="margin-top: 25px;">
                    <button type="submit" class="btn-submit" style="flex: 1;">Save Student Record</button>
                    <button type="button" class="btn-secondary" onclick="closeAddModal();">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <footer>
        MIRAL PATEL||25CE082||STUDENT HUB PORTAL
    </footer>
</body>
</html>

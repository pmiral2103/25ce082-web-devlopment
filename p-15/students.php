<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth_helper.php';

require_login($conn);

$is_admin = ($_SESSION['role'] === 'Admin');
$message = '';
$message_type = 'info';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $is_admin) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $message = "Security validation failed. Please try again.";
        $message_type = "danger";
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'create') {
            $student_id_no = trim($_POST['student_id_no'] ?? '');
            $name = trim($_POST['name'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $mobile = trim($_POST['mobile'] ?? '');
            $department = trim($_POST['department'] ?? 'CE');
            $semester = trim($_POST['semester'] ?? '4');
            $cgpa = (float)($_POST['cgpa'] ?? 8.00);

            if (empty($student_id_no) || empty($name) || empty($email) || empty($mobile)) {
                $message = "All student details (Student ID, Name, Email, Mobile) are required.";
                $message_type = "danger";
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $message = "Invalid email format.";
                $message_type = "danger";
            } else {
                $chk = $conn->prepare("SELECT id FROM students WHERE student_id_no = ? OR email = ? LIMIT 1");
                $chk->bind_param("ss", $student_id_no, $email);
                $chk->execute();
                if ($chk->get_result()->num_rows > 0) {
                    $message = "A student with this Student ID or Email already exists.";
                    $message_type = "warning";
                } else {
                    $stmt = $conn->prepare("INSERT INTO students (student_id_no, name, email, mobile, department, semester, cgpa, status) VALUES (?, ?, ?, ?, ?, ?, ?, 'Active')");
                    $stmt->bind_param("ssssssd", $student_id_no, $name, $email, $mobile, $department, $semester, $cgpa);
                    if ($stmt->execute()) {
                        $message = "Student profile created successfully.";
                        $message_type = "success";
                        log_audit($conn, $_SESSION['user_id'], $_SESSION['username'], "STUDENT_CREATE_$student_id_no", 'SUCCESS');
                    } else {
                        $message = "Database error: " . $stmt->error;
                        $message_type = "danger";
                    }
                    $stmt->close();
                }
                $chk->close();
            }
        } elseif ($action === 'update') {
            $id = (int)($_POST['student_id'] ?? 0);
            $student_id_no = trim($_POST['student_id_no'] ?? '');
            $name = trim($_POST['name'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $mobile = trim($_POST['mobile'] ?? '');
            $department = trim($_POST['department'] ?? 'CE');
            $semester = trim($_POST['semester'] ?? '4');
            $cgpa = (float)($_POST['cgpa'] ?? 8.00);
            $status = trim($_POST['status'] ?? 'Active');

            if ($id > 0 && !empty($student_id_no) && !empty($name) && !empty($email)) {
                $stmt = $conn->prepare("UPDATE students SET student_id_no = ?, name = ?, email = ?, mobile = ?, department = ?, semester = ?, cgpa = ?, status = ? WHERE id = ?");
                $stmt->bind_param("ssssssdsi", $student_id_no, $name, $email, $mobile, $department, $semester, $cgpa, $status, $id);
                if ($stmt->execute()) {
                    $message = "Student updated successfully.";
                    $message_type = "success";
                    log_audit($conn, $_SESSION['user_id'], $_SESSION['username'], "STUDENT_UPDATE_$id", 'SUCCESS');
                } else {
                    $message = "Update failed: " . $stmt->error;
                    $message_type = "danger";
                }
                $stmt->close();
            }
        } elseif ($action === 'soft_delete') {
            $id = (int)($_POST['student_id'] ?? 0);
            if ($id > 0) {
                $stmt = $conn->prepare("UPDATE students SET status = 'Inactive' WHERE id = ?");
                $stmt->bind_param("i", $id);
                if ($stmt->execute()) {
                    $message = "Student record marked as Inactive (Soft Deleted).";
                    $message_type = "warning";
                    log_audit($conn, $_SESSION['user_id'], $_SESSION['username'], "STUDENT_SOFT_DELETE_$id", 'SUCCESS');
                }
                $stmt->close();
            }
        } elseif ($action === 'restore') {
            $id = (int)($_POST['student_id'] ?? 0);
            if ($id > 0) {
                $stmt = $conn->prepare("UPDATE students SET status = 'Active' WHERE id = ?");
                $stmt->bind_param("i", $id);
                if ($stmt->execute()) {
                    $message = "Student record restored to Active status.";
                    $message_type = "success";
                    log_audit($conn, $_SESSION['user_id'], $_SESSION['username'], "STUDENT_RESTORE_$id", 'SUCCESS');
                }
                $stmt->close();
            }
        }
    }
}

$search = trim($_GET['search'] ?? '');
$dept_filter = trim($_GET['department'] ?? 'all');
$status_filter = trim($_GET['status'] ?? 'all');

$where_clauses = ["1=1"];
$params = [];
$types = "";

if (!empty($search)) {
    $where_clauses[] = "(name LIKE ? OR email LIKE ? OR student_id_no LIKE ? OR mobile LIKE ?)";
    $like = "%$search%";
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $types .= "ssss";
}

if ($dept_filter !== 'all' && !empty($dept_filter)) {
    $where_clauses[] = "department = ?";
    $params[] = $dept_filter;
    $types .= "s";
}

if ($status_filter !== 'all' && !empty($status_filter)) {
    $where_clauses[] = "status = ?";
    $params[] = $status_filter;
    $types .= "s";
}

$where_sql = implode(" AND ", $where_clauses);
$sql = "SELECT id, student_id_no, name, email, mobile, department, semester, cgpa, status, created_at FROM students WHERE $where_sql ORDER BY id DESC";

$students = [];
if ($conn) {
    if (!empty($params)) {
        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) $students[] = $row;
        $stmt->close();
    } else {
        $res = $conn->query($sql);
        if ($res) while ($row = $res->fetch_assoc()) $students[] = $row;
    }
}

$csrf_token = get_csrf_token();
$nav_items = get_dynamic_navigation('students.php');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Management - StudentHub</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <a href="#main-content" class="skip-link">Skip to main content</a>
    <header class="app-header shadow-sm">
        <div class="container py-2">
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                <div class="d-flex align-items-center gap-3">
                    <img src="image/charusatlogo.png" alt="CHARUSAT Logo" class="brand-logo" onerror="this.style.display='none'">
                    <div>
                        <h1 class="brand-title mb-0">StudentHub Portal</h1>
                        <p class="brand-subtitle mb-0">CSPIT / CHARUSAT - 25CE082 Final Integration</p>
                    </div>
                </div>
                <div class="text-end">
                    <span class="badge bg-light text-dark px-3 py-2 me-2">
                        <i class="bi bi-person-circle"></i> <?= htmlspecialchars($_SESSION['full_name'] ?? $_SESSION['username']) ?> (<?= htmlspecialchars($_SESSION['role']) ?>)
                    </span>
                    <a href="logout.php" class="btn btn-sm btn-outline-light">Logout</a>
                </div>
            </div>
        </div>
    </header>

    <nav class="app-nav" aria-label="Main Navigation">
        <div class="container d-flex flex-wrap gap-2 py-1">
            <?php foreach ($nav_items as $item): ?>
                <a href="<?= htmlspecialchars($item['url']) ?>" class="nav-item <?= $item['url'] === 'students.php' ? 'active' : '' ?>">
                    <i class="bi bi-<?= htmlspecialchars($item['icon']) ?>"></i> <?= htmlspecialchars($item['label']) ?>
                </a>
            <?php endforeach; ?>
        </div>
    </nav>

    <main id="main-content" class="container py-4 flex-grow-1">
        <?php if (!empty($message)): ?>
            <div class="alert alert-<?= $message_type ?> alert-dismissible fade show" role="alert">
                <i class="bi bi-info-circle-fill me-2"></i><?= htmlspecialchars($message) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
            <div>
                <h2 class="page-title mb-1">Student Management System</h2>
                <p class="text-muted mb-0">Complete CRUD operations with parameterized queries, search, filter, and CSV export (P-11).</p>
            </div>
            <div class="d-flex gap-2">
                <a href="export_csv.php" class="btn btn-outline-success">
                    <i class="bi bi-file-earmark-spreadsheet me-1"></i> Export to CSV
                </a>
                <?php if ($is_admin): ?>
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addStudentModal">
                        <i class="bi bi-person-plus-fill me-1"></i> Add New Student
                    </button>
                <?php endif; ?>
            </div>
        </div>

        <div class="card shadow-sm mb-4">
            <div class="card-body">
                <form method="GET" action="students.php" class="row g-2 align-items-center">
                    <div class="col-md-5">
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-search"></i></span>
                            <input type="text" name="search" class="form-control" placeholder="Search by name, student ID, email, or mobile..." value="<?= htmlspecialchars($search) ?>">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <select name="department" class="form-select">
                            <option value="all" <?= $dept_filter === 'all' ? 'selected' : '' ?>>All Departments</option>
                            <option value="CE" <?= $dept_filter === 'CE' ? 'selected' : '' ?>>Computer Engineering (CE)</option>
                            <option value="IT" <?= $dept_filter === 'IT' ? 'selected' : '' ?>>Information Technology (IT)</option>
                            <option value="CSE" <?= $dept_filter === 'CSE' ? 'selected' : '' ?>>Computer Science & Engg (CSE)</option>
                            <option value="EC" <?= $dept_filter === 'EC' ? 'selected' : '' ?>>Electronics & Comm (EC)</option>
                            <option value="ME" <?= $dept_filter === 'ME' ? 'selected' : '' ?>>Mechanical Engineering (ME)</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <select name="status" class="form-select">
                            <option value="all" <?= $status_filter === 'all' ? 'selected' : '' ?>>All Statuses</option>
                            <option value="Active" <?= $status_filter === 'Active' ? 'selected' : '' ?>>Active</option>
                            <option value="Inactive" <?= $status_filter === 'Inactive' ? 'selected' : '' ?>>Inactive (Trash)</option>
                        </select>
                    </div>
                    <div class="col-md-2 d-flex gap-2">
                        <button type="submit" class="btn btn-primary w-100"><i class="bi bi-funnel"></i> Filter</button>
                        <a href="students.php" class="btn btn-outline-secondary" title="Reset Filters"><i class="bi bi-arrow-counterclockwise"></i></a>
                    </div>
                </form>
            </div>
        </div>

        <div class="card shadow-sm">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <h5 class="mb-0 fw-bold text-dark">Students Directory</h5>
                <span class="badge bg-primary rounded-pill"><?= count($students) ?> Records</span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>#</th>
                                <th>Student ID</th>
                                <th>Full Name</th>
                                <th>Email</th>
                                <th>Mobile</th>
                                <th>Dept</th>
                                <th>Sem</th>
                                <th>CGPA</th>
                                <th>Status</th>
                                <?php if ($is_admin): ?>
                                    <th class="text-end">Actions</th>
                                <?php endif; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($students)): ?>
                                <tr>
                                    <td colspan="<?= $is_admin ? 10 : 9 ?>" class="text-center py-5 text-muted">
                                        <i class="bi bi-inbox display-6 d-block mb-2"></i>
                                        No matching student records found.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($students as $st): ?>
                                    <tr>
                                        <td><?= (int)$st['id'] ?></td>
                                        <td class="font-monospace fw-bold text-primary"><?= htmlspecialchars($st['student_id_no']) ?></td>
                                        <td><strong><?= htmlspecialchars($st['name']) ?></strong></td>
                                        <td><?= htmlspecialchars($st['email']) ?></td>
                                        <td><?= htmlspecialchars($st['mobile']) ?></td>
                                        <td><span class="badge bg-secondary"><?= htmlspecialchars($st['department']) ?></span></td>
                                        <td>Sem <?= htmlspecialchars($st['semester']) ?></td>
                                        <td><span class="badge bg-info text-dark"><?= number_format((float)$st['cgpa'], 2) ?></span></td>
                                        <td>
                                            <span class="badge bg-<?= $st['status'] === 'Active' ? 'success' : 'secondary' ?>">
                                                <?= htmlspecialchars($st['status']) ?>
                                            </span>
                                        </td>
                                        <?php if ($is_admin): ?>
                                            <td class="text-end">
                                                <button type="button" class="btn btn-sm btn-outline-primary me-1" onclick='openEditModal(<?= json_encode($st) ?>)'>
                                                    <i class="bi bi-pencil"></i>
                                                </button>
                                                <?php if ($st['status'] === 'Active'): ?>
                                                    <form method="POST" action="students.php" class="d-inline" onsubmit="return confirm('Soft-delete this student record?');">
                                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                                                        <input type="hidden" name="action" value="soft_delete">
                                                        <input type="hidden" name="student_id" value="<?= (int)$st['id'] ?>">
                                                        <button type="submit" class="btn btn-sm btn-outline-danger" title="Soft Delete">
                                                            <i class="bi bi-trash"></i>
                                                        </button>
                                                    </form>
                                                <?php else: ?>
                                                    <form method="POST" action="students.php" class="d-inline">
                                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                                                        <input type="hidden" name="action" value="restore">
                                                        <input type="hidden" name="student_id" value="<?= (int)$st['id'] ?>">
                                                        <button type="submit" class="btn btn-sm btn-success" title="Restore Record">
                                                            <i class="bi bi-arrow-counterclockwise"></i> Restore
                                                        </button>
                                                    </form>
                                                <?php endif; ?>
                                            </td>
                                        <?php endif; ?>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>

    <?php if ($is_admin): ?>
    <div class="modal fade" id="addStudentModal" tabindex="-1" aria-labelledby="addStudentModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" action="students.php">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                    <input type="hidden" name="action" value="create">
                    <div class="modal-header bg-primary text-white">
                        <h5 class="modal-title" id="addStudentModalLabel"><i class="bi bi-person-plus-fill me-2"></i>Add New Student</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Student ID / Enrollment Number</label>
                            <input type="text" name="student_id_no" class="form-control" placeholder="e.g. 25CE099" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Student Name</label>
                            <input type="text" name="name" class="form-control" placeholder="e.g. John Doe" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Email Address</label>
                            <input type="email" name="email" class="form-control" placeholder="e.g. student@charusat.edu.in" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Mobile Number</label>
                            <input type="text" name="mobile" class="form-control" placeholder="e.g. 9876543210" required pattern="^[0-9]{10}$">
                        </div>
                        <div class="row g-2">
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-semibold">Department</label>
                                <select name="department" class="form-select" required>
                                    <option value="CE">Computer Engineering (CE)</option>
                                    <option value="IT">Information Technology (IT)</option>
                                    <option value="CSE">Computer Science & Engg (CSE)</option>
                                    <option value="EC">Electronics & Comm (EC)</option>
                                    <option value="ME">Mechanical Engineering (ME)</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-semibold">Semester</label>
                                <select name="semester" class="form-select" required>
                                    <option value="1">Sem 1</option>
                                    <option value="2">Sem 2</option>
                                    <option value="3">Sem 3</option>
                                    <option value="4" selected>Sem 4</option>
                                    <option value="5">Sem 5</option>
                                    <option value="6">Sem 6</option>
                                    <option value="7">Sem 7</option>
                                    <option value="8">Sem 8</option>
                                </select>
                            </div>
                            <div class="col-12 mb-3">
                                <label class="form-label fw-semibold">CGPA</label>
                                <input type="number" step="0.01" min="0" max="10" name="cgpa" class="form-control" value="8.50" required>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i> Save Student</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="editStudentModal" tabindex="-1" aria-labelledby="editStudentModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" action="students.php">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                    <input type="hidden" name="action" value="update">
                    <input type="hidden" name="student_id" id="edit_student_id">
                    <div class="modal-header bg-dark text-white">
                        <h5 class="modal-title" id="editStudentModalLabel"><i class="bi bi-pencil-square me-2"></i>Edit Student Profile</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Student ID / Enrollment Number</label>
                            <input type="text" name="student_id_no" id="edit_student_id_no" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Student Name</label>
                            <input type="text" name="name" id="edit_name" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Email Address</label>
                            <input type="email" name="email" id="edit_email" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Mobile Number</label>
                            <input type="text" name="mobile" id="edit_mobile" class="form-control" required pattern="^[0-9]{10}$">
                        </div>
                        <div class="row g-2">
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-semibold">Department</label>
                                <select name="department" id="edit_department" class="form-select" required>
                                    <option value="CE">Computer Engineering (CE)</option>
                                    <option value="IT">Information Technology (IT)</option>
                                    <option value="CSE">Computer Science & Engg (CSE)</option>
                                    <option value="EC">Electronics & Comm (EC)</option>
                                    <option value="ME">Mechanical Engineering (ME)</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-semibold">Semester</label>
                                <select name="semester" id="edit_semester" class="form-select" required>
                                    <option value="1">Sem 1</option>
                                    <option value="2">Sem 2</option>
                                    <option value="3">Sem 3</option>
                                    <option value="4">Sem 4</option>
                                    <option value="5">Sem 5</option>
                                    <option value="6">Sem 6</option>
                                    <option value="7">Sem 7</option>
                                    <option value="8">Sem 8</option>
                                </select>
                            </div>
                        </div>
                        <div class="row g-2">
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-semibold">CGPA</label>
                                <input type="number" step="0.01" min="0" max="10" name="cgpa" id="edit_cgpa" class="form-control" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-semibold">Status</label>
                                <select name="status" id="edit_status" class="form-select">
                                    <option value="Active">Active</option>
                                    <option value="Inactive">Inactive</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary"><i class="bi bi-check2-circle me-1"></i> Update Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <footer class="app-footer text-center py-3">
        <div class="container">
            <p class="mb-1 text-light fw-bold">MIRAL PATEL || 25CE082 || STUDENT HUB PORTAL</p>
            <p class="mb-0 text-white-50 small">&copy; <?= date('Y') ?> CSPIT / CHARUSAT. Practical 15 Final Integration & Deployment.</p>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <?php if ($is_admin): ?>
    <script>
    function openEditModal(st) {
        document.getElementById('edit_student_id').value = st.id;
        document.getElementById('edit_student_id_no').value = st.student_id_no;
        document.getElementById('edit_name').value = st.name;
        document.getElementById('edit_email').value = st.email;
        document.getElementById('edit_mobile').value = st.mobile;
        document.getElementById('edit_department').value = st.department;
        document.getElementById('edit_semester').value = st.semester;
        document.getElementById('edit_cgpa').value = st.cgpa;
        document.getElementById('edit_status').value = st.status;
        new bootstrap.Modal(document.getElementById('editStudentModal')).show();
    }
    </script>
    <?php endif; ?>
</body>
</html>

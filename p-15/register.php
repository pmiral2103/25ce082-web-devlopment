<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth_helper.php';

check_remember_token($conn);

if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit();
}

$error = '';
$success = '';

$full_name = '';
$username_val = '';
$email_val = '';
$mobile_val = '';
$dept_val = 'CE';
$student_id_val = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = "Security validation token expired. Please try again.";
    } else {
        $full_name = trim($_POST['full_name'] ?? '');
        $username_val = trim($_POST['username'] ?? '');
        $email_val = trim($_POST['email'] ?? '');
        $mobile_val = trim($_POST['mobile'] ?? '');
        $dept_val = trim($_POST['department'] ?? 'CE');
        $student_id_val = trim($_POST['student_id_no'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';

        if (empty($full_name) || empty($username_val) || empty($email_val) || empty($mobile_val) || empty($password)) {
            $error = "All required fields marked with * must be filled.";
        } elseif (strlen($username_val) < 3 || strlen($username_val) > 30 || !preg_match('/^[a-zA-Z0-9_]+$/', $username_val)) {
            $error = "Username must be 3-30 alphanumeric characters or underscores.";
        } elseif (!filter_var($email_val, FILTER_VALIDATE_EMAIL)) {
            $error = "Invalid email address format.";
        } elseif (!preg_match('/^[0-9]{10}$/', $mobile_val)) {
            $error = "Mobile number must be exactly 10 digits.";
        } elseif (strlen($password) < 8) {
            $error = "Password must be at least 8 characters long.";
        } elseif (!preg_match('/[A-Z]/', $password) || !preg_match('/[a-z]/', $password) || !preg_match('/[0-9]/', $password)) {
            $error = "Password must include at least one uppercase letter, one lowercase letter, and one number.";
        } elseif ($password !== $confirm_password) {
            $error = "Passwords do not match.";
        } elseif (!$conn) {
            $error = "Database unavailable. Please visit setup to initialize.";
        } else {
            $check_stmt = $conn->prepare("SELECT id FROM users WHERE username = ? OR email = ? LIMIT 1");
            if ($check_stmt) {
                $check_stmt->bind_param("ss", $username_val, $email_val);
                $check_stmt->execute();
                $check_res = $check_stmt->get_result();
                if ($check_res->num_rows > 0) {
                    $error = "An account with that username or email already exists.";
                    log_audit($conn, null, $username_val, 'USER_REGISTRATION', 'FAILED_DUPLICATE');
                } else {
                    $password_hash = password_hash($password, PASSWORD_BCRYPT);
                    $role = 'Student';
                    $status = 'Active';

                    $ins_user = $conn->prepare("INSERT INTO users (username, email, password, full_name, mobile, role, status) VALUES (?, ?, ?, ?, ?, ?, ?)");
                    if ($ins_user) {
                        $ins_user->bind_param("sssssss", $username_val, $email_val, $password_hash, $full_name, $mobile_val, $role, $status);
                        if ($ins_user->execute()) {
                            $new_uid = $ins_user->insert_id;

                            if (empty($student_id_val)) {
                                $student_id_val = '25CE' . str_pad((string)$new_uid, 3, '0', STR_PAD_LEFT);
                            }

                            $ins_student = $conn->prepare("INSERT INTO students (student_id_no, name, email, mobile, department, semester, cgpa, status) VALUES (?, ?, ?, ?, ?, '4', 8.50, 'Active')");
                            if ($ins_student) {
                                $ins_student->bind_param("sssss", $student_id_val, $full_name, $email_val, $mobile_val, $dept_val);
                                $ins_student->execute();
                                $ins_student->close();
                            }

                            log_audit($conn, $new_uid, $username_val, 'USER_REGISTRATION', 'SUCCESS');
                            $success = "Registration successful! You may now sign in with your credentials.";
                            $full_name = '';
                            $username_val = '';
                            $email_val = '';
                            $mobile_val = '';
                            $student_id_val = '';
                        } else {
                            $error = "Database insertion error: " . $ins_user->error;
                        }
                        $ins_user->close();
                    }
                }
                $check_stmt->close();
            }
        }
    }
}

$csrf_token = get_csrf_token();
$nav_items = get_dynamic_navigation('register.php');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Registration - StudentHub</title>
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
                <div>
                    <a href="login.php" class="btn btn-sm btn-light text-primary me-2"><i class="bi bi-box-arrow-in-right"></i> Login</a>
                    <a href="setup.php" class="btn btn-sm btn-outline-light"><i class="bi bi-wrench"></i> Setup</a>
                </div>
            </div>
        </div>
    </header>

    <nav class="app-nav" aria-label="Main Navigation">
        <div class="container d-flex flex-wrap gap-2 py-1">
            <?php foreach ($nav_items as $item): ?>
                <a href="<?= htmlspecialchars($item['url']) ?>" class="nav-item <?= $item['url'] === 'register.php' ? 'active' : '' ?>">
                    <i class="bi bi-<?= htmlspecialchars($item['icon']) ?>"></i> <?= htmlspecialchars($item['label']) ?>
                </a>
            <?php endforeach; ?>
        </div>
    </nav>

    <main id="main-content" class="container py-5 flex-grow-1">
        <div class="row justify-content-center">
            <div class="col-md-9 col-lg-7">
                <div class="card shadow-sm border-0">
                    <div class="card-header bg-primary text-white text-center py-4">
                        <i class="bi bi-person-plus-fill display-5 mb-2"></i>
                        <h4 class="mb-0 fw-bold">Student Account Registration</h4>
                        <p class="small text-white-50 mb-0">Validated Secure Registration with Password Hashing (P-9)</p>
                    </div>
                    <div class="card-body p-4 p-md-5">
                        <?php if (!empty($error)): ?>
                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                <i class="bi bi-exclamation-triangle-fill me-2"></i><?= htmlspecialchars($error) ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($success)): ?>
                            <div class="alert alert-success alert-dismissible fade show" role="alert">
                                <i class="bi bi-check-circle-fill me-2"></i><?= htmlspecialchars($success) ?>
                                <div class="mt-2">
                                    <a href="login.php" class="btn btn-sm btn-success fw-bold">Click here to Sign In</a>
                                </div>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>

                        <form method="POST" action="register.php" id="regForm" novalidate>
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">

                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label for="full_name" class="form-label fw-semibold">Full Name <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="full_name" name="full_name" value="<?= htmlspecialchars($full_name) ?>" placeholder="e.g. Miral Patel" required>
                                </div>

                                <div class="col-md-6">
                                    <label for="username" class="form-label fw-semibold">Username <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="username" name="username" value="<?= htmlspecialchars($username_val) ?>" placeholder="e.g. miral082" required pattern="^[a-zA-Z0-9_]{3,30}$">
                                    <div class="form-text small">3-30 alphanumeric characters or underscores.</div>
                                </div>

                                <div class="col-md-6">
                                    <label for="email" class="form-label fw-semibold">Email Address <span class="text-danger">*</span></label>
                                    <input type="email" class="form-control" id="email" name="email" value="<?= htmlspecialchars($email_val) ?>" placeholder="student@charusat.edu.in" required>
                                </div>

                                <div class="col-md-6">
                                    <label for="mobile" class="form-label fw-semibold">Mobile Number <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="mobile" name="mobile" value="<?= htmlspecialchars($mobile_val) ?>" placeholder="e.g. 9876543210" required pattern="^[0-9]{10}$">
                                </div>

                                <div class="col-md-6">
                                    <label for="student_id_no" class="form-label fw-semibold">Student ID / Enrollment No</label>
                                    <input type="text" class="form-control" id="student_id_no" name="student_id_no" value="<?= htmlspecialchars($student_id_val) ?>" placeholder="e.g. 25CE082">
                                </div>

                                <div class="col-md-6">
                                    <label for="department" class="form-label fw-semibold">Department / Course <span class="text-danger">*</span></label>
                                    <select class="form-select" id="department" name="department" required>
                                        <option value="CE" <?= $dept_val === 'CE' ? 'selected' : '' ?>>Computer Engineering (CE)</option>
                                        <option value="IT" <?= $dept_val === 'IT' ? 'selected' : '' ?>>Information Technology (IT)</option>
                                        <option value="CSE" <?= $dept_val === 'CSE' ? 'selected' : '' ?>>Computer Science & Engg (CSE)</option>
                                        <option value="EC" <?= $dept_val === 'EC' ? 'selected' : '' ?>>Electronics & Comm (EC)</option>
                                        <option value="ME" <?= $dept_val === 'ME' ? 'selected' : '' ?>>Mechanical Engineering (ME)</option>
                                    </select>
                                </div>

                                <div class="col-md-6">
                                    <label for="password" class="form-label fw-semibold">Password <span class="text-danger">*</span></label>
                                    <input type="password" class="form-control" id="password" name="password" placeholder="Min 8 chars, 1 upper, 1 digit" required minlength="8">
                                </div>

                                <div class="col-md-6">
                                    <label for="confirm_password" class="form-label fw-semibold">Confirm Password <span class="text-danger">*</span></label>
                                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" placeholder="Re-enter password" required minlength="8">
                                </div>

                                <div class="col-12 mt-4">
                                    <button type="submit" class="btn btn-primary btn-lg w-100 fw-bold">
                                        <i class="bi bi-person-check-fill me-2"></i>Complete Registration
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>

                    <div class="card-footer bg-light text-center py-3">
                        <span class="text-muted small">Already registered? </span>
                        <a href="login.php" class="fw-bold">Sign In here</a>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <footer class="app-footer text-center py-3">
        <div class="container">
            <p class="mb-1 text-light fw-bold">MIRAL PATEL || 25CE082 || STUDENT HUB PORTAL</p>
            <p class="mb-0 text-white-50 small">&copy; <?= date('Y') ?> CSPIT / CHARUSAT. Practical 15 Final Integration & Deployment.</p>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

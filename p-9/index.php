<?php
session_start();
require_once "db.php";

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$errors = [];
$success_message = "";
$registered_user = null;

$full_name = "";
$username = "";
$email = "";
$mobile = "";
$role = "Student";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $submitted_token = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '';
    if (!hash_equals($_SESSION['csrf_token'], $submitted_token)) {
        $errors[] = "Security check failed: Invalid or expired CSRF token.";
    }

    $full_name = isset($_POST['full_name']) ? trim($_POST['full_name']) : '';
    $username = isset($_POST['username']) ? trim($_POST['username']) : '';
    $email = isset($_POST['email']) ? trim($_POST['email']) : '';
    $mobile = isset($_POST['mobile']) ? trim($_POST['mobile']) : '';
    $role = isset($_POST['role']) ? trim($_POST['role']) : 'Student';
    $password = isset($_POST['password']) ? $_POST['password'] : '';
    $confirm_password = isset($_POST['confirm_password']) ? $_POST['confirm_password'] : '';

    $ip_address = !empty($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '127.0.0.1';
    $user_agent = !empty($_SERVER['HTTP_USER_AGENT']) ? substr($_SERVER['HTTP_USER_AGENT'], 0, 255) : 'Unknown';

    if (empty($full_name)) {
        $errors[] = "Full Name is required.";
    } elseif (!preg_match("/^[a-zA-Z\s]{3,100}$/", $full_name)) {
        $errors[] = "Full Name must contain only letters and spaces (min 3 characters).";
    }

    if (empty($username)) {
        $errors[] = "Username is required.";
    } elseif (!preg_match("/^[a-zA-Z0-9_]{4,30}$/", $username)) {
        $errors[] = "Username must be 4 to 30 characters (letters, numbers, underscore only).";
    }

    if (empty($email)) {
        $errors[] = "Email address is required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Please provide a valid email address.";
    }

    if (empty($mobile)) {
        $errors[] = "Mobile number is required.";
    } elseif (!preg_match("/^[0-9]{10}$/", $mobile)) {
        $errors[] = "Mobile number must be exactly 10 digits.";
    }

    $valid_roles = ['Student', 'Faculty', 'Admin'];
    if (!in_array($role, $valid_roles)) {
        $role = 'Student';
    }

    if (empty($password)) {
        $errors[] = "Password is required.";
    } elseif (strlen($password) < 6) {
        $errors[] = "Password must be at least 6 characters long.";
    }

    if ($password !== $confirm_password) {
        $errors[] = "Password and Confirm Password do not match.";
    }

    if ($conn === null) {
        $errors[] = "Database connection error. Please run setup_database.php first.";
    }

    if (empty($errors)) {
        $check_stmt = $conn->prepare("SELECT username, email FROM users WHERE username = ? OR email = ?");
        $check_stmt->bind_param("ss", $username, $email);
        $check_stmt->execute();
        $check_res = $check_stmt->get_result();

        $dup_username = false;
        $dup_email = false;

        while ($row = $check_res->fetch_assoc()) {
            if (strcasecmp($row['username'], $username) === 0) {
                $dup_username = true;
            }
            if (strcasecmp($row['email'], $email) === 0) {
                $dup_email = true;
            }
        }
        $check_stmt->close();

        if ($dup_username) {
            $errors[] = "Duplicate Username: The username '{$username}' is already taken.";
        }
        if ($dup_email) {
            $errors[] = "Duplicate Email: The email '{$email}' is already registered.";
        }

        if ($dup_username || $dup_email) {
            $null_uid = null;
            $fail_act = "USER_REGISTRATION";
            $fail_status = "DUPLICATE_ATTEMPT";
            $log_stmt = $conn->prepare("INSERT INTO audit_logs (user_id, username, action, status, ip_address, user_agent) VALUES (?, ?, ?, ?, ?, ?)");
            $log_stmt->bind_param("isssss", $null_uid, $username, $fail_act, $fail_status, $ip_address, $user_agent);
            $log_stmt->execute();
            $log_stmt->close();
        } else {
            $hashed_password = password_hash($password, PASSWORD_BCRYPT);

            $insert_stmt = $conn->prepare("INSERT INTO users (username, email, password, full_name, mobile, role) VALUES (?, ?, ?, ?, ?, ?)");
            $insert_stmt->bind_param("ssssss", $username, $email, $hashed_password, $full_name, $mobile, $role);

            if ($insert_stmt->execute()) {
                $new_user_id = $conn->insert_id;
                $insert_stmt->close();

                $log_stmt = $conn->prepare("INSERT INTO audit_logs (user_id, username, action, status, ip_address, user_agent) VALUES (?, ?, ?, ?, ?, ?)");
                $succ_act = "USER_REGISTRATION";
                $succ_status = "SUCCESS";
                $log_stmt->bind_param("isssss", $new_user_id, $username, $succ_act, $succ_status, $ip_address, $user_agent);
                $log_stmt->execute();
                $log_stmt->close();

                $success_message = "Registration successful! User registered with secure bcrypt password hash.";
                $registered_user = [
                    'id' => $new_user_id,
                    'username' => $username,
                    'email' => $email,
                    'full_name' => $full_name,
                    'mobile' => $mobile,
                    'role' => $role,
                    'hash_sample' => substr($hashed_password, 0, 28) . '...'
                ];

                $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

                $full_name = "";
                $username = "";
                $email = "";
                $mobile = "";
                $role = "Student";
            } else {
                $errors[] = "Registration failed due to database error: " . $insert_stmt->error;
                $insert_stmt->close();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>StudentHub - User Registration (Practical 9)</title>
    <link rel="stylesheet" href="style.css">
    <script>
        function validateFrontendForm() {
            var pass = document.getElementById("password").value;
            var conf = document.getElementById("confirm_password").value;
            var errDiv = document.getElementById("client-errors");
            errDiv.style.display = "none";
            errDiv.innerHTML = "";

            var errors = [];
            if (pass.length < 6) {
                errors.push("Password must be at least 6 characters long.");
            }
            if (pass !== conf) {
                errors.push("Password and Confirm Password do not match.");
            }

            if (errors.length > 0) {
                errDiv.innerHTML = errors.map(function(e) { return "<div>&cross; " + e + "</div>"; }).join("");
                errDiv.style.display = "block";
                return false;
            }
            return true;
        }

        function checkPasswordMatch() {
            var pass = document.getElementById("password").value;
            var conf = document.getElementById("confirm_password").value;
            var matchSpan = document.getElementById("match-hint");

            if (conf.length === 0) {
                matchSpan.innerHTML = "";
            } else if (pass === conf) {
                matchSpan.innerHTML = "<span style='color: green; font-weight: bold;'>&check; Passwords match</span>";
            } else {
                matchSpan.innerHTML = "<span style='color: red; font-weight: bold;'>&cross; Passwords do not match</span>";
            }
        }
    </script>
</head>
<body>
    <header id="header">
        <img src="image/charusatlogo.png" alt="CHARUSAT Logo">
        <div>StudentHub Registration System</div>
        <nav>
            <a href="index.php" class="active">Register</a>
            <a href="users_list.php">View Users &amp; Logs</a>
            <a href="setup_database.php">Setup Database</a>
        </nav>
    </header>

    <main class="main-content">
        <section class="container">
            <h2>Practical 9: Secure User Registration</h2>
            <div class="info-box">
                Features: MySQLi Prepared Statements, Backend + Frontend Validation, Duplicate Email/Username Prevention, <code>password_hash(PASSWORD_BCRYPT)</code> Encryption, CSRF Protection, and Audit Logging.
            </div>

            <div id="client-errors" class="alert-error" style="display: none;"></div>

            <?php if (!empty($errors)): ?>
                <div class="alert-error">
                    <?php foreach ($errors as $err): ?>
                        <div>&cross; <?php echo htmlspecialchars($err); ?></div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($success_message) && $registered_user): ?>
                <div class="alert-success">
                    <div>&check; <?php echo htmlspecialchars($success_message); ?></div>
                    <div style="margin-top: 8px; font-size: 13px;">
                        <strong>User ID:</strong> <?php echo $registered_user['id']; ?> |
                        <strong>Username:</strong> <?php echo htmlspecialchars($registered_user['username']); ?> |
                        <strong>Email:</strong> <?php echo htmlspecialchars($registered_user['email']); ?> |
                        <strong>Role:</strong> <?php echo htmlspecialchars($registered_user['role']); ?>
                    </div>
                    <div style="margin-top: 6px; font-size: 12px;">
                        <strong>Bcrypt Hash Stored in DB:</strong>
                        <span class="hash-code"><?php echo htmlspecialchars($registered_user['hash_sample']); ?></span>
                    </div>
                </div>
            <?php endif; ?>

            <form action="index.php" method="POST" onsubmit="return validateFrontendForm();" novalidate>
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">

                <div class="form-grid">
                    <div class="form-group">
                        <label for="full_name">Full Name <span class="req">*</span></label>
                        <input type="text" id="full_name" name="full_name" required pattern="[A-Za-z\s]{3,100}" placeholder="e.g. Miral Patel" value="<?php echo htmlspecialchars($full_name); ?>">
                        <span class="hint">Min 3 characters, alphabets and spaces only</span>
                    </div>

                    <div class="form-group">
                        <label for="username">Username <span class="req">*</span></label>
                        <input type="text" id="username" name="username" required pattern="[A-Za-z0-9_]{4,30}" placeholder="e.g. miral082" value="<?php echo htmlspecialchars($username); ?>">
                        <span class="hint">4-30 chars, letters, numbers, and underscore only</span>
                    </div>

                    <div class="form-group">
                        <label for="email">Email Address <span class="req">*</span></label>
                        <input type="email" id="email" name="email" required placeholder="e.g. 25ce082@charusat.edu.in" value="<?php echo htmlspecialchars($email); ?>">
                        <span class="hint">Unique email address for your student account</span>
                    </div>

                    <div class="form-group">
                        <label for="mobile">Mobile Number <span class="req">*</span></label>
                        <input type="tel" id="mobile" name="mobile" required pattern="[0-9]{10}" maxlength="10" placeholder="e.g. 9876543210" value="<?php echo htmlspecialchars($mobile); ?>">
                        <span class="hint">Exact 10 digits Indian mobile number</span>
                    </div>

                    <div class="form-group">
                        <label for="role">User Role <span class="req">*</span></label>
                        <select id="role" name="role" required>
                            <option value="Student" <?php echo ($role === 'Student') ? 'selected' : ''; ?>>Student</option>
                            <option value="Faculty" <?php echo ($role === 'Faculty') ? 'selected' : ''; ?>>Faculty</option>
                            <option value="Admin" <?php echo ($role === 'Admin') ? 'selected' : ''; ?>>Admin</option>
                        </select>
                        <span class="hint">Select account permission level</span>
                    </div>

                    <div class="form-group">
                        <label for="password">Password <span class="req">*</span></label>
                        <input type="password" id="password" name="password" required minlength="6" placeholder="At least 6 characters" onkeyup="checkPasswordMatch();">
                        <span class="hint">Encrypted using password_hash() before DB insert</span>
                    </div>

                    <div class="form-group">
                        <label for="confirm_password">Confirm Password <span class="req">*</span></label>
                        <input type="password" id="confirm_password" name="confirm_password" required minlength="6" placeholder="Re-enter same password" onkeyup="checkPasswordMatch();">
                        <span id="match-hint" class="hint"></span>
                    </div>
                </div>

                <div class="btn-row" style="margin-top: 20px;">
                    <button type="submit" class="btn-submit">Register Account</button>
                    <a href="users_list.php" class="btn-secondary">View Registered Users &amp; Audit Logs</a>
                </div>
            </form>
        </section>
    </main>

    <footer>
        MIRAL PATEL||25CE082||STUDENT HUB PORTAL
    </footer>
</body>
</html>

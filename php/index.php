<?php
session_start();

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$name = $email = $mobile = $course = $year = $gender = "";
$errors = [];
$success = "";

function sanitize($data) {
    return htmlspecialchars(stripslashes(trim($data)));
}

if (isset($_SERVER["REQUEST_METHOD"]) && $_SERVER["REQUEST_METHOD"] == "POST") {

    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $errors['csrf'] = "Invalid CSRF token. Please submit the form again.";
    }

    $name = isset($_POST['name']) ? sanitize($_POST['name']) : "";
    $email = isset($_POST['email']) ? sanitize($_POST['email']) : "";
    $mobile = isset($_POST['mobile']) ? sanitize($_POST['mobile']) : "";
    $course = isset($_POST['course']) ? sanitize($_POST['course']) : "";
    $year = isset($_POST['year']) ? sanitize($_POST['year']) : "";
    $gender = isset($_POST['gender']) ? sanitize($_POST['gender']) : "";
    $terms = isset($_POST['terms']);

    if (empty($name)) {
        $errors['name'] = "Name is required.";
    } elseif (!preg_match("/^[a-zA-Z\s]{2,50}$/", $name)) {
        $errors['name'] = "Name must only contain letters and spaces (2-50 characters).";
    }

    if (empty($email)) {
        $errors['email'] = "Email is required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = "Enter a valid email address.";
    }

    if (empty($mobile)) {
        $errors['mobile'] = "Mobile number is required.";
    } elseif (!preg_match("/^[6-9][0-9]{9}$/", $mobile)) {
        $errors['mobile'] = "Mobile must be 10 digits starting with 6, 7, 8, or 9.";
    }

    $allowed_courses = ["CE", "IT", "CS", "AIML"];
    if (empty($course) || !in_array($course, $allowed_courses)) {
        $errors['course'] = "Please select a valid course.";
    }

    $allowed_years = ["1st", "2nd", "3rd", "4th"];
    if (empty($year) || !in_array($year, $allowed_years)) {
        $errors['year'] = "Please select a valid year.";
    }

    $allowed_genders = ["Male", "Female", "Other"];
    if (empty($gender) || !in_array($gender, $allowed_genders)) {
        $errors['gender'] = "Please select a gender.";
    }

    if (!$terms) {
        $errors['terms'] = "You must agree to the terms and conditions.";
    }

    if (empty($errors)) {
        $created_at = date("Y-m-d H:i:s");

        $jsonFile = __DIR__ . "/records.json";
        $records = [];
        if (file_exists($jsonFile)) {
            $jsonContent = file_get_contents($jsonFile);
            $records = json_decode($jsonContent, true) ?: [];
        }

        $nextId = count($records) + 1;
        $newRecord = [
            "id" => $nextId,
            "name" => $name,
            "email" => $email,
            "mobile" => $mobile,
            "course" => $course,
            "year" => $year,
            "gender" => $gender,
            "created_at" => $created_at
        ];

        $records[] = $newRecord;
        file_put_contents($jsonFile, json_encode($records, JSON_PRETTY_PRINT), LOCK_EX);

        $csvFile = __DIR__ . "/records.csv";
        $needsHeader = !file_exists($csvFile) || filesize($csvFile) == 0;
        $fileHandle = fopen($csvFile, "a");
        if ($fileHandle) {
            if (flock($fileHandle, LOCK_EX)) {
                if ($needsHeader) {
                    fputcsv($fileHandle, ["ID", "Name", "Email", "Mobile", "Course", "Year", "Gender", "Date"]);
                }
                fputcsv($fileHandle, [$nextId, $name, $email, $mobile, $course, $year, $gender, $created_at]);
                flock($fileHandle, LOCK_UN);
            }
            fclose($fileHandle);
        }

        $success = "Registration successful! Record has been securely saved to JSON and CSV files.";
        $name = $email = $mobile = $course = $year = $gender = "";
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>StudentHub - PHP Registration Form</title>
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
        <div class="container">
            <h2>Student Registration Form</h2>

            <?php if (!empty($success)): ?>
                <div class="alert-success"><?php echo $success; ?></div>
            <?php endif; ?>

            <?php if (isset($errors['csrf'])): ?>
                <div class="alert-error"><?php echo $errors['csrf']; ?></div>
            <?php endif; ?>

            <form action="index.php" method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">

                <p>
                    <label for="name">Full Name:</label>
                    <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($name); ?>" placeholder="Enter your full name">
                    <?php if (isset($errors['name'])): ?>
                        <span class="error-text"><?php echo $errors['name']; ?></span>
                    <?php endif; ?>
                </p>

                <p>
                    <label for="email">Email Address:</label>
                    <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($email); ?>" placeholder="example@charusat.edu.in">
                    <?php if (isset($errors['email'])): ?>
                        <span class="error-text"><?php echo $errors['email']; ?></span>
                    <?php endif; ?>
                </p>

                <p>
                    <label for="mobile">Mobile Number:</label>
                    <input type="tel" id="mobile" name="mobile" value="<?php echo htmlspecialchars($mobile); ?>" placeholder="10-digit mobile number" maxlength="10">
                    <?php if (isset($errors['mobile'])): ?>
                        <span class="error-text"><?php echo $errors['mobile']; ?></span>
                    <?php endif; ?>
                </p>

                <p>
                    <label for="course">Course / Department:</label>
                    <select id="course" name="course">
                        <option value="">-- Select Course --</option>
                        <option value="CE" <?php if ($course == "CE") echo "selected"; ?>>Computer Engineering (CE)</option>
                        <option value="IT" <?php if ($course == "IT") echo "selected"; ?>>Information Technology (IT)</option>
                        <option value="CS" <?php if ($course == "CS") echo "selected"; ?>>Computer Science (CS)</option>
                        <option value="AIML" <?php if ($course == "AIML") echo "selected"; ?>>Artificial Intelligence (AIML)</option>
                    </select>
                    <?php if (isset($errors['course'])): ?>
                        <span class="error-text"><?php echo $errors['course']; ?></span>
                    <?php endif; ?>
                </p>

                <p>
                    <label for="year">Year of Study:</label>
                    <select id="year" name="year">
                        <option value="">-- Select Year --</option>
                        <option value="1st" <?php if ($year == "1st") echo "selected"; ?>>1st Year</option>
                        <option value="2nd" <?php if ($year == "2nd") echo "selected"; ?>>2nd Year</option>
                        <option value="3rd" <?php if ($year == "3rd") echo "selected"; ?>>3rd Year</option>
                        <option value="4th" <?php if ($year == "4th") echo "selected"; ?>>4th Year</option>
                    </select>
                    <?php if (isset($errors['year'])): ?>
                        <span class="error-text"><?php echo $errors['year']; ?></span>
                    <?php endif; ?>
                </p>

                <div class="gender-box">
                    <label>Gender:</label><br>
                    <label><input type="radio" name="gender" value="Male" <?php if ($gender == "Male") echo "checked"; ?>> Male</label>
                    <label><input type="radio" name="gender" value="Female" <?php if ($gender == "Female") echo "checked"; ?>> Female</label>
                    <label><input type="radio" name="gender" value="Other" <?php if ($gender == "Other") echo "checked"; ?>> Other</label>
                    <?php if (isset($errors['gender'])): ?>
                        <span class="error-text"><?php echo $errors['gender']; ?></span>
                    <?php endif; ?>
                </div>

                <div class="terms-box">
                    <label>
                        <input type="checkbox" name="terms" value="1"> I accept the terms and conditions
                    </label>
                    <?php if (isset($errors['terms'])): ?>
                        <span class="error-text"><?php echo $errors['terms']; ?></span>
                    <?php endif; ?>
                </div>

                <p style="text-align: center;">
                    <input type="submit" value="Register" class="btn-submit">
                    <input type="reset" value="Reset" class="btn-reset">
                </p>
            </form>

            <div style="text-align: center; margin-top: 15px;">
                <a href="view_records.php" class="btn-link">View Stored Records (CSV / JSON)</a>
            </div>
        </div>
    </main>

    <footer>
        MIRAL PATEL||25CE082||STUDENT HUB PORTAL
    </footer>

</body>
</html>

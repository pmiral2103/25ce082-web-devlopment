<?php
require_once "auth_helper.php";

if (isset($_SESSION['user_id'])) {
    $_SESSION['last_activity'] = time() - 1000;
}

header("Location: student_dashboard.php");
exit();

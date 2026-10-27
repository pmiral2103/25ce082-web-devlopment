<?php
$host = "localhost";
$username = "root";
$password = "";
$database = "studenthub_db";

$conn = @new mysqli($host, $username, $password, $database);

if ($conn->connect_error) {
    $conn = null;
} else {
    $conn->set_charset("utf8mb4");
}

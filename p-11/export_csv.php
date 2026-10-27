<?php
require_once "db.php";

if ($conn === null) {
    die("Database connection failed.");
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

$sql .= " ORDER BY id ASC";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=charusat_students_export_' . date('Y-m-d') . '.csv');
header('Pragma: no-cache');
header('Expires: 0');

$output = fopen('php://output', 'w');

fputcsv($output, ['ID', 'Student ID No', 'Full Name', 'Institutional Email', 'Mobile', 'Department', 'Semester', 'CGPA', 'Status', 'Registered Date']);

while ($row = $result->fetch_assoc()) {
    fputcsv($output, [
        $row['id'],
        $row['student_id_no'],
        $row['name'],
        $row['email'],
        $row['mobile'],
        $row['department'],
        $row['semester'],
        $row['cgpa'],
        $row['status'],
        $row['created_at']
    ]);
}

fclose($output);
$stmt->close();
$conn->close();
exit();
